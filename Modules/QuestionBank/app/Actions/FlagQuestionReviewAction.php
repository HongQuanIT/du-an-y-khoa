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
 * Layer 1b: two reviewers flag green/yellow/red. Second flag → pending_publish.
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

            if ($count >= QuestionReviewerFlagCycle::REQUIRED_FLAGS) {
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
                ],
            );

            return $question;
        });
    }
}
