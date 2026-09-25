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
use Modules\QuestionBank\Models\QuestionFlagChangeEvent;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;
use Modules\QuestionBank\Support\QuestionWorkflowRecorder;

/**
 * Conflict tab: reaffirm or change own flag (with responsibility ack).
 * Status only changes when the pair becomes 2 green or 2 red.
 */
final class ChangeReviewerFlagInConflictAction
{
    public function __construct(
        private readonly QuestionReviewerFlagCycle $flagCycle,
        private readonly QuestionWorkflowRecorder $workflowRecorder,
    ) {}

    public function handle(
        User $reviewer,
        Question $question,
        ReviewerFlag $flag,
        ?string $note = null,
        bool $responsibilityAcked = false,
    ): Question {
        abort_unless(
            $reviewer->can(Permission::QuestionFlag->value),
            403,
            'Bạn không có quyền gắn cờ.',
        );

        $note = trim(strip_tags((string) $note));
        $note = $note !== '' ? mb_substr($note, 0, 2000) : null;

        return DB::transaction(function () use ($reviewer, $question, $flag, $note, $responsibilityAcked): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());

            if ($question->status !== QuestionStatus::FlagConflict) {
                throw ValidationException::withMessages([
                    'flag' => 'Chỉ đổi cờ được khi câu đang ở tab Cảnh báo.',
                ]);
            }

            if (! $this->flagCycle->actorHasFlagged($question, $reviewer)) {
                throw ValidationException::withMessages([
                    'flag' => 'Bạn không phải reviewer của câu này.',
                ]);
            }

            $before = AuditSnapshot::question($question);
            $fromFlag = $this->flagCycle->actorFlag($question, $reviewer);
            $versionBefore = (int) $question->version;

            $this->flagCycle->changeFlagInConflict(
                $question,
                $reviewer,
                $flag,
                $note,
                $responsibilityAcked,
            );

            $question = $question->refresh();
            $this->applyPairOutcome($question, $reviewer);
            $question = $question->refresh();

            if ((int) $question->version !== $versionBefore) {
                throw ValidationException::withMessages([
                    'version' => 'Đổi cờ reviewer không được tăng version.',
                ]);
            }

            Auditor::record(
                AuditAction::QuestionReviewerFlagged,
                $reviewer,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'from_status' => QuestionStatus::FlagConflict->value,
                    'to_status' => $question->status->value,
                    'from_flag' => $fromFlag?->value,
                    'to_flag' => $flag->value,
                    'reaffirmed' => $fromFlag === $flag,
                    'ack_text_version' => QuestionFlagChangeEvent::ACK_TEXT_VERSION,
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

            $notes = collect([
                $question->reviewer_1_note,
                $question->reviewer_2_note,
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

        // Still conflict (reaffirm or cross-swap) — status unchanged.
        $question->forceFill([
            'updated_by' => $actor->getKey(),
        ])->save();
    }
}
