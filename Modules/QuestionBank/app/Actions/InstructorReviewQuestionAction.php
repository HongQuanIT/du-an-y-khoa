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
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;

/**
 * Layer-1 instructor review: in_review → pending_publish | rejected.
 * Does not bump version or publish to Qbank.
 */
final class InstructorReviewQuestionAction
{
    public function approve(User $instructor, Question $question, ?string $note = null): Question
    {
        $this->authorize($instructor);

        return DB::transaction(function () use ($instructor, $question, $note): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());

            if ($question->status !== QuestionStatus::InReview) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ duyệt được câu đang chờ giảng viên.',
                ]);
            }

            $before = AuditSnapshot::question($question);
            $versionBefore = (int) $question->version;

            $question->forceFill([
                'status' => QuestionStatus::PendingPublish,
                'instructor_id' => $instructor->getKey(),
                'reviewer_id' => $instructor->getKey(),
                'rejection_reason' => null,
                'rejected_by_role' => null,
                'updated_by' => $instructor->getKey(),
            ])->save();

            $this->resolvePendingCreateRequest(
                $question,
                $instructor,
                QuestionReviewStatus::Approved,
                $note,
            );

            $question = $question->refresh();

            if ((int) $question->version !== $versionBefore) {
                throw ValidationException::withMessages([
                    'version' => 'Duyệt giảng viên không được tăng version.',
                ]);
            }

            Auditor::record(
                AuditAction::QuestionInstructorApproved,
                $instructor,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'from_status' => QuestionStatus::InReview->value,
                    'to_status' => QuestionStatus::PendingPublish->value,
                    'review_note' => $this->cleanNote($note),
                ],
            );

            return $question;
        });
    }

    public function reject(User $instructor, Question $question, string $reason): Question
    {
        $this->authorize($instructor);

        $reason = trim(strip_tags($reason));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'review_note' => 'Vui lòng nhập lý do từ chối.',
            ]);
        }

        return DB::transaction(function () use ($instructor, $question, $reason): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());

            if ($question->status !== QuestionStatus::InReview) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ từ chối được câu đang chờ giảng viên.',
                ]);
            }

            $before = AuditSnapshot::question($question);
            $versionBefore = (int) $question->version;

            $question->forceFill([
                'status' => QuestionStatus::Rejected,
                'instructor_id' => $instructor->getKey(),
                'reviewer_id' => $instructor->getKey(),
                'rejection_reason' => mb_substr($reason, 0, 2000),
                'rejected_by_role' => Role::Instructor->value,
                'updated_by' => $instructor->getKey(),
            ])->save();

            $this->resolvePendingCreateRequest(
                $question,
                $instructor,
                QuestionReviewStatus::Rejected,
                $reason,
            );

            $question = $question->refresh();

            if ((int) $question->version !== $versionBefore) {
                throw ValidationException::withMessages([
                    'version' => 'Từ chối giảng viên không được tăng version.',
                ]);
            }

            Auditor::record(
                AuditAction::QuestionInstructorRejected,
                $instructor,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'from_status' => QuestionStatus::InReview->value,
                    'to_status' => QuestionStatus::Rejected->value,
                    'review_note' => $reason,
                ],
            );

            return $question;
        });
    }

    private function authorize(User $instructor): void
    {
        abort_unless(
            $instructor->hasRole(Role::Instructor->value)
            && $instructor->can(Permission::QuestionReview->value),
            403,
            'Chỉ giảng viên được duyệt lớp 1.',
        );
    }

    private function resolvePendingCreateRequest(
        Question $question,
        User $instructor,
        QuestionReviewStatus $status,
        ?string $note,
    ): void {
        $pending = QuestionReviewRequest::query()
            ->where('question_id', $question->getKey())
            ->where('status', QuestionReviewStatus::Pending->value)
            ->where('action', QuestionReviewAction::Create->value)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if ($pending === null) {
            return;
        }

        $pending->forceFill([
            'status' => $status,
            'reviewed_by' => $instructor->getKey(),
            'review_note' => $this->cleanNote($note),
            'reviewed_at' => now(),
        ])->save();
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim(strip_tags((string) $note));

        return $note !== '' ? mb_substr($note, 0, 2000) : null;
    }
}
