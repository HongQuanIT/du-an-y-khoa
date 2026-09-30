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
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;

final class RequestQuestionRetirementAction
{
    public function handle(User $actor, Question $question): void
    {
        if (QuestionAccess::canPublish($actor)) {
            throw ValidationException::withMessages([
                'review' => 'Admin ngừng dùng trực tiếp từ trạng thái câu hỏi.',
            ]);
        }

        DB::transaction(function () use ($actor, $question): void {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());
            $before = AuditSnapshot::question($question);

            if (! in_array($question->status, [QuestionStatus::Published, QuestionStatus::Private], true)) {
                throw ValidationException::withMessages([
                    'review' => 'Chỉ yêu cầu ngừng dùng câu đang xuất bản hoặc đang ẩn khỏi ngân hàng.',
                ]);
            }

            if ($question->reviewRequests()->where('status', QuestionReviewStatus::Pending->value)->exists()) {
                throw ValidationException::withMessages([
                    'review' => 'Câu hỏi đang chờ duyệt một yêu cầu khác.',
                ]);
            }

            $reviewRequest = QuestionReviewRequest::query()->create([
                'question_id' => $question->getKey(),
                'action' => QuestionReviewAction::Retire,
                'status' => QuestionReviewStatus::Pending,
                'requested_by' => $actor->getKey(),
            ]);

            Auditor::record(
                AuditAction::QuestionRetireRequested,
                $actor,
                $question,
                $before,
                $before,
                metadata: [
                    'review_request_id' => $reviewRequest->getKey(),
                    'review_action' => QuestionReviewAction::Retire->value,
                ],
            );
        });
    }
}
