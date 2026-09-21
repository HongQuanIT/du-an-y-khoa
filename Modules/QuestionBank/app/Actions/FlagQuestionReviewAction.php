<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;

/**
 * Layer 1b: two reviewers flag green/red. Second green OR any red → pending_publish.
 */
final class FlagQuestionReviewAction
{
    public function __construct(
        private readonly QuestionReviewerFlagCycle $flagCycle,
    ) {}

    public function handle(User $reviewer, Question $question, ReviewerFlag $flag, ?string $note = null): Question
    {
        abort_unless(
            $reviewer->can(Permission::QuestionFlag->value),
            403,
            'Bạn không có quyền gắn cờ.',
        );

        $note = trim(strip_tags((string) $note));
        $note = $note !== '' ? mb_substr($note, 0, 2000) : null;

        if ($flag->requiresNote() && $note === null) {
            throw ValidationException::withMessages([
                'note' => 'Cờ đỏ bắt buộc phải ghi chú lý do.',
            ]);
        }

        return DB::transaction(function () use ($reviewer, $question, $flag, $note): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());

            if ($question->status !== QuestionStatus::InFlagReview) {
                throw ValidationException::withMessages([
                    'flag' => 'Chỉ gắn cờ được câu đang chờ reviewer.',
                ]);
            }

            $before = AuditSnapshot::question($question);
            $versionBefore = (int) $question->version;

            $count = $this->flagCycle->recordFlag($question, $reviewer, $flag, $note);
            $question = $question->refresh();

            // Fail-fast on red: Admin must return to editor. Two greens → ready to publish.
            $escalate = $count >= QuestionReviewerFlagCycle::REQUIRED_FLAGS
                || $this->flagCycle->hasRedFlag($question);

            if ($escalate) {
                $question->forceFill([
                    'status' => QuestionStatus::PendingPublish,
                    'updated_by' => $reviewer->getKey(),
                ])->save();
            } else {
                $question->forceFill([
                    'updated_by' => $reviewer->getKey(),
                ])->save();
            }

            $question = $question->refresh();

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
                    'from_status' => QuestionStatus::InFlagReview->value,
                    'to_status' => $question->status->value,
                    'flag' => $flag->value,
                    'review_note' => $note,
                    'flag_count' => $count,
                    'has_red_flag' => $this->flagCycle->hasRedFlag($question),
                ],
            );

            return $question;
        });
    }
}
