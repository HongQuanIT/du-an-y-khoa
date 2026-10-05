<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\Models\LearnerProfile;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Support\AdaptiveLearning;
use Modules\QuestionBank\Support\AdaptiveTrace;
use Modules\QuestionBank\Support\MemoryStability;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use Modules\QuestionBank\Support\ServePublishedQuestion;

/**
 * Adaptive V2: Lọc → Phân nhóm → Phân suất (mophong khung).
 *
 * Độ bền thang bậc 1–3–7–14–30–60; R = 0,9^(t/S); đến hạn = t ≥ S.
 * Log: pipeline filter_group_quota_v2.
 */
final class AdaptiveQuestionSelector
{
    public function __construct(
        private readonly QuestionFilterBuilder $filters,
    ) {}

    /**
     * @return array<int, string> Question UUIDs for the new session
     */
    public function pick(int $userId, int $limit, bool $canUsePremium, CreateSessionData $data): array
    {
        $focus = $this->normalizeFocus($data->adaptiveFocus);
        $now = CarbonImmutable::now();

        $this->trace('start', [
            'user_id' => $userId,
            'limit' => $limit,
            'focus' => $focus,
            'pipeline' => AdaptiveLearning::PIPELINE,
            'blueprint_id' => $data->blueprintId,
            'organ_system_ids' => $data->organSystemIds,
            'subject_ids' => $data->subjectIds,
            'can_use_premium' => $canUsePremium,
        ]);

        $poolIds = $this->poolQuestionIds($userId, $canUsePremium, $data);
        if ($poolIds === []) {
            $this->trace('empty_pool', ['user_id' => $userId]);

            return [];
        }

        $limit = max(1, min($limit, count($poolIds)));

        $metaById = $this->loadQuestionMeta($poolIds);
        $stats = UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->whereIn('question_id', $poolIds)
            ->get()
            ->keyBy(fn (UserQuestionStatusModel $row): string => (string) $row->question_id);

        $sessionTimes = QuestionSession::query()
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(fn ($at) => CarbonImmutable::parse($at))
            ->all();

        // ─── Lọc ───────────────────────────────────────────────
        $excluded = [
            'thrash' => 0,
            'cooldown' => 0,
            'version_mismatch' => 0,
            'ungraded_status' => 0,
        ];
        $eligible = []; // graded + pass filter
        $unseen = [];   // no valid graded state for current content version
        $resting = [];  // thrash / cooldown — liệt kê cho admin briefing

        foreach ($poolIds as $questionId) {
            $meta = $metaById[$questionId] ?? null;
            if ($meta === null) {
                continue;
            }

            /** @var UserQuestionStatusModel|null $row */
            $row = $stats->get($questionId);
            $contentVersion = (int) ($meta['content_version'] ?? 0);

            $hasGraded = $row !== null
                && $row->last_graded_at !== null
                && $row->memory_stability_days !== null
                && ($row->content_version === null || (int) $row->content_version === $contentVersion);

            if ($row !== null && $row->content_version !== null && (int) $row->content_version !== $contentVersion) {
                $excluded['version_mismatch']++;
                $hasGraded = false;
            }

            if (! $hasGraded) {
                $unseen[] = $questionId;

                continue;
            }

            $stability = (float) $row->memory_stability_days;
            $dueAt = AdaptiveLearning::dueAt($stability, $row->last_graded_at);
            $memorySnapshot = [
                'stability' => $stability,
                'days_since' => MemoryStability::elapsedDays($row->last_graded_at, $now),
                'retention' => AdaptiveLearning::retention($stability, $row->last_graded_at, $now),
                'is_due' => AdaptiveLearning::isDue($stability, $row->last_graded_at, $now),
                'due_at' => $dueAt?->toIso8601String(),
            ];

            $wrongStreak = (int) ($row->wrong_streak ?? 0);
            $sessionsAfterGraded = $this->sessionsAfter($sessionTimes, $row->last_graded_at);
            if (AdaptiveLearning::isThrashBlocked(
                $row->thrash_blocked_until,
                $wrongStreak,
                $sessionsAfterGraded,
                $now,
            )) {
                $excluded['thrash']++;
                $timeUntil = $row->thrash_blocked_until !== null
                    ? CarbonImmutable::parse($row->thrash_blocked_until)
                    : null;
                $sessionsLeft = max(0, AdaptiveLearning::THRASH_MILD_SESSIONS - $sessionsAfterGraded);
                $detail = $wrongStreak >= AdaptiveLearning::THRASH_SEVERE_STREAK
                    ? sprintf(
                        'Sai liên tiếp %d — tạm không đưa vào phiên %d ngày',
                        $wrongStreak,
                        AdaptiveLearning::THRASH_SEVERE_DAYS,
                    )
                    : sprintf(
                        'Sai liên tiếp %d — nghỉ đến hết 72 giờ%s',
                        $wrongStreak,
                        $sessionsLeft > 0 ? ' và đủ '.$sessionsLeft.' phiên nữa' : '',
                    );
                $resting[] = [
                    'question_id' => $questionId,
                    'reason' => 'thrash',
                    'detail' => $detail,
                    'rest_until' => $timeUntil?->toIso8601String(),
                    'wrong_streak' => $wrongStreak,
                    'sessions_since_graded' => $sessionsAfterGraded,
                    ...$memorySnapshot,
                ];

                continue;
            }

            if ($row->last_served_at !== null) {
                $servedAt = CarbonImmutable::parse($row->last_served_at);
                $hours = abs((float) $now->diffInHours($servedAt));
                if ($hours < AdaptiveLearning::COOLDOWN_HOURS) {
                    $excluded['cooldown']++;
                    $restUntil = $servedAt->addHours(AdaptiveLearning::COOLDOWN_HOURS);
                    $resting[] = [
                        'question_id' => $questionId,
                        'reason' => 'cooldown',
                        'detail' => sprintf(
                            'Vừa đưa vào phiên thích ứng %.1f giờ trước — nghỉ serve %d giờ',
                            $hours,
                            AdaptiveLearning::COOLDOWN_HOURS,
                        ),
                        'rest_until' => $restUntil->toIso8601String(),
                        'wrong_streak' => $wrongStreak,
                        'sessions_since_graded' => $sessionsAfterGraded,
                        ...$memorySnapshot,
                    ];

                    continue;
                }
            }

            $recent = is_array($row->recent_results) ? array_values($row->recent_results) : [];
            $recent = array_map(static fn ($v): bool => (bool) $v, $recent);
            if ($recent === []) {
                // Fallback Laplace từ counters lifetime nếu chưa có cửa sổ.
                $correct = (int) ($row->correct_count ?? 0);
                $wrong = (int) ($row->wrong_count ?? 0);
                for ($i = 0; $i < min(AdaptiveLearning::WEAK_WINDOW, $correct); $i++) {
                    $recent[] = true;
                }
                for ($i = 0; $i < min(AdaptiveLearning::WEAK_WINDOW - count($recent), $wrong); $i++) {
                    $recent[] = false;
                }
            }

            $eligible[$questionId] = [
                'question_id' => $questionId,
                'lesson_id' => $meta['lesson_id'],
                'recent_results' => $recent,
                'weakness' => AdaptiveLearning::weakness($recent),
                'stability' => $stability,
                'last_graded_at' => CarbonImmutable::parse($row->last_graded_at),
                'retention' => $memorySnapshot['retention'],
                'is_due' => $memorySnapshot['is_due'],
                'due_at' => $memorySnapshot['due_at'],
                'days_since' => $memorySnapshot['days_since'],
            ];
        }

        usort($resting, static fn (array $a, array $b): int => strcmp($a['question_id'], $b['question_id']));

        $this->trace('filter', [
            'stage' => 1,
            'stage_name' => 'Lọc',
            'active_count' => count($poolIds),
            'eligible_count' => count($eligible),
            'unseen_count' => count($unseen),
            'excluded' => $excluded,
            'resting' => $resting,
            'rules' => [
                'thrash',
                'cooldown_adaptive_20h',
                'content_version',
            ],
        ]);

        // ─── 2. Phân nhóm ──────────────────────────────────────
        $weakPool = array_values(array_filter(
            $eligible,
            static fn (array $r): bool => $r['weakness'] >= AdaptiveLearning::WEAK_THRESHOLD,
        ));
        usort($weakPool, static function (array $a, array $b): int {
            return $b['weakness'] <=> $a['weakness']
                ?: ($a['retention'] ?? 1) <=> ($b['retention'] ?? 1)
                ?: strcmp($a['question_id'], $b['question_id']);
        });

        $duePool = array_values(array_filter(
            $eligible,
            static fn (array $r): bool => $r['is_due'],
        ));
        usort($duePool, static function (array $a, array $b): int {
            return ($a['retention'] ?? 1) <=> ($b['retention'] ?? 1)
                ?: $b['weakness'] <=> $a['weakness']
                ?: strcmp($a['question_id'], $b['question_id']);
        });

        $overlap = count(array_intersect(
            array_column($weakPool, 'question_id'),
            array_column($duePool, 'question_id'),
        ));

        $this->trace('group', [
            'stage' => 2,
            'stage_name' => 'Phân nhóm',
            'weak_pool' => count($weakPool),
            'due_pool' => count($duePool),
            'unseen_count' => count($unseen),
            'overlap_weak_due' => $overlap,
            'rules' => [
                'weak_W_ge_0.5_window_5',
                'due_t_ge_S',
                'new_unseen',
            ],
        ]);

        // ─── 3. Phân suất ──────────────────────────────────────
        $quota = AdaptiveLearning::newQuestionQuota(
            count($duePool),
            count($unseen),
            count($eligible),
            $limit,
        );
        $newCount = $quota['count'];
        $reviewSlots = $limit - $newCount;

        $weakQuota = match ($focus) {
            'weak_focus' => $reviewSlots,
            'retention' => 0,
            default => (int) ceil($reviewSlots / 2),
        };
        $dueQuota = $reviewSlots - $weakQuota;

        $this->trace('quota', [
            'stage' => 3,
            'stage_name' => 'Phân suất',
            'due_band' => $quota['band'],
            'new_share' => $quota['share'],
            'new_count' => $newCount,
            'review_slots' => $reviewSlots,
            'weak_quota' => $weakQuota,
            'due_quota' => $dueQuota,
            'focus' => $focus,
            'rules' => [
                'new_share_30_20_10_by_due',
                'mode_splits_review_only',
            ],
        ]);

        $taken = [];
        $items = [];

        $takeReview = function (array $pool, int $count, string $bucket) use (&$taken, &$items, $now): void {
            if ($count <= 0) {
                return;
            }
            $avail = array_values(array_filter(
                $pool,
                static fn (array $r): bool => ! isset($taken[$r['question_id']]),
            ));
            foreach ($this->pickTop($avail, $count) as $row) {
                $taken[$row['question_id']] = true;
                $reason = $bucket === 'yeu'
                    ? sprintf(
                        'Sai %d/%d lần gần nhất (độ yếu %d%%)',
                        count(array_filter($row['recent_results'], static fn (bool $r): bool => ! $r)),
                        count($row['recent_results']),
                        (int) round($row['weakness'] * 100),
                    )
                    : sprintf(
                        'Đã %d ngày chưa ôn, khả năng ghi nhớ còn %d%%',
                        (int) floor($row['days_since']),
                        (int) round(($row['retention'] ?? 0) * 100),
                    );
                $items[] = [
                    'question_id' => $row['question_id'],
                    'bucket' => $bucket,
                    'reason' => $reason,
                    'lesson_id' => $row['lesson_id'],
                    'weakness' => $row['weakness'],
                    'retention' => $row['retention'],
                    'days_since' => $row['days_since'],
                    'stability' => $row['stability'],
                    'is_due' => $row['is_due'],
                    'due_at' => $row['due_at'],
                ];
            }
        };

        if ($focus === 'retention') {
            $takeReview($duePool, $dueQuota, 'sap_quen');
            $takeReview($weakPool, $reviewSlots - count($items), 'yeu');
        } else {
            $takeReview($weakPool, $weakQuota, 'yeu');
            $weakShort = $weakQuota - count(array_filter($items, static fn (array $i): bool => $i['bucket'] === 'yeu'));
            // weakShort already reflected if takeReview got fewer; use remaining review slots.
            $takeReview($duePool, $dueQuota + max(0, $weakQuota - count($items)), 'sap_quen');
            $takeReview($weakPool, $reviewSlots - count($items), 'yeu');
        }

        $this->trace('pick_review', [
            'taken' => array_map(static fn (array $i): array => [
                'question_id' => $i['question_id'],
                'bucket' => $i['bucket'],
                'reason' => $i['reason'],
            ], array_values(array_filter($items, static fn (array $i): bool => in_array($i['bucket'], ['yeu', 'sap_quen'], true)))),
        ]);

        // ─── Câu mới: bài dang dở trước ────────────────────────
        $exposure = [];
        foreach ($eligible as $row) {
            $lid = $row['lesson_id'] ?? 0;
            if ($lid) {
                $exposure[$lid] = ($exposure[$lid] ?? 0) + 1;
            }
        }

        $unseenByLesson = [];
        $lessonNames = [];
        $shuffledUnseen = $unseen;
        shuffle($shuffledUnseen);
        foreach ($shuffledUnseen as $qid) {
            $lid = (int) ($metaById[$qid]['lesson_id'] ?? 0);
            $unseenByLesson[$lid][] = $qid;
            $name = $metaById[$qid]['lesson_name'] ?? null;
            if (is_string($name) && $name !== '' && ! isset($lessonNames[$lid])) {
                $lessonNames[$lid] = $name;
            }
        }

        $newSlots = $limit - count($items);
        $pickedNew = [];
        for ($i = 0; $i < $newSlots && $unseenByLesson !== []; $i++) {
            $bestLesson = null;
            $bestScore = -1.0;
            foreach ($unseenByLesson as $lessonId => $list) {
                $seenIn = $exposure[$lessonId] ?? 0;
                $inProgress = $seenIn > 0 ? 1 : 0;
                $weight = 1.0; // ma trận chi tiết có thể gắn CCT weight sau
                $score = $inProgress * 1_000_000_000 + $weight / (1 + $seenIn);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestLesson = $lessonId;
                }
            }
            $list = $unseenByLesson[$bestLesson];
            $qid = array_shift($list);
            if ($list === []) {
                unset($unseenByLesson[$bestLesson]);
            } else {
                $unseenByLesson[$bestLesson] = $list;
            }
            $wasInProgress = ($exposure[$bestLesson] ?? 0) > 0;
            $exposure[$bestLesson] = ($exposure[$bestLesson] ?? 0) + 1;
            $taken[$qid] = true;
            $lessonName = $lessonNames[(int) $bestLesson] ?? null;
            $lessonLabel = is_string($lessonName) && $lessonName !== ''
                ? $lessonName
                : ('bài #'.((string) $bestLesson));
            $item = [
                'question_id' => $qid,
                'bucket' => 'moi',
                'reason' => $wasInProgress
                    ? 'Câu mới — tiếp tục bài đang học: '.$lessonLabel
                    : 'Câu mới — bài học mới: '.$lessonLabel,
                'lesson_id' => $bestLesson,
                'lesson_name' => is_string($lessonName) ? $lessonName : null,
                'weakness' => null,
                'retention' => null,
                'days_since' => null,
                'stability' => null,
                'is_due' => null,
                'due_at' => null,
            ];
            $items[] = $item;
            $pickedNew[] = $item;
        }

