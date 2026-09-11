<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use Modules\QuestionBank\Support\ServePublishedQuestion;

/**
 * Adaptive session picker (docs/adaptive-session-algorithm.md).
 *
 * Pipeline:
 *   pool → split unseen/seen → score seen (Weakness + Memory × mode) → cooldown
 *   → weighted random without replacement → shuffle.
 *
 * Debug: mỗi bước ghi Log::debug channel `adaptive` (xem storage/logs/laravel.log).
 */
final class AdaptiveQuestionSelector
{
    /** Memory curve: MemoryScore = 1 - e^(-days / T). */
    private const MEMORY_TAU_DAYS = 20.0;

    /** Floor weight so sampling never hard-zeros a candidate. */
    private const WEIGHT_EPSILON = 0.01;

    /**
     * Mode weights: BasePriority = wW × Weakness + wM × Memory.
     *
     * @var array<string, array{w: float, m: float}>
     */
    private const FOCUS_WEIGHTS = [
        'weak_focus' => ['w' => 0.85, 'm' => 0.15],
        'balanced' => ['w' => 0.55, 'm' => 0.45],
        'retention' => ['w' => 0.30, 'm' => 0.70],
    ];

    public function __construct(
        private readonly QuestionFilterBuilder $filters,
    ) {}

    /**
     * @return array<int, string> Question UUIDs for the new session
     */
    public function pick(int $userId, int $limit, bool $canUsePremium, CreateSessionData $data): array
    {
        $focus = $this->normalizeFocus($data->adaptiveFocus);
        $weights = self::FOCUS_WEIGHTS[$focus];
        $now = CarbonImmutable::now();

        // ─── DEBUG: đầu vào phiên ───────────────────────────────────────────
        Log::debug('[adaptive] start', [
            'user_id' => $userId,
            'limit' => $limit,
            'focus' => $focus,
            'w_weakness' => $weights['w'],
            'w_memory' => $weights['m'],
            'blueprint_id' => $data->blueprintId,
            'organ_system_ids' => $data->organSystemIds,
            'subject_ids' => $data->subjectIds,
            'can_use_premium' => $canUsePremium,
        ]);

        $poolIds = $this->poolQuestionIds($userId, $canUsePremium, $data);

        if ($poolIds === []) {
            Log::debug('[adaptive] empty pool — no questions', ['user_id' => $userId]);

            return [];
        }

        $limit = max(1, min($limit, count($poolIds)));

        // ─── Bước 2: Coverage split (unseen = chưa từng attempt chấm/omit) ──
        $seenIds = $this->seenQuestionIds($userId, $poolIds);
        $unseenIds = array_values(array_diff($poolIds, $seenIds));

        $poolSize = count($poolIds);
        $unseenCount = count($unseenIds);
        $unseenRatio = $poolSize > 0 ? $unseenCount / $poolSize : 0.0;

        // quota_unseen = clamp(round(N × (0.4 + 0.5 × unseen_ratio)), 0|1, N)
        $quotaUnseen = $unseenCount === 0
            ? 0
            : (int) max(1, min($limit, (int) round($limit * (0.4 + 0.5 * $unseenRatio))));
        $quotaUnseen = min($quotaUnseen, $unseenCount, $limit);
        $quotaReview = $limit - $quotaUnseen;

        Log::debug('[adaptive] coverage split', [
            'pool_size' => $poolSize,
            'unseen_count' => $unseenCount,
            'seen_count' => count($seenIds),
            'unseen_ratio' => round($unseenRatio, 3),
            'quota_unseen' => $quotaUnseen,
            'quota_review' => $quotaReview,
        ]);

        $pickedUnseen = $this->sampleUniform($unseenIds, $quotaUnseen);
        $pickedReview = $quotaReview > 0
            ? $this->sampleReview($userId, $seenIds, $quotaReview, $focus, $weights, $now)
            : [];

        // Nếu review thiếu (pool seen nhỏ), bù thêm unseen còn lại.
        $picked = array_values(array_unique([...$pickedUnseen, ...$pickedReview]));
        if (count($picked) < $limit) {
            $need = $limit - count($picked);
            $filler = $this->sampleUniform(
                array_values(array_diff($poolIds, $picked)),
                $need,
            );
            $picked = array_values(array_unique([...$picked, ...$filler]));

            Log::debug('[adaptive] top-up after shortfall', [
                'need' => $need,
                'filled' => count($filler),
            ]);
        }

        // ─── Bước 6: shuffle thứ tự hiển thị (tránh dồn câu “nặng” lên đầu) ─
        $ordered = collect($picked)->shuffle()->values()->all();

        Log::debug('[adaptive] result', [
            'focus' => $focus,
            'picked_count' => count($ordered),
            'picked_unseen' => count($pickedUnseen),
            'picked_review' => count($pickedReview),
            'question_ids' => $ordered,
        ]);

        return $ordered;
    }

    /**
     * Full adaptive pool size (blueprint ± hệ/môn), không thu hẹp theo weak lessons.
     */
    public function countPool(int $userId, bool $canUsePremium, CreateSessionData $data): int
    {
        return count($this->poolQuestionIds($userId, $canUsePremium, $data));
    }

