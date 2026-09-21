<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\InstructorReviewOutcome;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
use Modules\QuestionBank\Models\QuestionVersion;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;

/**
 * Builds a review timeline grouped by published version (+ current working copy).
 *
 * @phpstan-type TimelineEntry array{
 *   type: string,
 *   label: string,
 *   actor_name: string|null,
 *   actor_role: string|null,
 *   note: string|null,
 *   tone: string,
 *   outcome_label: string|null,
 *   occurred_at: Carbon|null,
 *   qa: array{
 *     kind: 'instructor'|'flag',
 *     id: int,
 *     current: string|null,
 *     current_label: string|null,
 *     note: string|null,
 *     locked: bool,
 *     options: list<array{value: string, label: string}>
 *   }|null,
 *   meta: array<string, mixed>
 * }
 * @phpstan-type TimelineCycle array{
 *   cycle: int,
 *   display_cycle: int,
 *   rejects: int,
 *   entries: list<TimelineEntry>
 * }
 * @phpstan-type TimelineSegment array{
 *   key: string,
 *   kind: 'current'|'published',
 *   version: int|null,
 *   title: string,
 *   status_label: string,
 *   status_tone: string,
 *   summary: string,
 *   cycle_count: int,
 *   reject_count: int,
 *   published_at: Carbon|null,
 *   publisher_name: string|null,
 *   is_open_default: bool,
 *   is_qbank_live: bool,
 *   cycles: list<TimelineCycle>
 * }
 */
final class QuestionReviewTimeline
{
    /**
     * @return array{
     *   current_cycle: int,
     *   pipeline_reject_count: int,
     *   total_rejects: int,
     *   cycles: list<TimelineCycle>,
     *   segments: list<TimelineSegment>
     * }
     */
    public function build(Question $question): array
    {
        $question->loadMissing([
            'instructorReviews.instructor:id,name',
            'reviewerFlags.reviewer:id,name',
            'workflowEvents.actor:id,name',
            'versions' => fn ($q) => $q->orderByDesc('version'),
            'publisher:id,name',
        ]);

        /** @var Collection<int, TimelineEntry> $flat */
        $flat = collect();

        foreach ($question->instructorReviews as $review) {
            $flat->push($this->fromInstructorReview($review));
        }

        foreach ($question->reviewerFlags as $flag) {
            $flat->push($this->fromReviewerFlag($flag));
        }

        foreach ($question->workflowEvents as $event) {
            $flat->push($this->fromWorkflowEvent($event));
        }

        $grouped = $flat
            ->sortBy(fn (array $entry): int => $entry['occurred_at']?->getTimestamp() ?? 0)
            ->groupBy(fn (array $entry): int => (int) ($entry['meta']['review_cycle'] ?? 0));

        /** @var array<int, TimelineCycle> $cyclesByNumber */
        $cyclesByNumber = [];
        $totalRejects = 0;

        foreach ($grouped->sortKeys() as $cycle => $entries) {
            $cycleNum = (int) $cycle;
            if ($cycleNum < 1) {
                continue;
            }

            $cycleRejects = $entries->filter(fn (array $e): bool => in_array($e['type'], [
                'instructor_reject',
                'admin_reject',
            ], true))->count();
            $totalRejects += $cycleRejects;

            $cyclesByNumber[$cycleNum] = [
                'cycle' => $cycleNum,
                'display_cycle' => $cycleNum,
                'rejects' => $cycleRejects,
                'entries' => $entries->values()->all(),
            ];
        }

        $segments = $this->buildSegments($question, $cyclesByNumber);

        // Flat list (newest first) — kept for tests / legacy consumers.
        $cyclesNewestFirst = array_values(array_reverse($cyclesByNumber, true));

        return [
            'current_cycle' => (int) $question->instructor_review_cycle,
            'pipeline_reject_count' => (int) $question->pipeline_reject_count,
            'total_rejects' => $totalRejects,
            'cycles' => $cyclesNewestFirst,
            'segments' => $segments,
        ];
    }

