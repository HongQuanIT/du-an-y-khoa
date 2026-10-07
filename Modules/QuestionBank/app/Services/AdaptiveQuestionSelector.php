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
    private bool $tracing = true;

    public function __construct(
        private readonly QuestionFilterBuilder $filters,
    ) {}

    /**
     * @return array<int, string> Question UUIDs for the new session
     */
    public function pick(int $userId, int $limit, bool $canUsePremium, CreateSessionData $data): array
    {
        return $this->inspect($userId, $limit, $canUsePremium, $data)['question_ids'];
    }

    /**
     * @return array{
     *     question_ids: list<string>,
     *     can_start: bool,
     *     needs_extra_confirm: bool,
     *     message: string|null,
     *     next_ready_at: string|null,
     *     blocked_reason: string|null,
     *     available_weak: int,
     *     pickable_count: int,
     *     pool_count: int
     * }
     */
    public function inspect(int $userId, int $limit, bool $canUsePremium, CreateSessionData $data, bool $trace = true): array
    {
        $this->tracing = $trace;
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

            return $this->emptyInspect('Không còn câu hỏi phù hợp với bộ lọc đã chọn.', 0);
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
        $eligible = []; // graded hợp lệ + pass filter
        $unseen = [];   // chưa trả lời đúng/sai cho đúng content_version
        $resting = [];  // thrash / cooldown — liệt kê cho admin briefing
        $cooldownWeak = []; // câu yếu đang nghỉ serve — ứng viên luyện thêm (weak_focus)
        $cooldownDue = [];  // câu đến hạn đang nghỉ serve — ứng viên luyện thêm (retention)

        foreach ($poolIds as $questionId) {
            $meta = $metaById[$questionId] ?? null;
            if ($meta === null) {
                continue;
            }

            /** @var UserQuestionStatusModel|null $row */
            $row = $stats->get($questionId);
            $contentVersion = (int) ($meta['content_version'] ?? 0);

            $versionMismatch = $row !== null
                && $row->content_version !== null
                && (int) $row->content_version !== $contentVersion;

            $hasGraded = $row !== null
                && $row->last_graded_at !== null
                && $row->memory_stability_days !== null
                && ! $versionMismatch;

            if ($versionMismatch) {
                $excluded['version_mismatch']++;
            }

            if (! $hasGraded) {
                $answeredTooFast = ! $versionMismatch
                    && AdaptiveLearning::hasAnsweredAttempt($row?->status, $row?->last_attempt_at);
                if ($answeredTooFast) {
                    $excluded['ungraded_status']++;
                } else {
                    $unseen[] = $questionId;
                }

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
            $recentForResting = $this->recentResultsForRow($row);
            $restingWeakness = AdaptiveLearning::weakness($recentForResting);

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
                    'weakness' => $restingWeakness,
                    ...$memorySnapshot,
                ];

                continue;
            }

            if (AdaptiveLearning::isServeBlocked($row->last_served_at, $now)) {
                $excluded['cooldown']++;
                $restUntil = AdaptiveLearning::serveReadyAt($row->last_served_at);
                $resting[] = [
                    'question_id' => $questionId,
                    'reason' => 'cooldown',
                    'detail' => 'Đã đưa vào phiên thích ứng trong ngày học này — nghỉ đến ngày học kế tiếp (tối thiểu 8 giờ)',
                    'rest_until' => $restUntil->toIso8601String(),
                    'wrong_streak' => $wrongStreak,
                    'sessions_since_graded' => $sessionsAfterGraded,
                    'weakness' => $restingWeakness,
                    ...$memorySnapshot,
                ];

                $recent = $this->recentResultsForRow($row);
                $restingEligible = [
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
                if ($restingWeakness >= AdaptiveLearning::WEAK_THRESHOLD) {
                    $cooldownWeak[$questionId] = $restingEligible;
                }
                if ($memorySnapshot['is_due']) {
                    $cooldownDue[$questionId] = $restingEligible;
                }

                continue;
            }

            $recent = $this->recentResultsForRow($row);

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
                'cooldown_study_day',
                'content_version',
                'too_fast_seen_not_new',
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

        $weakResting = 0;
        $nextWeakReady = null;
        $dueResting = 0;
        $nextDueReady = null;
        foreach ($resting as $row) {
            $untilRaw = $row['rest_until'] ?? null;
            $until = is_string($untilRaw) && $untilRaw !== ''
                ? CarbonImmutable::parse($untilRaw)
                : null;

            if ((float) ($row['weakness'] ?? 0) >= AdaptiveLearning::WEAK_THRESHOLD) {
                $weakResting++;
                if ($until !== null && ($nextWeakReady === null || $until->lessThan($nextWeakReady))) {
                    $nextWeakReady = $until;
                }
            }
            if (($row['is_due'] ?? false) === true) {
                $dueResting++;
                if ($until !== null && ($nextDueReady === null || $until->lessThan($nextDueReady))) {
                    $nextDueReady = $until;
                }
            }
        }

        if ($focus === 'weak_focus' && $weakPool === []) {
            return $this->blockedFocusResult(
                focus: $focus,
                blocked: AdaptiveLearning::weakFocusBlocked($weakResting, $nextWeakReady, $now),
                extraCandidates: array_values($cooldownWeak),
                nextReady: $nextWeakReady,
                limit: $limit,
                poolCount: count($poolIds),
                extraPractice: $data->extraPractice,
                bucket: 'yeu',
                sort: static function (array $a, array $b): int {
                    return $b['weakness'] <=> $a['weakness']
                        ?: ($a['retention'] ?? 1) <=> ($b['retention'] ?? 1)
                        ?: strcmp($a['question_id'], $b['question_id']);
                },
                reason: static fn (array $row): string => sprintf(
                    'Luyện thêm — câu yếu đang nghỉ (độ yếu %d%%; độ bền không đổi)',
                    (int) round($row['weakness'] * 100),
                ),
            );
        }

        if ($focus === 'retention' && $duePool === []) {
            return $this->blockedFocusResult(
                focus: $focus,
                blocked: AdaptiveLearning::retentionFocusBlocked($dueResting, $nextDueReady, $now),
                extraCandidates: array_values($cooldownDue),
                nextReady: $nextDueReady,
                limit: $limit,
                poolCount: count($poolIds),
                extraPractice: $data->extraPractice,
                bucket: 'sap_quen',
                sort: static function (array $a, array $b): int {
                    return ($a['retention'] ?? 1) <=> ($b['retention'] ?? 1)
                        ?: $b['weakness'] <=> $a['weakness']
                        ?: strcmp($a['question_id'], $b['question_id']);
                },
                reason: static fn (array $row): string => sprintf(
                    'Luyện thêm — câu đến hạn đang nghỉ (ghi nhớ còn %d%%; độ bền không đổi)',
                    (int) round(($row['retention'] ?? 0) * 100),
                ),
            );
        }

        // ─── 3. Phân suất ──────────────────────────────────────
        $quota = AdaptiveLearning::newQuestionQuota(
            count($duePool),
            count($unseen),
            count($eligible) + $excluded['ungraded_status'],
            $limit,
        );
        $newCount = $quota['count'];
        $reviewSlots = $limit - $newCount;
        $share = (float) $quota['share'];

        $weakQuota = match ($focus) {
            'weak_focus' => $reviewSlots,
            'retention' => 0,
            default => (int) ceil($reviewSlots / 2),
        };
        $dueQuota = $reviewSlots - $weakQuota;
        $strictPrimary = in_array($focus, ['weak_focus', 'retention'], true);
        $allowFill = ! $strictPrimary;

        if ($focus === 'weak_focus') {
            $weakAvailable = count($weakPool);
            $weakQuota = min($weakAvailable, $reviewSlots);
            $dueQuota = 0;
            if ($weakQuota < $reviewSlots && $share > 0.0 && $share < 1.0) {
                $newCount = min(count($unseen), max(0, (int) round($share * $weakQuota / (1.0 - $share))));
                $reviewSlots = $weakQuota;
            } else {
                $reviewSlots = $weakQuota;
            }
        }

        if ($focus === 'retention') {
            $dueAvailable = count($duePool);
            $dueQuota = min($dueAvailable, $reviewSlots);
            $weakQuota = 0;
            if ($dueQuota < $reviewSlots && $share > 0.0 && $share < 1.0) {
                $newCount = min(count($unseen), max(0, (int) round($share * $dueQuota / (1.0 - $share))));
                $reviewSlots = $dueQuota;
            } else {
                $reviewSlots = $dueQuota;
            }
        }

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
                'weak_focus_no_due_fill',
                'retention_no_weak_fill',
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
        } elseif ($focus === 'weak_focus') {
            $takeReview($weakPool, $weakQuota, 'yeu');
        } else {
            $takeReview($weakPool, $weakQuota, 'yeu');
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

        $newSlots = $strictPrimary
            ? $newCount
            : $limit - count($items);
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
        if ($allowFill && count($items) < $limit) {
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

        $questionIds = array_column($resultItems, 'question_id');
        $resultMessage = (! $strictPrimary && $shortfall > 0)
            ? sprintf(
                'Hôm nay bạn đã ôn hết %d câu phù hợp. Hãy quay lại vào ngày mai, hoặc mở rộng chủ đề để luyện thêm.',
                count($resultItems),
            )
            : null;

        $this->trace('result', [
            'focus' => $focus,
            'pipeline' => AdaptiveLearning::PIPELINE,
            'picked_count' => count($resultItems),
            'bucket_counts' => $bucketCounts,
            'shortfall' => $strictPrimary ? 0 : $shortfall,
            'message' => $resultMessage,
            'available_weak' => count($weakPool),
            'items' => $resultItems,
            'question_ids' => $questionIds,
            'picked_unseen' => $bucketCounts['moi'],
            'picked_review' => $bucketCounts['yeu'] + $bucketCounts['sap_quen'] + $bucketCounts['lap_day'],
        ]);

        return [
            'question_ids' => $questionIds,
            'can_start' => $questionIds !== [],
            'needs_extra_confirm' => false,
            'message' => $resultMessage,
            'next_ready_at' => null,
            'blocked_reason' => null,
            'available_weak' => count($weakPool),
            'pickable_count' => count($questionIds),
            'pool_count' => count($poolIds),
        ];
    }

    /**
     * Hết nhóm chính (yếu / đến hạn): popup luyện thêm hoặc khoá khởi tạo.
     *
     * @param  list<array<string, mixed>>  $extraCandidates
     * @param  array{message: string, reason: string}  $blocked
     * @param  callable(array, array): int  $sort
     * @param  callable(array): string  $reason
     * @return array{
     *     question_ids: list<string>,
     *     can_start: bool,
     *     needs_extra_confirm: bool,
     *     message: string|null,
     *     next_ready_at: string|null,
     *     blocked_reason: string|null,
     *     available_weak: int,
     *     pickable_count: int,
     *     pool_count: int
     * }
     */
    private function blockedFocusResult(
        string $focus,
        array $blocked,
        array $extraCandidates,
        ?CarbonImmutable $nextReady,
        int $limit,
        int $poolCount,
        bool $extraPractice,
        string $bucket,
        callable $sort,
        callable $reason,
    ): array {
        if ($extraPractice && $extraCandidates !== []) {
            return $this->finishExtraFocusPractice(
                $extraCandidates,
                $limit,
                $poolCount,
                $nextReady,
                $blocked,
                $focus,
                $bucket,
                $sort,
                $reason,
            );
        }

        $canOfferExtra = $extraCandidates !== [];
        $this->trace('result', [
            'focus' => $focus,
            'pipeline' => AdaptiveLearning::PIPELINE,
            'picked_count' => 0,
            'bucket_counts' => ['yeu' => 0, 'sap_quen' => 0, 'moi' => 0, 'lap_day' => 0],
            'shortfall' => $limit,
            'message' => $blocked['message'],
            'blocked_reason' => $blocked['reason'],
            'needs_extra_confirm' => $canOfferExtra,
            'available_weak' => 0,
            'resting_extra_cooldown' => count($extraCandidates),
            'next_ready_at' => $nextReady?->toIso8601String(),
            'question_ids' => [],
            'picked_unseen' => 0,
            'picked_review' => 0,
        ]);

        return [
            'question_ids' => [],
            'can_start' => $canOfferExtra,
            'needs_extra_confirm' => $canOfferExtra,
            'message' => $blocked['message'],
            'next_ready_at' => $nextReady?->toIso8601String(),
            'blocked_reason' => $blocked['reason'],
            'available_weak' => 0,
            'pickable_count' => count($extraCandidates),
            'pool_count' => $poolCount,
        ];
    }

    /**
     * Luyện thêm: lấy ứng viên đang nghỉ cooldown (không thrash); giữ S.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  array{message: string, reason: string}  $blocked
     * @param  callable(array, array): int  $sort
     * @param  callable(array): string  $reason
     * @return array{
     *     question_ids: list<string>,
     *     can_start: bool,
     *     needs_extra_confirm: bool,
     *     message: string|null,
     *     next_ready_at: string|null,
     *     blocked_reason: string|null,
     *     available_weak: int,
     *     pickable_count: int,
     *     pool_count: int
     * }
     */
    private function finishExtraFocusPractice(
        array $candidates,
        int $limit,
        int $poolCount,
        ?CarbonImmutable $nextReady,
        array $blocked,
        string $focus,
        string $bucket,
        callable $sort,
        callable $reason,
    ): array {
        usort($candidates, $sort);

        $take = min(max(1, $limit), count($candidates));
        $picked = $this->pickTop($candidates, $take);
        $items = [];
        foreach ($picked as $row) {
            $items[] = [
                'question_id' => $row['question_id'],
                'bucket' => $bucket,
                'reason' => $reason($row),
                'lesson_id' => $row['lesson_id'],
                'weakness' => $row['weakness'],
                'retention' => $row['retention'],
                'days_since' => $row['days_since'],
                'stability' => $row['stability'],
                'is_due' => $row['is_due'],
                'due_at' => $row['due_at'],
            ];
        }

        shuffle($items);
        $resultItems = [];
        foreach (array_values($items) as $index => $item) {
            $resultItems[] = [
                'question_id' => $item['question_id'],
                'position' => $index + 1,
                'bucket' => $item['bucket'],
                'reason' => $item['reason'],
                'lesson_id' => $item['lesson_id'],
                'lesson_name' => null,
                'weakness' => $item['weakness'],
                'retention' => $item['retention'],
                'days_since' => $item['days_since'],
                'stability' => $item['stability'],
                'is_due' => $item['is_due'],
                'due_at' => $item['due_at'],
            ];
        }

        $questionIds = array_column($resultItems, 'question_id');
        $bucketCounts = ['yeu' => 0, 'sap_quen' => 0, 'moi' => 0, 'lap_day' => 0];
        $bucketCounts[$bucket] = count($resultItems);

        $this->trace('quota', [
            'stage' => 3,
            'stage_name' => 'Phân suất',
            'due_band' => 'extra',
            'new_share' => 0.0,
            'new_count' => 0,
            'review_slots' => count($questionIds),
            'weak_quota' => $bucket === 'yeu' ? count($questionIds) : 0,
            'due_quota' => $bucket === 'sap_quen' ? count($questionIds) : 0,
            'focus' => $focus,
            'extra_practice' => true,
            'rules' => ['extra_practice_cooldown_primary_only'],
        ]);
        $this->trace('result', [
            'focus' => $focus,
            'pipeline' => AdaptiveLearning::PIPELINE,
            'extra_practice' => true,
            'picked_count' => count($resultItems),
            'bucket_counts' => $bucketCounts,
            'shortfall' => 0,
            'message' => $blocked['message'],
            'blocked_reason' => $blocked['reason'],
            'needs_extra_confirm' => false,
            'available_weak' => $bucket === 'yeu' ? count($candidates) : 0,
            'items' => $resultItems,
            'question_ids' => $questionIds,
            'picked_unseen' => 0,
            'picked_review' => count($resultItems),
            'next_ready_at' => $nextReady?->toIso8601String(),
        ]);

        return [
            'question_ids' => $questionIds,
            'can_start' => $questionIds !== [],
            'needs_extra_confirm' => false,
            'message' => null,
            'next_ready_at' => $nextReady?->toIso8601String(),
            'blocked_reason' => null,
            'available_weak' => $bucket === 'yeu' ? count($candidates) : 0,
            'pickable_count' => count($questionIds),
            'pool_count' => $poolCount,
        ];
    }

    /**
     * @return array{
     *     question_ids: list<string>,
     *     can_start: bool,
     *     needs_extra_confirm: bool,
     *     message: string|null,
     *     next_ready_at: string|null,
     *     blocked_reason: string|null,
     *     available_weak: int,
     *     pickable_count: int,
     *     pool_count: int
     * }
     */
    private function emptyInspect(string $message, int $poolCount = 0): array
    {
        return [
            'question_ids' => [],
            'can_start' => false,
            'needs_extra_confirm' => false,
            'message' => $message,
            'next_ready_at' => null,
            'blocked_reason' => 'empty_pool',
            'available_weak' => 0,
            'pickable_count' => 0,
            'pool_count' => $poolCount,
        ];
    }

    /**
     * @return list<bool>
     */
    private function recentResultsForRow(UserQuestionStatusModel $row): array
    {
        $recent = is_array($row->recent_results) ? array_values($row->recent_results) : [];
        $recent = array_map(static fn ($v): bool => (bool) $v, $recent);
        if ($recent !== []) {
            return $recent;
        }

        $correct = (int) ($row->correct_count ?? 0);
        $wrong = (int) ($row->wrong_count ?? 0);
        for ($i = 0; $i < min(AdaptiveLearning::WEAK_WINDOW, $correct); $i++) {
            $recent[] = true;
        }
        for ($i = 0; $i < min(AdaptiveLearning::WEAK_WINDOW - count($recent), $wrong); $i++) {
            $recent[] = false;
        }

        return $recent;
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
        if (! $this->tracing) {
            return;
        }
        AdaptiveTrace::write($step, $context);
    }

    private function learnerProfessionId(int $userId): ?int
    {
        $professionId = LearnerProfile::query()->where('user_id', $userId)->value('profession_id');

        return $professionId === null ? null : (int) $professionId;
    }
}
