<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\QuestionBank\Enums\QuestionRejectReasonCode;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;
use Modules\QuestionBank\Support\ReviewerChecklist;
use Modules\QuestionBank\Support\QuestionWorkflowRecorder;

/**
 * Layer 1b: two reviewers flag green/red independently.
 * After both flags: 2 green → pending_publish; 2 red → auto rejected (sticky);
 * conflict → flag_conflict.
 */
final class FlagQuestionReviewAction
{
    public function __construct(
        private readonly QuestionReviewerFlagCycle $flagCycle,
        private readonly QuestionWorkflowRecorder $workflowRecorder,
    ) {}

    /**
     * @param  list<string>  $failedChecks
     */
    public function handle(
        User $reviewer,
        Question $question,
        ReviewerFlag $flag,
        ?string $note = null,
        array $failedChecks = [],
    ): Question
    {
        abort_unless(
            $reviewer->can(Permission::QuestionFlag->value),
            403,
            'Bạn không có quyền gắn cờ.',
        );

        $note = trim(strip_tags((string) $note));
        $note = $note !== '' ? mb_substr($note, 0, 2000) : null;

        $failedChecks = $flag->requiresFailedCheck() ? ReviewerChecklist::only($failedChecks) : [];

        if ($flag->requiresFailedCheck() && $failedChecks === []) {
            throw ValidationException::withMessages([
                'failed_checks' => 'Không đạt cần đánh dấu ít nhất một mục checklist.',
            ]);
        }

        return DB::transaction(function () use ($reviewer, $question, $flag, $note, $failedChecks): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());

            if ($question->status !== QuestionStatus::InFlagReview) {
                throw ValidationException::withMessages([
                    'flag' => 'Chỉ gắn cờ được câu đang chờ reviewer.',
                ]);
            }

            $before = AuditSnapshot::question($question);
            $versionBefore = (int) $question->version;
            $fromStatus = $question->status;

            $count = $this->flagCycle->recordFlag($question, $reviewer, $flag, $note, $failedChecks);
            $question = $question->refresh();

            if ($count >= QuestionReviewerFlagCycle::REQUIRED_FLAGS) {
                $this->applyPairOutcome($question, $reviewer);
                $question = $question->refresh();
            } else {
                $question->forceFill([
                    'updated_by' => $reviewer->getKey(),
                ])->save();
                $question = $question->refresh();
            }

            if ((int) $question->version !== $versionBefore) {
                throw ValidationException::withMessages([
                    'version' => 'Gắn cờ reviewer không được tăng version.',
                ]);
            }

            Auditor::record(
                AuditAction::QuestionReviewerFlagged,
                $reviewer,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'from_status' => $fromStatus->value,
                    'to_status' => $question->status->value,
                    'flag' => $flag->value,
                    'review_note' => $note,
                    'flag_count' => $count,
                    'both_green' => $this->flagCycle->bothGreen($question),
                    'both_red' => $this->flagCycle->bothRed($question),
                    'conflict' => $this->flagCycle->isConflictPair($question),
                ],
            );

            return $question;
        });
    }

    private function applyPairOutcome(Question $question, User $actor): void
    {
        if ($this->flagCycle->bothGreen($question)) {
            $question->forceFill([
                'status' => QuestionStatus::PendingPublish,
                'rejection_reason' => null,
                'rejected_by_role' => null,
                'reject_reason_code' => null,
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->flagCycle->clearStickyPair($question->refresh());

            return;
        }

        if ($this->flagCycle->bothRed($question)) {
            $this->flagCycle->setStickyPair($question);
            $question = $question->refresh();

            $failedTitles = ReviewerChecklist::titles(
                $question->reviewerFlags()
                    ->where('review_cycle', (int) $question->instructor_review_cycle)
                    ->get()
                    ->flatMap(fn ($row): array => (array) ($row->failed_checks ?? []))
                    ->all(),
            );
            $notes = collect([
                $question->reviewer_1_note,
                $question->reviewer_2_note,
                $failedTitles !== [] ? 'Mục không đạt: '.implode(', ', $failedTitles) : null,
            ])->filter()->implode("\n---\n");

            $question->forceFill([
                'status' => QuestionStatus::Rejected,
                'rejection_reason' => $notes !== ''
                    ? $notes
                    : 'Hai reviewer gắn cờ đỏ — hệ thống trả về biên tập.',
                'rejected_by_role' => 'system',
                'reject_reason_code' => QuestionRejectReasonCode::DualRed->value,
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->workflowRecorder->record(
                $question->refresh(),
                QuestionWorkflowEventType::DualRedReject,
                $actor,
                note: $question->rejection_reason,
                actorRole: 'system',
                meta: [
                    'sticky_reviewer_1_id' => $question->sticky_reviewer_1_id,
                    'sticky_reviewer_2_id' => $question->sticky_reviewer_2_id,
                ],
            );
            $this->workflowRecorder->bumpRejectCount($question->refresh());

            return;
        }

        $question->forceFill([
            'status' => QuestionStatus::FlagConflict,
            'updated_by' => $actor->getKey(),
        ])->save();
    }
}
