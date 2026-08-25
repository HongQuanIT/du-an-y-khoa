<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;

final class RequestQuestionDeletionAction
{
    public function handle(User $actor, Question $question): void
    {
        DB::transaction(function () use ($actor, $question): void {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());
            $before = AuditSnapshot::question($question);

            if (QuestionAccess::isReviewer($actor)) {
                Auditor::record(AuditAction::QuestionDeleted, $actor, $question, $before, null);
                $question->delete();

                return;
            }

            if ($question->reviewRequests()->where('status', QuestionReviewStatus::Pending->value)->exists()) {
                throw ValidationException::withMessages([
                    'review' => 'Câu hỏi đang chờ duyệt một yêu cầu khác.',
                ]);
            }

            $reviewRequest = QuestionReviewRequest::query()->create([
                'question_id' => $question->getKey(),
                'action' => QuestionReviewAction::Delete,
                'status' => QuestionReviewStatus::Pending,
                'requested_by' => $actor->getKey(),
            ]);

            Auditor::record(
                AuditAction::QuestionDeleteRequested,
                $actor,
                $question,
                $before,
                $before,
                metadata: [
                    'review_request_id' => $reviewRequest->getKey(),
                    'review_action' => QuestionReviewAction::Delete->value,
                ],
            );
        });
    }
}