    /**
     * Snapshot metadata stored on publish versions.
     *
     * @return array{
     *   review_cycle: int,
     *   pipeline_reject_count: int,
     *   instructor_id: int|null,
     *   instructor_name: string|null,
     *   publisher_id: int|null,
     *   publisher_name: string|null,
     *   flags: list<array{reviewer_id: int|null, reviewer_name: string|null, flag: string|null, note: string|null}>
     * }
     */
    public function publishMeta(Question $question): array
    {
        $question->loadMissing([
            'instructor:id,name',
            'assignedInstructor:id,name',
            'publisher:id,name',
            'reviewerSlot1:id,name',
            'reviewerSlot2:id,name',
        ]);

        $flags = [];
        foreach ([1, 2] as $slot) {
            $flag = $question->{"reviewer_{$slot}_flag"};
            $flagValue = $flag instanceof ReviewerFlag ? $flag->value : (is_string($flag) ? $flag : null);
            $relation = $slot === 1 ? $question->reviewerSlot1 : $question->reviewerSlot2;
            $flags[] = [
                'reviewer_id' => $question->{"reviewer_{$slot}_id"} ? (int) $question->{"reviewer_{$slot}_id"} : null,
                'reviewer_name' => $relation?->name,
                'flag' => $flagValue,
                'note' => filled($question->{"reviewer_{$slot}_note"})
                    ? (string) $question->{"reviewer_{$slot}_note"}
                    : null,
            ];
        }

        $instructor = $question->instructor ?? $question->assignedInstructor;

        return [
            'review_cycle' => (int) $question->instructor_review_cycle,
            'pipeline_reject_count' => (int) $question->pipeline_reject_count,
            'instructor_id' => $instructor?->getKey() ? (int) $instructor->getKey() : null,
            'instructor_name' => $instructor?->name,
            'publisher_id' => $question->publisher_id ? (int) $question->publisher_id : null,
            'publisher_name' => $question->publisher?->name,
            'flags' => $flags,
        ];
    }

    /**
     * @param  array<int, TimelineCycle>  $cyclesByNumber
     * @return list<TimelineSegment>
     */
    private function buildSegments(Question $question, array $cyclesByNumber): array
    {
        $publishBounds = $this->publishCycleBounds($question);
        $segments = [];

        $lastPublishCycle = 0;
        foreach ($publishBounds as $bound) {
            $lastPublishCycle = max($lastPublishCycle, $bound['end_cycle']);
        }

        $status = $question->status instanceof QuestionStatus
            ? $question->status
            : QuestionStatus::tryFrom((string) $question->status);

        $inTerminal = in_array($status, [
            QuestionStatus::Published,
            QuestionStatus::Private,
            QuestionStatus::Retired,
        ], true);

        $currentCycleNumbers = array_values(array_filter(
            array_keys($cyclesByNumber),
            fn (int $cycle): bool => $cycle > $lastPublishCycle,
        ));

        $showCurrent = ! $inTerminal || $currentCycleNumbers !== [];

        $liveQbankVersion = (int) ($question->published_version ?? 0);
        $qbankIsServing = $liveQbankVersion > 0
            && $status !== QuestionStatus::Retired;

        if ($showCurrent) {
            $currentCycles = $this->cyclesForRange(
                $cyclesByNumber,
                $lastPublishCycle + 1,
                PHP_INT_MAX,
            );
            $rejectCount = array_sum(array_column($currentCycles, 'rejects'));
            $cycleCount = count($currentCycles);

            $segments[] = [
                'key' => 'current',
                'kind' => 'current',
                'version' => null,
                'title' => 'Bản hiện tại',
                'status_label' => $status?->label() ?? 'Đang làm việc',
                'status_tone' => $this->statusTone($status),
                'summary' => $this->segmentSummary($cycleCount, $rejectCount, isCurrent: true),
                'cycle_count' => $cycleCount,
                'reject_count' => $rejectCount,
                'published_at' => null,
                'publisher_name' => null,
                'is_open_default' => true, // Bản làm việc = mới nhất khi đang soạn/duyệt
                'is_qbank_live' => false,
                'cycles' => $currentCycles,
            ];
        }

        // Published versions newest-first for reading order.
        foreach (array_reverse($publishBounds) as $index => $bound) {
            $versionCycles = $this->cyclesForRange(
                $cyclesByNumber,
                $bound['start_cycle'],
                $bound['end_cycle'],
            );
            $rejectCount = array_sum(array_column($versionCycles, 'rejects'));
            $cycleCount = count($versionCycles);
            $versionNum = $bound['version'];
            $isQbankLive = $qbankIsServing && $versionNum === $liveQbankVersion;

            $segments[] = [
                'key' => 'v'.$versionNum,
                'kind' => 'published',
                'version' => $versionNum,
                'title' => 'Phiên bản '.$versionNum,
                'status_label' => $isQbankLive ? 'Bản đang dùng' : 'Không còn phục vụ',
                'status_tone' => $isQbankLive ? 'green' : 'neutral',
                'summary' => $this->segmentSummary($cycleCount, $rejectCount, isCurrent: false),
                'cycle_count' => $cycleCount,
                'reject_count' => $rejectCount,
                'published_at' => $bound['published_at'],
                'publisher_name' => $bound['publisher_name'],
                // Chỉ mở mặc định phiên bản mới nhất; nếu có bản làm việc thì ưu tiên bản đó.
                'is_open_default' => ! $showCurrent && $index === 0,
                'is_qbank_live' => $isQbankLive,
                'cycles' => $versionCycles,
            ];
        }

        return $segments;
    }