    /**
     * @return array<int, string>
     */
    private function poolQuestionIds(int $userId, bool $canUsePremium, CreateSessionData $data): array
    {
        $lessonIds = $this->poolLessonIds($data);

        $query = ServePublishedQuestion::scopeAvailable(Question::query()->select('id'))
            ->when(! $canUsePremium, fn (Builder $q) => $q->where('is_free', true))
            ->when(
                $lessonIds !== [],
                fn (Builder $q) => $q->whereHas(
                    'lessons',
                    fn (Builder $lessons) => $lessons->whereIn('lessons.id', $lessonIds),
                ),
            );

        $this->filters->apply(
            $query,
            blueprintId: $data->blueprintId,
            blueprintSectionId: $data->blueprintSectionId,
            coreClinicalTopicIds: $data->coreClinicalTopicIds,
            tagIds: $data->tagIds,
        );

        $ids = $query->pluck('id')->map(fn ($id) => (string) $id)->all();

        Log::debug('[adaptive] pool built', [
            'lesson_ids_count' => count($lessonIds),
            'pool_size' => count($ids),
            'blueprint_id' => $data->blueprintId,
        ]);

        return $ids;
    }

    /**
     * Lesson scope for adaptive: optional hệ/môn ∩ matrix; không dùng weak-lesson heuristic.
     *
     * @return array<int, int>
     */
    private function poolLessonIds(CreateSessionData $data): array
    {
        $matrixLessonIds = $data->blueprintId !== null
            ? $this->filters->mappedLessonIdsForBlueprint(blueprintId: $data->blueprintId)
            : [];

        $scopedLessonIds = $this->filters->resolveContentLessonIds(
            $data->organSystemIds,
            $data->subjectIds,
            [], // adaptive UI không chọn bài học thủ công
        );

        if ($scopedLessonIds === []) {
            return $matrixLessonIds;
        }

        if ($matrixLessonIds === []) {
            return $scopedLessonIds;
        }

        $allowed = array_flip($matrixLessonIds);

        return array_values(array_filter(
            $scopedLessonIds,
            static fn (int $id): bool => isset($allowed[$id]),
        ));
    }

