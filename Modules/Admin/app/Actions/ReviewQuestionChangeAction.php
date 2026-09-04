<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Html\SafeHtml;
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

final class ReviewQuestionChangeAction
{
    public function __construct(
        private readonly TransitionQuestionStatusAction $transitionStatus,
    ) {}

    public function approve(User $reviewer, QuestionReviewRequest $reviewRequest, ?string $note = null): Question
    {
        abort_unless(QuestionAccess::canPublish($reviewer), 403);

        if ($reviewRequest->action === QuestionReviewAction::Create) {
            throw ValidationException::withMessages([
                'review' => 'Admin không duyệt thay giảng viên. Câu hỏi mới phải do giảng viên duyệt trên cổng /teach, sau đó admin mới xuất bản.',
            ]);
        }

        return DB::transaction(function () use ($reviewer, $reviewRequest, $note): Question {
            $reviewRequest = QuestionReviewRequest::query()->lockForUpdate()->findOrFail($reviewRequest->getKey());
            $this->assertPending($reviewRequest);

            $question = Question::withTrashed()->lockForUpdate()->findOrFail($reviewRequest->question_id);
            $before = AuditSnapshot::question($question);

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
                AuditAction::QuestionReviewApproved,
                $reviewer,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'review_request_id' => $reviewRequest->getKey(),
                    'review_action' => $reviewRequest->action->value,
                    'review_note' => $this->cleanNote($note),
                ],
            );

            return $question;
        });
    }

    public function reject(User $reviewer, QuestionReviewRequest $reviewRequest, ?string $note = null): Question
    {
        abort_unless(QuestionAccess::canPublish($reviewer), 403);

        if ($reviewRequest->action === QuestionReviewAction::Create) {
            throw ValidationException::withMessages([
                'review' => 'Admin không từ chối thay giảng viên. Hãy để giảng viên xử lý trên cổng /teach.',
            ]);
        }

        return DB::transaction(function () use ($reviewer, $reviewRequest, $note): Question {
            $reviewRequest = QuestionReviewRequest::query()->lockForUpdate()->findOrFail($reviewRequest->getKey());
            $this->assertPending($reviewRequest);

            $question = Question::withTrashed()->lockForUpdate()->findOrFail($reviewRequest->question_id);
            $before = AuditSnapshot::question($question);

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
                AuditAction::QuestionReviewRejected,
                $reviewer,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'review_request_id' => $reviewRequest->getKey(),
                    'review_action' => $reviewRequest->action->value,
                    'review_note' => $this->cleanNote($note),
                ],
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
        unset($reviewer, $question, $reviewRequest);

        throw ValidationException::withMessages([
            'review' => 'Không duyệt thay đổi nội dung tại đây. Mọi create/edit phải do giảng viên duyệt trên /teach, sau đó admin xuất bản để tăng phiên bản.',
        ]);
    }

    private function approveDeletion(User $reviewer, Question $question): void
    {
        Auditor::record(
            AuditAction::QuestionDeleted,
            $reviewer,
            $question,
            AuditSnapshot::question($question),
            null,
            metadata: ['source' => 'review_approval'],
        );
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