    /**
     * @return list<array{
     *   version: int,
     *   start_cycle: int,
     *   end_cycle: int,
     *   published_at: Carbon|null,
     *   publisher_name: string|null
     * }>
     */
    private function publishCycleBounds(Question $question): array
    {
        $publishEvents = $question->workflowEvents
            ->filter(function (QuestionWorkflowEvent $event): bool {
                $type = $event->event_type instanceof QuestionWorkflowEventType
                    ? $event->event_type
                    : QuestionWorkflowEventType::tryFrom((string) $event->event_type);

                return $type === QuestionWorkflowEventType::Publish
                    && (int) $event->published_version >= 1;
            })
            ->sortBy(fn (QuestionWorkflowEvent $event): int => (int) $event->published_version)
            ->values();

        /** @var Collection<int, QuestionVersion> $versions */
        $versions = $question->relationLoaded('versions')
            ? $question->versions->keyBy(fn (QuestionVersion $v): int => (int) $v->version)
            : collect();

        $bounds = [];
        $prevEnd = 0;

        foreach ($publishEvents as $event) {
            $versionNum = (int) $event->published_version;
            $endCycle = max(1, (int) $event->review_cycle);

            // Prefer snapshot pipeline cycle when present (authoritative at publish).
            $version = $versions->get($versionNum);
            $pipelineCycle = (int) ($version?->snapshot['review_pipeline']['review_cycle'] ?? 0);
            if ($pipelineCycle > 0) {
                $endCycle = $pipelineCycle;
            }

            $startCycle = $prevEnd + 1;
            if ($startCycle > $endCycle) {
                $startCycle = $endCycle;
            }

            $publisherName = $event->actor?->name
                ?? (is_string($version?->snapshot['review_pipeline']['publisher_name'] ?? null)
                    ? (string) $version->snapshot['review_pipeline']['publisher_name']
                    : null);

            $bounds[] = [
                'version' => $versionNum,
                'start_cycle' => $startCycle,
                'end_cycle' => $endCycle,
                'published_at' => $event->occurred_at ?? $event->created_at ?? $version?->created_at,
                'publisher_name' => $publisherName,
            ];

            $prevEnd = $endCycle;
        }

        // Fallback: versions table without workflow publish events.
        if ($bounds === [] && $versions->isNotEmpty()) {
            foreach ($versions->sortBy(fn (QuestionVersion $v): int => (int) $v->version) as $version) {
                $versionNum = (int) $version->version;
                $endCycle = (int) ($version->snapshot['review_pipeline']['review_cycle'] ?? $versionNum);
                $endCycle = max(1, $endCycle);
                $startCycle = $prevEnd + 1;
                if ($startCycle > $endCycle) {
                    $startCycle = $endCycle;
                }

                $bounds[] = [
                    'version' => $versionNum,
                    'start_cycle' => $startCycle,
                    'end_cycle' => $endCycle,
                    'published_at' => $version->created_at,
                    'publisher_name' => is_string($version->snapshot['review_pipeline']['publisher_name'] ?? null)
                        ? (string) $version->snapshot['review_pipeline']['publisher_name']
                        : null,
                ];
                $prevEnd = $endCycle;
            }
        }

        return $bounds;
    }

