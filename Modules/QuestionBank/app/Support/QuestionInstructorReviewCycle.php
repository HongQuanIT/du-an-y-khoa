<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;

/**
 * Layer 1: one assigned instructor approves or rejects (fail-fast on reject).
 * Legacy dual-slot approvals remain valid for already-queued pending_publish rows.
 */
final class QuestionInstructorReviewCycle
{
    public const REQUIRED_APPROVALS = 1;

    public function __construct(
        private readonly QuestionReviewerFlagCycle $flagCycle,
    ) {}

    public function startOrReset(Question $question): void
    {
        $question->forceFill([
            'instructor_review_cycle' => (int) $question->instructor_review_cycle + 1,
            'instructor_decision' => null,
            'instructor_note' => null,
            'instructor_reviewed_at' => null,
            'instructor_id' => $question->assigned_instructor_id,
            'instructor_1_id' => null,
            'instructor_1_decision' => null,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
            'rejection_reason' => null,
            'rejected_by_role' => null,
        ])->save();

        $this->flagCycle->clearFlags($question->fresh() ?? $question);
    }

    public function clearSlots(Question $question): void
    {
        $question->forceFill([
            'instructor_decision' => null,
            'instructor_note' => null,
            'instructor_reviewed_at' => null,
            'instructor_id' => $question->assigned_instructor_id,
            'instructor_1_id' => null,
            'instructor_1_decision' => null,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
        ])->save();

        $this->flagCycle->clearFlags($question->fresh() ?? $question);
    }

    public function recordApproval(Question $question, User $instructor, ?string $note = null): int
    {
        $this->assertCanVote($question, $instructor);

        $this->writeReview($question, $instructor, InstructorReviewDecision::Approved, $note);

        $question->forceFill([
            'instructor_id' => $instructor->getKey(),
            'instructor_decision' => InstructorReviewDecision::Approved->value,
            'instructor_note' => $note,
            'instructor_reviewed_at' => now(),
            'rejection_reason' => null,
            'rejected_by_role' => null,
        ])->save();

        return 1;
    }

    public function recordRejection(Question $question, User $instructor, string $reason): void
    {
        $this->assertCanVote($question, $instructor);

        $this->writeReview($question, $instructor, InstructorReviewDecision::Rejected, $reason);

        $question->forceFill([
            'instructor_id' => $instructor->getKey(),
            'instructor_decision' => InstructorReviewDecision::Rejected->value,
            'instructor_note' => $reason,
            'instructor_reviewed_at' => now(),
        ])->save();
    }

    public function instructorApproved(Question $question): bool
    {
        return $question->instructor_decision === InstructorReviewDecision::Approved->value
            || $question->instructor_decision === InstructorReviewDecision::Approved;
    }

    /**
     * Ready for Admin publish: assigned GV approved + 2 flags, or legacy 2-instructor accepts.
     */
    public function hasRequiredApprovals(Question $question): bool
    {
        if ($this->instructorApproved($question) && $this->flagCycle->hasRequiredFlags($question)) {
            return true;
        }

        if ($this->approvedCountFromSlots($question) >= 2) {
            return true;
        }

        return (int) $question->instructor_review_cycle === 0
            && $question->instructor_id !== null
            && in_array($question->status, [
                QuestionStatus::PendingPublish,
                QuestionStatus::Published,
                QuestionStatus::Private,
            ], true);
    }

    public function canPublish(Question $question): bool
    {
        if ($this->flagCycle->hasRedFlag($question)) {
            return false;
        }

        return $this->hasRequiredApprovals($question);
    }

    /**
     * @return list<int>
     */
    public function blockedPublisherIds(Question $question): array
    {
        $ids = $this->approvedInstructorIds($question);
        if ($question->assigned_instructor_id) {
            $ids[] = (int) $question->assigned_instructor_id;
        }
        if ($question->instructor_id) {
            $ids[] = (int) $question->instructor_id;
        }

        return array_values(array_unique(array_merge($ids, $this->flagCycle->reviewerIds($question))));
    }

    /**
     * @return list<int>
     */
    public function approvedInstructorIds(Question $question): array
    {
        $ids = [];
        foreach ([1, 2] as $slot) {
            $id = $question->{"instructor_{$slot}_id"};
            $decision = $question->{"instructor_{$slot}_decision"};
            if ($id && $decision === InstructorReviewDecision::Approved->value) {
                $ids[] = (int) $id;
            }
        }

        if ($this->instructorApproved($question) && $question->instructor_id) {
            $ids[] = (int) $question->instructor_id;
        }

        if ($ids === [] && $question->instructor_id) {
            $ids[] = (int) $question->instructor_id;
        }

        return array_values(array_unique($ids));
    }

    public function actorHasDecided(Question $question, User $actor): bool
    {
        $actorId = (int) $actor->getKey();

        if ((int) $question->assigned_instructor_id === $actorId && $question->instructor_decision !== null) {
            return true;
        }

        return ((int) $question->instructor_1_id === $actorId && $question->instructor_1_decision !== null)
            || ((int) $question->instructor_2_id === $actorId && $question->instructor_2_decision !== null);
    }

    public function approvedCountFromSlots(Question $question): int
    {
        if ($this->instructorApproved($question)) {
            return 1;
        }

        $count = 0;
        foreach ([1, 2] as $slot) {
            if ($question->{"instructor_{$slot}_decision"} === InstructorReviewDecision::Approved->value) {
                $count++;
            }
        }

        return $count;
    }

    private function assertCanVote(Question $question, User $instructor): void
    {
        if ((int) $question->created_by === (int) $instructor->getKey()) {
            throw ValidationException::withMessages([
                'status' => 'Người soạn không được tự duyệt câu hỏi của mình.',
            ]);
        }

        $assignedId = (int) $question->assigned_instructor_id;
        if ($assignedId > 0 && $assignedId !== (int) $instructor->getKey()) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ giảng viên được gán mới duyệt câu hỏi này.',
            ]);
        }

        if ($this->actorHasDecided($question, $instructor)) {
            throw ValidationException::withMessages([
                'status' => 'Bạn đã gửi phiếu duyệt cho vòng này.',
            ]);
        }

        $exists = QuestionInstructorReview::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', (int) $question->instructor_review_cycle)
            ->where('instructor_id', $instructor->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'status' => 'Bạn đã gửi phiếu duyệt cho vòng này.',
            ]);
        }
    }

    private function writeReview(
        Question $question,
        User $instructor,
        InstructorReviewDecision $decision,
        ?string $note,
    ): void {
        QuestionInstructorReview::query()->create([
            'question_id' => $question->getKey(),
            'review_cycle' => (int) $question->instructor_review_cycle,
            'instructor_id' => $instructor->getKey(),
            'decision' => $decision,
            'note' => $note,
            'content_fingerprint' => $question->content_fingerprint,
            'reviewed_at' => now(),
        ]);
    }
}
