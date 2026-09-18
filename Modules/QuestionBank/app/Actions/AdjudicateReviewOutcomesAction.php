<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\InstructorReviewOutcome;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionReviewerFlag;

/**
 * Adjudicate review quality outcomes (SaaS QA).
 *
 * - Admin reject with red flags: explicit confirmed / false_positive.
 * - Publish: fingerprint heuristic for pending red flags & instructor rejects.
 */
final class AdjudicateReviewOutcomesAction
{
    /**
     * @param  'confirmed'|'false_positive'  $redFlagOutcome
     */
    public function onAdminReject(Question $question, User $actor, string $redFlagOutcome = 'confirmed'): void
    {
        $outcome = $redFlagOutcome === 'false_positive'
            ? ReviewFlagOutcome::FalsePositive
            : ReviewFlagOutcome::Confirmed;

        $cycle = (int) $question->instructor_review_cycle;

        $redFlags = QuestionReviewerFlag::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', $cycle)
            ->where('flag', ReviewerFlag::Red->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', ReviewFlagOutcome::Pending->value);
            })
            ->get();

        foreach ($redFlags as $flag) {
            $this->setFlagOutcome($flag, $outcome, $actor, 'admin', 'Admin trả về biên tập');
        }

        if ($outcome === ReviewFlagOutcome::Confirmed && $redFlags->isNotEmpty()) {
            $this->markInstructorMissForCycle($question, $cycle, $actor, 'admin');
        }
    }

    public function onPublish(Question $question, User $actor): void
    {
        $currentFingerprint = (string) ($question->content_fingerprint ?? '');

        $pendingReds = QuestionReviewerFlag::query()
            ->where('question_id', $question->getKey())
            ->where('flag', ReviewerFlag::Red->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', ReviewFlagOutcome::Pending->value);
            })
            ->get();

        foreach ($pendingReds as $flag) {
            $flagFp = (string) ($flag->content_fingerprint ?? '');
            $outcome = $this->compareFingerprints($flagFp, $currentFingerprint);
            $this->setFlagOutcome(
                $flag,
                $outcome,
                $actor,
                'auto',
                'Heuristic fingerprint khi xuất bản',
            );

            if ($outcome === ReviewFlagOutcome::Confirmed) {
                $this->markInstructorMissForCycle(
                    $question,
                    (int) $flag->review_cycle,
                    $actor,
                    'auto',
                );
            }
        }

        $pendingRejects = QuestionInstructorReview::query()
            ->where('question_id', $question->getKey())
            ->where('decision', InstructorReviewDecision::Rejected->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', InstructorReviewOutcome::Pending->value);
            })
            ->get();

        foreach ($pendingRejects as $review) {
            $reviewFp = (string) ($review->content_fingerprint ?? '');
            $outcome = match ($this->compareFingerprints($reviewFp, $currentFingerprint)) {
                ReviewFlagOutcome::Confirmed => InstructorReviewOutcome::Confirmed,
                ReviewFlagOutcome::FalsePositive => InstructorReviewOutcome::OverReject,
                default => InstructorReviewOutcome::Inconclusive,
            };
            $this->setInstructorOutcome(
                $review,
                $outcome,
                $actor,
                'auto',
                'Heuristic fingerprint khi xuất bản',
            );
        }

        // Approvals that were never disputed → confirmed on successful publish.
        QuestionInstructorReview::query()
            ->where('question_id', $question->getKey())
            ->where('decision', InstructorReviewDecision::Approved->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', InstructorReviewOutcome::Pending->value);
            })
            ->update([
                'outcome' => InstructorReviewOutcome::Confirmed->value,
                'outcome_source' => 'auto',
                'outcome_by' => $actor->getKey(),
                'outcome_at' => now(),
                'outcome_note' => 'Xuất bản thành công — không phát hiện duyệt sót',
                'updated_at' => now(),
            ]);
    }

    private function markInstructorMissForCycle(
        Question $question,
        int $cycle,
        User $actor,
        string $source,
    ): void {
        QuestionInstructorReview::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', $cycle)
            ->where('decision', InstructorReviewDecision::Approved->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhereIn('outcome', [
                        InstructorReviewOutcome::Pending->value,
                        InstructorReviewOutcome::Confirmed->value,
                    ]);
            })
            ->get()
            ->each(function (QuestionInstructorReview $review) use ($actor, $source): void {
                $this->setInstructorOutcome(
                    $review,
                    InstructorReviewOutcome::Miss,
                    $actor,
                    $source,
                    'Cờ đỏ được xác nhận sau khi GV duyệt',
                );
            });
    }

    private function compareFingerprints(string $atDecision, string $atLater): ReviewFlagOutcome
    {
        if ($atDecision === '' || $atLater === '') {
            return ReviewFlagOutcome::Inconclusive;
        }

        return $atDecision === $atLater
            ? ReviewFlagOutcome::FalsePositive
            : ReviewFlagOutcome::Confirmed;
    }

    private function setFlagOutcome(
        QuestionReviewerFlag $flag,
        ReviewFlagOutcome $outcome,
        User $actor,
        string $source,
        ?string $note,
    ): void {
        $flag->forceFill([
            'outcome' => $outcome->value,
            'outcome_source' => $source,
            'outcome_by' => $actor->getKey(),
            'outcome_at' => now(),
            'outcome_note' => $note,
        ])->save();
    }

    private function setInstructorOutcome(
        QuestionInstructorReview $review,
        InstructorReviewOutcome $outcome,
        User $actor,
        string $source,
        ?string $note,
    ): void {
        $review->forceFill([
            'outcome' => $outcome->value,
            'outcome_source' => $source,
            'outcome_by' => $actor->getKey(),
            'outcome_at' => now(),
            'outcome_note' => $note,
        ])->save();
    }
}