        $this->trace('pick_new', ['taken' => $pickedNew]);

        // ─── Lấp đầy ───────────────────────────────────────────
        if (count($items) < $limit) {
            $rest = array_values(array_filter(
                $eligible,
                static fn (array $r): bool => ! isset($taken[$r['question_id']]),
            ));
            usort($rest, static function (array $a, array $b): int {
                return ($a['retention'] ?? 1) <=> ($b['retention'] ?? 1)
                    ?: strcmp($a['question_id'], $b['question_id']);
            });
            foreach (array_slice($rest, 0, $limit - count($items)) as $row) {
                $taken[$row['question_id']] = true;
                $items[] = [
                    'question_id' => $row['question_id'],
                    'bucket' => 'lap_day',
                    'reason' => sprintf('Ôn thêm, khả năng ghi nhớ còn %d%%', (int) round(($row['retention'] ?? 0) * 100)),
                    'lesson_id' => $row['lesson_id'],
                    'weakness' => $row['weakness'],
                    'retention' => $row['retention'],
                    'days_since' => $row['days_since'],
                    'stability' => $row['stability'],
                    'is_due' => $row['is_due'],
                    'due_at' => $row['due_at'],
                ];
            }
        }

        $shortfall = $limit - count($items);
        $ordered = $items;
        // Giữ thứ tự bucket để debug dễ; vẫn xáo nhẹ trong từng nhóm? — shuffle toàn phiên như V1 để UX.
        shuffle($ordered);

