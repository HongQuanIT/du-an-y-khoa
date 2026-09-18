<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\InstructorReviewOutcome;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;

/**
 * Builds a chronological review timeline grouped by review_cycle for Admin UI.
 *
 * @phpstan-type TimelineEntry array{
 *   type: string,
 *   label: string,
 *   actor_name: string|null,
 *   actor_role: string|null,
 *   note: string|null,
 *   tone: string,
 *   occurred_at: Carbon|null,
 *   meta: array<string, mixed>
 * }
 * @phpstan-type TimelineCycle array{
 *   cycle: int,
 *   rejects: int,
 *   entries: list<TimelineEntry>
 * }
 */
final class QuestionReviewTimeline
{
    /**
     * @return array{
     *   current_cycle: int,
     *   pipeline_reject_count: int,
     *   total_rejects: int,
     *   cycles: list<TimelineCycle>
     * }
     */
    public function build(Question $question): array
    {
        $question->loadMissing([
            'instructorReviews.instructor:id,name',
            'reviewerFlags.reviewer:id,name',
            'workflowEvents.actor:id,name',
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

        $cycles = [];
        $totalRejects = 0;

        foreach ($grouped->sortKeysDesc() as $cycle => $entries) {
            $cycleRejects = $entries->filter(fn (array $e): bool => in_array($e['type'], [
                'instructor_reject',
                'admin_reject',
            ], true))->count();
            $totalRejects += $cycleRejects;

            $cycles[] = [
                'cycle' => (int) $cycle,
                'rejects' => $cycleRejects,
                'entries' => $entries->values()->all(),
            ];
        }

        return [
            'current_cycle' => (int) $question->instructor_review_cycle,
            'pipeline_reject_count' => (int) $question->pipeline_reject_count,
            'total_rejects' => $totalRejects,
            'cycles' => $cycles,
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
     * @return TimelineEntry
     */
    private function fromInstructorReview(QuestionInstructorReview $review): array
    {
        $rejected = $review->decision === InstructorReviewDecision::Rejected;

        $outcome = $review->outcome instanceof InstructorReviewOutcome
            ? $review->outcome
            : InstructorReviewOutcome::tryFrom((string) $review->outcome);

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
            'meta' => [
                'review_cycle' => (int) $review->review_cycle,
                'decision' => $review->decision instanceof InstructorReviewDecision
                    ? $review->decision->value
                    : (string) $review->decision,
                'outcome' => $outcome?->value,
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
            'meta' => [
                'review_cycle' => (int) $flag->review_cycle,
                'flag' => $value?->value,
                'outcome' => $outcome?->value,
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

        return [
            'type' => $type?->value ?? 'unknown',
            'label' => $type?->label() ?? 'Sự kiện',
            'actor_name' => $event->actor?->name,
            'actor_role' => $event->actor_role,
            'note' => filled($event->note) ? (string) $event->note : null,
            'tone' => $tone,
            'occurred_at' => $event->occurred_at ?? $event->created_at,
            'meta' => array_merge((array) $event->meta, [
                'review_cycle' => (int) $event->review_cycle,
                'published_version' => $event->published_version,
            ]),
        ];
    }
}