    /**
     * Seen = đã có attempt (đúng/sai/omit) hoặc last_seen_at đã được ghi.
     *
     * @param  array<int, string>  $poolIds
     * @return array<int, string>
     */
    private function seenQuestionIds(int $userId, array $poolIds): array
    {
        if ($poolIds === []) {
            return [];
        }

        $fromAttempts = QuestionAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('question_id', $poolIds)
            ->distinct()
            ->pluck('question_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $fromStatus = UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->whereIn('question_id', $poolIds)
            ->where(function ($query): void {
                $query->whereNotNull('last_seen_at')
                    ->orWhere('correct_count', '>', 0)
                    ->orWhere('wrong_count', '>', 0)
                    ->orWhere('omitted_count', '>', 0)
                    ->orWhereIn('status', [
                        UserQuestionStatus::Correct,
                        UserQuestionStatus::Incorrect,
                        UserQuestionStatus::Omitted,
                    ]);
            })
            ->pluck('question_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_values(array_unique([...$fromAttempts, ...$fromStatus]));
    }

    /**
     * Score + cooldown + weighted sample for the review half of the session.
     *
     * @param  array<int, string>  $seenIds
     * @param  array{w: float, m: float}  $weights
     * @return array<int, string>
     */
    private function sampleReview(
        int $userId,
        array $seenIds,
        int $limit,
        string $focus,
        array $weights,
        CarbonImmutable $now,
    ): array {
        if ($seenIds === [] || $limit <= 0) {
            return [];
        }

        $stats = UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->whereIn('question_id', $seenIds)
            ->get()
            ->keyBy(fn (UserQuestionStatusModel $row): string => (string) $row->question_id);

        // Số session user tạo SAU lần serve gần nhất → cooldown window.
        $userSessionsAfter = $this->sessionsCreatedAfterMap($userId, $stats);

        $scored = [];
        $debugTop = [];

        foreach ($seenIds as $questionId) {
            /** @var UserQuestionStatusModel|null $row */
            $row = $stats->get($questionId);

            $correct = (int) ($row?->correct_count ?? 0);
            $wrong = (int) ($row?->wrong_count ?? 0);
            $gradedAttempts = $correct + $wrong;

            // Weakness (Laplace): (wrong+1)/(n+2) — omit KHÔNG vào mẫu.
            $weakness = ($wrong + 1) / ($gradedAttempts + 2);

            // Memory: 1 - e^(-days/T); chưa từng last_seen → coi như rất lâu (1.0).
            $lastSeen = $row?->last_seen_at;
            $days = $lastSeen === null
                ? 365.0
                : max(0.0, abs((float) $now->diffInDays($lastSeen)));
            $memory = 1.0 - exp(-$days / self::MEMORY_TAU_DAYS);

            $base = ($weights['w'] * $weakness) + ($weights['m'] * $memory);

            $sessionsSinceServed = $userSessionsAfter[$questionId] ?? PHP_INT_MAX;
            $lastServedAt = $row?->last_served_at;
            $cooldown = $this->cooldownFactor(
                $sessionsSinceServed,
                $lastServedAt !== null ? CarbonImmutable::parse($lastServedAt) : null,
                $now,
            );
            $weight = max(self::WEIGHT_EPSILON, $base * $cooldown);

            $scored[$questionId] = $weight;
            $debugTop[] = [
                'question_id' => $questionId,
                'correct' => $correct,
                'wrong' => $wrong,
                'weakness' => round($weakness, 4),
                'days_since_seen' => round($days, 2),
                'memory' => round($memory, 4),
                'base' => round($base, 4),
                'sessions_since_served' => $sessionsSinceServed === PHP_INT_MAX ? null : $sessionsSinceServed,
                'last_served_at' => $lastServedAt?->toDateTimeString(),
                'cooldown' => $cooldown,
                'weight' => round($weight, 4),
            ];
        }

        usort($debugTop, static fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);

        Log::debug('[adaptive] review scores (top 15)', [
            'focus' => $focus,
            'candidates' => count($scored),
            'top' => array_slice($debugTop, 0, 15),
        ]);

        $picked = $this->weightedSampleWithoutReplacement($scored, $limit);

        Log::debug('[adaptive] review sampled', [
            'picked' => $picked,
            'weights' => array_intersect_key($scored, array_flip($picked)),
        ]);

        return $picked;
    }

    /**
     * @param  Collection<string, UserQuestionStatusModel>  $stats
     * @return array<string, int> question_id => sessions created after last_served_at
     */
    private function sessionsCreatedAfterMap(int $userId, Collection $stats): array
    {
        $servedAts = $stats
            ->filter(fn (UserQuestionStatusModel $row): bool => $row->last_served_at !== null)
            ->mapWithKeys(fn (UserQuestionStatusModel $row): array => [
                (string) $row->question_id => $row->last_served_at?->toDateTimeString(),
            ]);

        if ($servedAts->isEmpty()) {
            return [];
        }

        // Đếm session của user có created_at > last_served_at (không gồm session sắp tạo).
        $sessionTimes = QuestionSession::query()
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(fn ($at) => CarbonImmutable::parse($at))
            ->all();

        $map = [];
        foreach ($servedAts as $questionId => $servedAt) {
            $served = CarbonImmutable::parse((string) $servedAt);
            $count = 0;
            foreach ($sessionTimes as $created) {
                if ($created->greaterThan($served)) {
                    $count++;
                }
            }
            $map[$questionId] = $count;
        }

        return $map;
    }

    /**
     * CooldownFactor: vừa đưa vào session gần đây → giảm mạnh.
     *
     *   sessions_since_served | last_served age | factor
     *   ≥2                    | bất kỳ          | 1.00
     *   1                     | bất kỳ          | 0.10
     *   0 (chưa có session mới) + served ≤2 ngày | 0.30
     *   0 + served >2 ngày (im lặng lâu)         | 1.00  ← tránh phạt oan
     *   chưa từng serve                          | 1.00
     */
    private function cooldownFactor(
        int $sessionsSinceServed,
        ?CarbonImmutable $lastServedAt,
        CarbonImmutable $now,
    ): float {
        if ($sessionsSinceServed >= 2 || $sessionsSinceServed === PHP_INT_MAX) {
            return 1.0;
        }

        if ($sessionsSinceServed === 1) {
            return 0.10;
        }

        // sessionsSinceServed === 0: chưa có session nào sau lần serve.
        if ($lastServedAt === null) {
            return 1.0;
        }

        $daysSinceServed = abs((float) $now->diffInDays($lastServedAt));

        // Cửa sổ cooldown theo thời gian khi user chưa mở session mới.
        return $daysSinceServed <= 2.0 ? 0.30 : 1.0;
    }

    /**
     * Weighted random sampling without replacement (roulette, O(n·k)).
     *
     * @param  array<string, float>  $weights question_id => weight
     * @return array<int, string>
     */
    private function weightedSampleWithoutReplacement(array $weights, int $limit): array
    {
        $picked = [];
        $remaining = $weights;

        for ($i = 0; $i < $limit && $remaining !== []; $i++) {
            $total = array_sum($remaining);
            if ($total <= 0) {
                break;
            }

            $target = (mt_rand() / mt_getrandmax()) * $total;
            $cursor = 0.0;
            $chosen = array_key_first($remaining);

            foreach ($remaining as $questionId => $weight) {
                $cursor += $weight;
                if ($cursor >= $target) {
                    $chosen = $questionId;
                    break;
                }
            }

            $picked[] = (string) $chosen;
            unset($remaining[$chosen]);
        }

        return $picked;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    private function sampleUniform(array $ids, int $limit): array
    {
        if ($limit <= 0 || $ids === []) {
            return [];
        }

        shuffle($ids);

        return array_values(array_slice($ids, 0, $limit));
    }

    private function normalizeFocus(?string $focus): string
    {
        $focus = $focus ?: 'balanced';

        return array_key_exists($focus, self::FOCUS_WEIGHTS) ? $focus : 'balanced';
    }
}