        $resultItems = [];
        foreach (array_values($ordered) as $index => $item) {
            $resultItems[] = [
                'question_id' => $item['question_id'],
                'position' => $index + 1,
                'bucket' => $item['bucket'],
                'reason' => $item['reason'],
                'lesson_id' => $item['lesson_id'],
                'lesson_name' => $item['lesson_name'] ?? null,
                'weakness' => $item['weakness'],
                'retention' => $item['retention'],
                'days_since' => $item['days_since'],
                'stability' => $item['stability'],
                'is_due' => $item['is_due'],
                'due_at' => $item['due_at'],
            ];
        }

        $bucketCounts = ['yeu' => 0, 'sap_quen' => 0, 'moi' => 0, 'lap_day' => 0];
        foreach ($resultItems as $item) {
            $bucketCounts[$item['bucket']] = ($bucketCounts[$item['bucket']] ?? 0) + 1;
        }

        $this->trace('result', [
            'focus' => $focus,
            'pipeline' => AdaptiveLearning::PIPELINE,
            'picked_count' => count($resultItems),
            'bucket_counts' => $bucketCounts,
            'shortfall' => $shortfall,
            'message' => $shortfall > 0
                ? sprintf(
                    'Hôm nay bạn đã ôn hết %d câu phù hợp. Quay lại sau %d giờ, hoặc mở rộng chủ đề để luyện thêm.',
                    count($resultItems),
                    AdaptiveLearning::COOLDOWN_HOURS,
                )
                : null,
            'items' => $resultItems,
            'question_ids' => array_column($resultItems, 'question_id'),
            // Tương thích briefing cũ một phần
            'picked_unseen' => $bucketCounts['moi'],
            'picked_review' => $bucketCounts['yeu'] + $bucketCounts['sap_quen'] + $bucketCounts['lap_day'],
        ]);

