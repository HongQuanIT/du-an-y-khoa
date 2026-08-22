<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;

final class ReviewQuestionChangeAction
{
    public function __construct(
        private readonly SaveAdminQuestionAction $saveQuestion,
        private readonly TransitionQuestionStatusAction $transitionStatus,
    ) {}

    public function approve(User $reviewer, QuestionReviewRequest $reviewRequest, ?string $note = null): Question
    {
        abort_unless(QuestionAccess::isReviewer($reviewer), 403);

        return DB::transaction(function () use ($reviewer, $reviewRequest, $note): Question {
            $reviewRequest = QuestionReviewRequest::query()->lockForUpdate()->findOrFail($reviewRequest->getKey());
            $this->assertPending($reviewRequest);

            $question = Question::withTrashed()->lockForUpdate()->findOrFail($reviewRequest->question_id);

            match ($reviewRequest->action) {
                QuestionReviewAction::Create => $this->approveCreation($reviewer, $question),
                QuestionReviewAction::Update => $this->approveUpdate($reviewer, $question, $reviewRequest),
                QuestionReviewAction::Delete => $this->approveDeletion($reviewer, $question),
            };

            $reviewRequest->forceFill([
                'status' => QuestionReviewStatus::Approved,
                'reviewed_by' => $reviewer->getKey(),
                'review_note' => $this->cleanNote($note),
                'reviewed_at' => now(),
            ])->save();

            Auditor::record(
                'admin.question.review_approved',
                $reviewer,
                $question,
                ['review_request_id' => $reviewRequest->getKey()],
                ['action' => $reviewRequest->action->value],
            );

            return $question;
        });
    }

    public function reject(User $reviewer, QuestionReviewRequest $reviewRequest, ?string $note = null): Question
    {
        abort_unless(QuestionAccess::isReviewer($reviewer), 403);

        return DB::transaction(function () use ($reviewer, $reviewRequest, $note): Question {
            $reviewRequest = QuestionReviewRequest::query()->lockForUpdate()->findOrFail($reviewRequest->getKey());
            $this->assertPending($reviewRequest);

            $question = Question::withTrashed()->lockForUpdate()->findOrFail($reviewRequest->question_id);

            if ($reviewRequest->action === QuestionReviewAction::Create && $question->status === QuestionStatus::InReview) {
                $question->forceFill(['status' => QuestionStatus::Draft])->save();
            }

            $reviewRequest->forceFill([
                'status' => QuestionReviewStatus::Rejected,
                'reviewed_by' => $reviewer->getKey(),
                'review_note' => $this->cleanNote($note),
                'reviewed_at' => now(),
            ])->save();

            Auditor::record(
                'admin.question.review_rejected',
                $reviewer,
                $question,
                ['review_request_id' => $reviewRequest->getKey()],
                ['action' => $reviewRequest->action->value, 'note' => $this->cleanNote($note)],
            );

            return $question;
        });
    }

    private function approveCreation(User $reviewer, Question $question): void
    {
        $this->fillLegacyGeneralExplanation($question);

        if ($question->status !== QuestionStatus::Published) {
            $this->transitionStatus->handle($reviewer, $question, QuestionStatus::Published);
        }
    }

    private function fillLegacyGeneralExplanation(Question $question): void
    {
        if (! SafeHtml::isBlank($question->explanation)) {
            return;
        }

        $question->loadMissing('options');
        $correctOption = $question->options->firstWhere('is_correct', true);

        if ($correctOption === null || SafeHtml::isBlank($correctOption->explanation)) {
            return;
        }

        $question->forceFill([
            'explanation' => SafeHtml::fromEditor($correctOption->explanation),
        ])->save();
    }

    private function approveUpdate(User $reviewer, Question $question, QuestionReviewRequest $reviewRequest): void
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $reviewRequest->payload;
        if ($payload === null) {
            throw ValidationException::withMessages(['review' => 'Yêu cầu chỉnh sửa không có dữ liệu.']);
        }

        $this->saveQuestion->handle($reviewer, $question, $payload);
    }

    private function approveDeletion(User $reviewer, Question $question): void
    {
        Auditor::record('admin.question.delete', $reviewer, $question);
        $question->delete();
    }

    private function assertPending(QuestionReviewRequest $reviewRequest): void
    {
        if ($reviewRequest->status !== QuestionReviewStatus::Pending) {
            throw ValidationException::withMessages(['review' => 'Yêu cầu này đã được xử lý.']);
        }
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim(strip_tags((string) $note));

        return $note !== '' ? mb_substr($note, 0, 2000) : null;
    }
}
