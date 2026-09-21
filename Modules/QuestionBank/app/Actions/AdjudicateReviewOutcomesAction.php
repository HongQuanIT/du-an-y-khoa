<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Enums\EditorSubmitOutcome;
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
 * Adjudicate review quality outcomes (SaaS QA).
 *
 * - Admin reject with red flags: explicit confirmed / false_positive (+ editor submit).
 * - Publish: fingerprint heuristic for pending reds, instructor rejects, editor submits;
 *   undisputed greens + approvals → confirmed.
 * - Manual: Admin marks outcome on timeline for instructor / flag / editor submit.
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
            $this->markEditorOutcomeForCycle(
                $question,
                $cycle,
                EditorSubmitOutcome::NeedsRework,
                $actor,
                'admin',
                'Cờ đỏ đúng — bản gửi cần sửa',
            );
        }

        if ($outcome === ReviewFlagOutcome::FalsePositive && $redFlags->isNotEmpty()) {
            $this->markEditorOutcomeForCycle(
                $question,
                $cycle,
                EditorSubmitOutcome::Confirmed,
                $actor,
                'admin',
                'Cờ đỏ gắn sai — bản gửi của biên tập đạt',
            );
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
                $this->markEditorOutcomeForCycle(
                    $question,
                    (int) $flag->review_cycle,
                    EditorSubmitOutcome::NeedsRework,
                    $actor,
                    'auto',
                    'Cờ đỏ được xác nhận khi xuất bản',
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

            $editorOutcome = match ($outcome) {
                InstructorReviewOutcome::Confirmed => EditorSubmitOutcome::NeedsRework,
                InstructorReviewOutcome::OverReject => EditorSubmitOutcome::Confirmed,
                default => EditorSubmitOutcome::Inconclusive,
            };
            $this->markEditorOutcomeForCycle(
                $question,
                (int) $review->review_cycle,
                $editorOutcome,
                $actor,
                'auto',
                'Theo kết quả reject GV khi xuất bản',
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

        // Green flags that reached publish without dispute → confirmed (gắn đúng).
        QuestionReviewerFlag::query()
            ->where('question_id', $question->getKey())
            ->where('flag', ReviewerFlag::Green->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', ReviewFlagOutcome::Pending->value);
            })
            ->update([
                'outcome' => ReviewFlagOutcome::Confirmed->value,
                'outcome_source' => 'auto',
                'outcome_by' => $actor->getKey(),
                'outcome_at' => now(),
                'outcome_note' => 'Xuất bản thành công — cờ xanh không bị tranh chấp',
                'updated_at' => now(),
            ]);

        $pendingSubmits = QuestionWorkflowEvent::query()
            ->where('question_id', $question->getKey())
            ->where('event_type', QuestionWorkflowEventType::Submit->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', EditorSubmitOutcome::Pending->value);
            })
            ->get();

        foreach ($pendingSubmits as $event) {
            $submitFp = (string) ($event->content_fingerprint ?? '');
            $editorOutcome = $this->editorOutcomeFromFingerprints($submitFp, $currentFingerprint);
            $this->setEditorOutcome(
                $event,
                $editorOutcome,
                $actor,
                'auto',
                'Heuristic fingerprint khi xuất bản',
            );
        }
    }

    /**
     * Admin marks a single instructor decision on the timeline.
     *
     * Reject → confirmed | over_reject
     * Approve → confirmed | miss
     */
    public function manualInstructorOutcome(
        QuestionInstructorReview $review,
        User $actor,
        InstructorReviewOutcome $outcome,
        ?string $note = null,
    ): void {
        $decision = $review->decision instanceof InstructorReviewDecision
            ? $review->decision
            : InstructorReviewDecision::tryFrom((string) $review->decision);

        $allowed = match ($decision) {
            InstructorReviewDecision::Rejected => [
                InstructorReviewOutcome::Confirmed,
                InstructorReviewOutcome::OverReject,
                InstructorReviewOutcome::Pending,
            ],
            InstructorReviewDecision::Approved => [
                InstructorReviewOutcome::Confirmed,
                InstructorReviewOutcome::Miss,
                InstructorReviewOutcome::Pending,
            ],
            default => [InstructorReviewOutcome::Pending],
        };

        if (! in_array($outcome, $allowed, true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Outcome không khớp với quyết định giảng viên (approve/reject).',
            ]);
        }

        $this->setInstructorOutcome(
            $review,
            $outcome,
            $actor,
            'admin',
            $note,
        );

        $question = $review->question ?? Question::query()->find($review->question_id);
        if (! $question instanceof Question) {
            return;
        }

        $editorOutcome = match ($outcome) {
            InstructorReviewOutcome::Confirmed => $decision === InstructorReviewDecision::Rejected
                ? EditorSubmitOutcome::NeedsRework
                : EditorSubmitOutcome::Confirmed,
            InstructorReviewOutcome::OverReject => EditorSubmitOutcome::Confirmed,
            InstructorReviewOutcome::Miss => EditorSubmitOutcome::NeedsRework,
            default => null,
        };

        if ($editorOutcome !== null) {
            $this->markEditorOutcomeForCycle(
                $question,
                (int) $review->review_cycle,
                $editorOutcome,
                $actor,
                'admin',
                'Theo đánh giá QA giảng viên',
            );
        }
    }

    /**
     * Admin marks a single reviewer flag on the timeline.
     * When green is marked false_positive, optionally mark same-cycle instructor approve as miss.
     */
    public function manualFlagOutcome(
        QuestionReviewerFlag $flag,
        User $actor,
        ReviewFlagOutcome $outcome,
        ?string $note = null,
        bool $cascadeInstructorMiss = true,
    ): void {
        if (! in_array($outcome, [
            ReviewFlagOutcome::Confirmed,
            ReviewFlagOutcome::FalsePositive,
            ReviewFlagOutcome::Pending,
            ReviewFlagOutcome::Inconclusive,
        ], true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Outcome cờ không hợp lệ.',
            ]);
        }

        $this->setFlagOutcome(
            $flag,
            $outcome,
            $actor,
            'admin',
            $note,
        );

        $isGreen = ($flag->flag instanceof ReviewerFlag ? $flag->flag : ReviewerFlag::tryFrom((string) $flag->flag))
            === ReviewerFlag::Green;
        $isRed = ($flag->flag instanceof ReviewerFlag ? $flag->flag : ReviewerFlag::tryFrom((string) $flag->flag))
            === ReviewerFlag::Red;

        $question = $flag->question ?? Question::query()->find($flag->question_id);

        if ($cascadeInstructorMiss && $outcome === ReviewFlagOutcome::FalsePositive && $isGreen && $question instanceof Question) {
            $this->markInstructorMissForCycle(
                $question,
                (int) $flag->review_cycle,
                $actor,
                'admin',
            );
            $this->markEditorOutcomeForCycle(
                $question,
                (int) $flag->review_cycle,
                EditorSubmitOutcome::NeedsRework,
                $actor,
                'admin',
                'Cờ xanh gắn sai — nội dung còn thiếu sót',
            );
        }

        if ($question instanceof Question && $isRed) {
            if ($outcome === ReviewFlagOutcome::Confirmed) {
                $this->markEditorOutcomeForCycle(
                    $question,
                    (int) $flag->review_cycle,
                    EditorSubmitOutcome::NeedsRework,
                    $actor,
                    'admin',
                    'Cờ đỏ đúng — bản gửi cần sửa',
                );
            }
            if ($outcome === ReviewFlagOutcome::FalsePositive) {
                $this->markEditorOutcomeForCycle(
                    $question,
                    (int) $flag->review_cycle,
                    EditorSubmitOutcome::Confirmed,
                    $actor,
                    'admin',
                    'Cờ đỏ gắn sai — bản gửi của biên tập đạt',
                );
            }
        }
    }

    public function manualEditorOutcome(
        QuestionWorkflowEvent $event,
        User $actor,
        EditorSubmitOutcome $outcome,
        ?string $note = null,
    ): void {
        $type = $event->event_type instanceof QuestionWorkflowEventType
            ? $event->event_type
            : QuestionWorkflowEventType::tryFrom((string) $event->event_type);

        if ($type !== QuestionWorkflowEventType::Submit) {
            throw ValidationException::withMessages([
                'kind' => 'Chỉ đánh giá QA trên sự kiện gửi duyệt của biên tập viên.',
            ]);
        }

        if (! in_array($outcome, [
            EditorSubmitOutcome::Confirmed,
            EditorSubmitOutcome::NeedsRework,
            EditorSubmitOutcome::Pending,
            EditorSubmitOutcome::Inconclusive,
        ], true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Outcome biên tập không hợp lệ.',
            ]);
        }

        $this->setEditorOutcome($event, $outcome, $actor, 'admin', $note);
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

    private function markEditorOutcomeForCycle(
        Question $question,
        int $cycle,
        EditorSubmitOutcome $outcome,
        User $actor,
        string $source,
        ?string $note,
    ): void {
        QuestionWorkflowEvent::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', $cycle)
            ->where('event_type', QuestionWorkflowEventType::Submit->value)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', EditorSubmitOutcome::Pending->value);
            })
            ->get()
            ->each(function (QuestionWorkflowEvent $event) use ($outcome, $actor, $source, $note): void {
                $this->setEditorOutcome($event, $outcome, $actor, $source, $note);
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

    private function editorOutcomeFromFingerprints(string $atSubmit, string $atPublish): EditorSubmitOutcome
    {
        if ($atSubmit === '' || $atPublish === '') {
            return EditorSubmitOutcome::Inconclusive;
        }

        // Same content published → editor submit stood; different → that revision needed rework.
        return $atSubmit === $atPublish
            ? EditorSubmitOutcome::Confirmed
            : EditorSubmitOutcome::NeedsRework;
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

    private function setEditorOutcome(
        QuestionWorkflowEvent $event,
        EditorSubmitOutcome $outcome,
        User $actor,
        string $source,
        ?string $note,
    ): void {
        $event->forceFill([
            'outcome' => $outcome->value,
            'outcome_source' => $source,
            'outcome_by' => $actor->getKey(),
            'outcome_at' => now(),
            'outcome_note' => $note,
        ])->save();
    }
}