        return array_column($resultItems, 'question_id');
    }

    public function countPool(int $userId, bool $canUsePremium, CreateSessionData $data): int
    {
        return count($this->poolQuestionIds($userId, $canUsePremium, $data, trace: false));
    }

    /**
     * @param  list<array<string, mixed>>  $ranked
     * @return list<array<string, mixed>>
     */
    private function pickTop(array $ranked, int $count): array
    {
        if ($count <= 0 || $ranked === []) {
            return [];
        }
        $window = array_slice($ranked, 0, max($count, (int) ceil(AdaptiveLearning::DIVERSITY_FACTOR * $count)));
        shuffle($window);
        $chosenIds = array_flip(array_column(array_slice($window, 0, $count), 'question_id'));

        return array_values(array_filter(
            $ranked,
            static fn (array $r): bool => isset($chosenIds[$r['question_id']]),
        ));
    }

    /**
     * @param  array<int, string>  $poolIds
     * @return array<string, array{content_version: int, lesson_id: int, lesson_name: string|null}>
     */
    private function loadQuestionMeta(array $poolIds): array
    {
        if ($poolIds === []) {
            return [];
        }

        $questions = Question::query()
            ->whereIn('id', $poolIds)
            ->with(['lessons:id,name'])
            ->get(['id', 'published_version']);

        $meta = [];
        foreach ($questions as $question) {
            $lesson = $question->lessons->sortBy('id')->first();
            $lessonId = (int) ($lesson?->getKey() ?? 0);
            $meta[(string) $question->getKey()] = [
                'content_version' => (int) ($question->published_version ?? 0),
                'lesson_id' => $lessonId,
                'lesson_name' => $lesson !== null ? (string) $lesson->name : null,
            ];
        }

        return $meta;
    }

    /**
     * @param  list<CarbonImmutable>  $sessionTimes
     */
    private function sessionsAfter(array $sessionTimes, mixed $after): int
    {
        if ($after === null) {
            return PHP_INT_MAX;
        }
        $anchor = CarbonImmutable::parse($after);
        $count = 0;
        foreach ($sessionTimes as $created) {
            if ($created->greaterThan($anchor)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, string>
     */
    private function poolQuestionIds(int $userId, bool $canUsePremium, CreateSessionData $data, bool $trace = true): array
    {
        [$lessonIds, $lessonScope] = $this->poolLessonIds($data);

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
            professionId: $this->learnerProfessionId($userId),
            examCatalogId: $data->examCatalogId,
        );

        $ids = $query->pluck('id')->map(fn ($id) => (string) $id)->all();

        if ($trace) {
            $this->trace('pool', [
                'scope' => $lessonScope,
                'lesson_ids_count' => count($lessonIds),
                'pool_size' => count($ids),
                'blueprint_id' => $data->blueprintId,
                'organ_system_ids' => $data->organSystemIds,
                'subject_ids' => $data->subjectIds,
                'premium_only_free' => ! $canUsePremium,
            ]);
        }

        return $ids;
    }

    /**
     * @return array{0: array<int, int>, 1: string}
     */
    private function poolLessonIds(CreateSessionData $data): array
    {
        $scopedLessonIds = $this->filters->resolveContentLessonIds(
            $data->organSystemIds,
            $data->subjectIds,
            [],
        );

        if (
            $scopedLessonIds === []
            && $this->filters->hasContentFilter($data->organSystemIds, $data->subjectIds, [])
        ) {
            return [[], 'empty_content_filter'];
        }

        if ($scopedLessonIds === []) {
            return [[], $data->blueprintId !== null ? 'blueprint_membership' : 'all'];
        }

        return [
            $scopedLessonIds,
            $data->blueprintId !== null ? 'blueprint_and_content' : 'organ_subject',
        ];
    }

    private function normalizeFocus(?string $focus): string
    {
        $focus = $focus ?: 'balanced';

        return in_array($focus, ['weak_focus', 'balanced', 'retention'], true) ? $focus : 'balanced';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function trace(string $step, array $context = []): void
    {
        AdaptiveTrace::write($step, $context);
    }

    private function learnerProfessionId(int $userId): ?int
    {
        $professionId = LearnerProfile::query()->where('user_id', $userId)->value('profession_id');

        return $professionId === null ? null : (int) $professionId;
    }
}