    /**
     * @param  array<int, TimelineCycle>  $cyclesByNumber
     * @return list<TimelineCycle>
     */
    private function cyclesForRange(array $cyclesByNumber, int $start, int $end): array
    {
        $selected = [];
        foreach ($cyclesByNumber as $cycle => $payload) {
            if ($cycle >= $start && $cycle <= $end) {
                $selected[$cycle] = $payload;
            }
        }

        // Chronological within the segment (oldest = display vòng 1).
        ksort($selected, SORT_NUMERIC);
        $display = 1;
        foreach ($selected as $cycle => $payload) {
            $selected[$cycle]['display_cycle'] = $display;
            $display++;
        }

        // Newest cycle first for reading order.
        krsort($selected, SORT_NUMERIC);

        return array_values($selected);
    }

    private function segmentSummary(int $cycleCount, int $rejectCount, bool $isCurrent): string
    {
        if ($cycleCount === 0) {
            return $isCurrent
                ? 'Chưa có vòng duyệt trong bản làm việc này'
                : 'Không ghi nhận vòng duyệt';
        }

        $parts = [$cycleCount.' vòng duyệt'];
        if ($rejectCount > 0) {
            $parts[] = $rejectCount.' lần từ chối';
        }

        return implode(' · ', $parts);
    }

    private function statusTone(?QuestionStatus $status): string
    {
        return match ($status) {
            QuestionStatus::Published, QuestionStatus::Private => 'green',
            QuestionStatus::Rejected, QuestionStatus::Retired => 'red',
            QuestionStatus::PendingPublish => 'amber',
            QuestionStatus::InFlagReview => 'sky',
            QuestionStatus::InReview => 'violet',
            default => 'neutral',
        };
    }

    /**
     * @return TimelineEntry
     */
    private function fromInstructorReview(QuestionInstructorReview $review): array
    {
        $rejected = $review->decision === InstructorReviewDecision::Rejected;

        $outcome = $review->outcome instanceof InstructorReviewOutcome
            ? $review->outcome
            : InstructorReviewOutcome::tryFrom((string) $review->outcome);

        $qaOptions = $rejected
            ? [
                ['value' => InstructorReviewOutcome::Confirmed->value, 'label' => 'Duyệt đúng'],
                ['value' => InstructorReviewOutcome::OverReject->value, 'label' => 'Duyệt sai'],
                ['value' => InstructorReviewOutcome::Pending->value, 'label' => 'Chưa đánh giá'],
            ]
            : [
                ['value' => InstructorReviewOutcome::Confirmed->value, 'label' => 'Duyệt đúng'],
                ['value' => InstructorReviewOutcome::Miss->value, 'label' => 'Duyệt sai'],
                ['value' => InstructorReviewOutcome::Pending->value, 'label' => 'Chưa đánh giá'],
            ];

        return [
            'type' => $rejected ? 'instructor_reject' : 'instructor_accept',
            'label' => $rejected ? 'Giảng viên từ chối' : 'Giảng viên duyệt chuyên môn',
            'actor_name' => $review->instructor?->name,
            'actor_role' => 'instructor',
            'note' => filled($review->note) ? (string) $review->note : null,
            'tone' => $rejected ? 'red' : 'green',
            'outcome_label' => $outcome && $outcome !== InstructorReviewOutcome::Pending
                ? $outcome->label()
                : null,
            'occurred_at' => $review->reviewed_at ?? $review->created_at,
            'qa' => [
                'kind' => 'instructor',
                'id' => (int) $review->getKey(),
                'current' => $outcome?->value ?? InstructorReviewOutcome::Pending->value,
                'current_label' => $outcome && $outcome !== InstructorReviewOutcome::Pending
                    ? $outcome->label()
                    : null,
                'note' => filled($review->outcome_note) ? (string) $review->outcome_note : null,
                'locked' => $outcome !== null && $outcome !== InstructorReviewOutcome::Pending,
                'options' => $qaOptions,
            ],
            'meta' => [
                'review_cycle' => (int) $review->review_cycle,
                'decision' => $review->decision instanceof InstructorReviewDecision
                    ? $review->decision->value
                    : (string) $review->decision,
                'outcome' => $outcome?->value,
                'record_id' => (int) $review->getKey(),
            ],
        ];
    }

