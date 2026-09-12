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
 * Dual medical review: two distinct instructors, fail-fast on first reject.
 */
final class QuestionInstructorReviewCycle
{
    public const REQUIRED_APPROVALS = 2;

    public function startOrReset(Question $question): void
    {
        $question->forceFill([
            'instructor_review_cycle' => (int) $question->instructor_review_cycle + 1,
            'instructor_1_id' => null,
            'instructor_1_decision' => null,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
            'instructor_id' => null,
            'rejection_reason' => null,
            'rejected_by_role' => null,
        ])->save();
    }

    public function clearSlots(Question $question): void
    {
        $question->forceFill([
            'instructor_1_id' => null,
            'instructor_1_decision' => null,
            'instructor_2_id' => null,
            'instructor_2_decision' => null,
            'instructor_id' => null,
        ])->save();
    }

    public function recordApproval(Question $question, User $instructor, ?string $note = null): int
    {
        $this->assertCanVote($question, $instructor);

        $this->writeReview($question, $instructor, InstructorReviewDecision::Approved, $note);
        $this->fillNextSlot($question, $instructor, InstructorReviewDecision::Approved);

        $question->forceFill([
            'instructor_id' => $instructor->getKey(),
            'rejection_reason' => null,
            'rejected_by_role' => null,
        ])->save();

        return $this->approvedCountFromSlots($question->fresh());
    }

    public function recordRejection(Question $question, User $instructor, string $reason): void
    {
        $this->assertCanVote($question, $instructor);

        $this->writeReview($question, $instructor, InstructorReviewDecision::Rejected, $reason);
        $this->fillNextSlot($question, $instructor, InstructorReviewDecision::Rejected);

        $question->forceFill([
            'instructor_id' => $instructor->getKey(),
        ])->save();
    }

    public function hasRequiredApprovals(Question $question): bool
    {
        if ($this->approvedCountFromSlots($question) >= self::REQUIRED_APPROVALS) {
            return true;
        }

        // Cycle 0 = dữ liệu cũ (1 GV). Chỉ cho xuất bản nếu đã ở hàng đợi lớp 2.
        return (int) $question->instructor_review_cycle === 0
            && $question->instructor_id !== null
            && in_array($question->status, [
                QuestionStatus::PendingPublish,
                QuestionStatus::Published,
                QuestionStatus::Private,
            ], true);
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

        if ($ids === [] && $question->instructor_id) {
            $ids[] = (int) $question->instructor_id;
        }

        return array_values(array_unique($ids));
    }

    public function actorHasDecided(Question $question, User $actor): bool
    {
        $actorId = (int) $actor->getKey();

        return ((int) $question->instructor_1_id === $actorId && $question->instructor_1_decision !== null)
            || ((int) $question->instructor_2_id === $actorId && $question->instructor_2_decision !== null);
    }

    public function approvedCountFromSlots(Question $question): int
    {
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

    private function fillNextSlot(
        Question $question,
        User $instructor,
        InstructorReviewDecision $decision,
    ): void {
        if ($question->instructor_1_id === null) {
            $question->forceFill([
                'instructor_1_id' => $instructor->getKey(),
                'instructor_1_decision' => $decision->value,
            ])->save();

            return;
        }

        if ($question->instructor_2_id === null) {
            $question->forceFill([
                'instructor_2_id' => $instructor->getKey(),
                'instructor_2_decision' => $decision->value,
            ])->save();

            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Đã đủ 2 phiếu giảng viên cho vòng này.',
        ]);
    }
}