    /**
     * @return TimelineEntry
     */
    private function fromReviewerFlag(QuestionReviewerFlag $flag): array
    {
        $value = $flag->flag instanceof ReviewerFlag ? $flag->flag : ReviewerFlag::tryFrom((string) $flag->flag);
        $isRed = $value === ReviewerFlag::Red;
        $outcome = $flag->outcome instanceof ReviewFlagOutcome
            ? $flag->outcome
            : ReviewFlagOutcome::tryFrom((string) $flag->outcome);

        return [
            'type' => $isRed ? 'flag_red' : 'flag_green',
            'label' => $isRed ? 'Reviewer gắn cờ đỏ' : 'Reviewer gắn cờ xanh',
            'actor_name' => $flag->reviewer?->name,
            'actor_role' => 'reviewer',
            'note' => filled($flag->note) ? (string) $flag->note : null,
            'tone' => $isRed ? 'red' : 'green',
            'outcome_label' => $outcome && $outcome !== ReviewFlagOutcome::Pending
                ? $outcome->label()
                : null,
            'occurred_at' => $flag->reviewed_at ?? $flag->created_at,
            'qa' => [
                'kind' => 'flag',
                'id' => (int) $flag->getKey(),
                'current' => $outcome?->value ?? ReviewFlagOutcome::Pending->value,
                'current_label' => $outcome && $outcome !== ReviewFlagOutcome::Pending
                    ? $outcome->label()
                    : null,
                'note' => filled($flag->outcome_note) ? (string) $flag->outcome_note : null,
                'locked' => $outcome !== null && $outcome !== ReviewFlagOutcome::Pending,
                'options' => [
                    ['value' => ReviewFlagOutcome::Confirmed->value, 'label' => 'Gắn đúng'],
                    ['value' => ReviewFlagOutcome::FalsePositive->value, 'label' => 'Gắn sai'],
                    ['value' => ReviewFlagOutcome::Pending->value, 'label' => 'Chưa đánh giá'],
                ],
            ],
            'meta' => [
                'review_cycle' => (int) $flag->review_cycle,
                'flag' => $value?->value,
                'outcome' => $outcome?->value,
                'record_id' => (int) $flag->getKey(),
            ],
        ];
    }

    /**
     * @return TimelineEntry
     */
    private function fromWorkflowEvent(QuestionWorkflowEvent $event): array
    {
        $type = $event->event_type instanceof QuestionWorkflowEventType
            ? $event->event_type
            : QuestionWorkflowEventType::tryFrom((string) $event->event_type);

        $tone = match ($type) {
            QuestionWorkflowEventType::AdminReject => 'red',
            QuestionWorkflowEventType::Publish => 'primary',
            default => 'neutral',
        };

        $label = $type?->label() ?? 'Sự kiện';
        if ($type === QuestionWorkflowEventType::Publish && (int) $event->published_version >= 1) {
            $label = 'Xuất bản phiên bản '.(int) $event->published_version;
        }

        return [
            'type' => $type?->value ?? 'unknown',
            'label' => $label,
            'actor_name' => $event->actor?->name,
            'actor_role' => $event->actor_role,
            'note' => filled($event->note) ? (string) $event->note : null,
            'tone' => $tone,
            'outcome_label' => null,
            'occurred_at' => $event->occurred_at ?? $event->created_at,
            'qa' => null,
            'meta' => array_merge((array) $event->meta, [
                'review_cycle' => (int) $event->review_cycle,
                'published_version' => $event->published_version,
            ]),
        ];
    }
}
