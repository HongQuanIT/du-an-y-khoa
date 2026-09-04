<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use App\Support\Html\SafeHtml;
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

/**
 * Transition publication workflow for a question.
 */
final class TransitionQuestionStatusAction
{
    use AsAction;

    public function __construct(
        private readonly CaptureQuestionVersionAction $captureVersion,
    ) {}

    public function handle(
        User $actor,
        Question $question,
        QuestionStatus $to,
        ?string $rejectionReason = null,
    ): Question {
        $from = $question->status;

        if ($from === $to) {
            return $question;
        }

        $this->assertTransitionAllowed($actor, $question, $from, $to);
        $this->assertReadyForStatus($question, $to);

        $before = AuditSnapshot::question($question);

        if ($to === QuestionStatus::Rejected) {
            if (blank($rejectionReason)) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Vui lòng nhập lý do từ chối.',
                ]);
            }

            $question->forceFill([
                'status' => $to,
                'reviewer_id' => $actor->getKey(),
                'publisher_id' => $from === QuestionStatus::PendingPublish ? $actor->getKey() : $question->publisher_id,
                'rejection_reason' => trim((string) $rejectionReason),
                'rejected_by_role' => $from === QuestionStatus::PendingPublish
                    ? 'admin'
                    : ($question->rejected_by_role ?? 'admin'),
                'updated_by' => $actor->getKey(),
            ])->save();

            Auditor::record(
                AuditAction::QuestionStatusChanged,
                $actor,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                ],
            );

            return $question->refresh();
        }

        if ($to === QuestionStatus::Draft && $from === QuestionStatus::Rejected) {
            $question->forceFill([
                'status' => $to,
                'rejection_reason' => null,
                'rejected_by_role' => null,
                'updated_by' => $actor->getKey(),
            ])->save();

            Auditor::record(
                AuditAction::QuestionStatusChanged,
                $actor,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                ],
            );

            return $question->refresh();
        }

        $isPublishing = in_array($to, [QuestionStatus::Published, QuestionStatus::Private], true);

        // Chỉ snapshot bản đã từng publish; câu mới (version 0) chưa có phiên bản.
        if ($isPublishing && (int) $question->version > 0) {
            $this->captureVersion->handle($question, null, 'baseline');
        }

        $nextVersion = $isPublishing ? ((int) $question->version + 1) : (int) $question->version;

        $question->forceFill([
            'status' => $to,
            'version' => $nextVersion,
            'published_version' => $isPublishing ? $nextVersion : $question->published_version,
            'updated_by' => $actor->getKey(),
            'reviewer_id' => $isPublishing
                ? $actor->getKey()
                : $question->reviewer_id,
            'publisher_id' => $isPublishing
                ? $actor->getKey()
                : $question->publisher_id,
            'rejection_reason' => $isPublishing
                ? null
                : $question->rejection_reason,
            'rejected_by_role' => $isPublishing
                ? null
                : $question->rejected_by_role,
        ])->save();

        if ($to === QuestionStatus::Published && QuestionAccess::isReviewer($actor)) {
            $question->reviewRequests()
                ->where('status', QuestionReviewStatus::Pending->value)
                ->where('action', QuestionReviewAction::Create->value)
                ->update([
                    'status' => QuestionReviewStatus::Approved->value,
                    'reviewed_by' => $actor->getKey(),
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if ($to === QuestionStatus::InReview && ! QuestionAccess::isReviewer($actor)) {
            $this->queueCreationReview($actor, $question);
        }

        if ($to === QuestionStatus::Draft && $from === QuestionStatus::InReview) {
            $question->reviewRequests()
                ->where('status', QuestionReviewStatus::Pending->value)
                ->where('action', QuestionReviewAction::Create->value)
                ->delete();
        }

        if ($isPublishing) {
            $question->load(['options' => fn ($query) => $query->orderBy('order'), 'medicalTaxonomyNodes:id']);
            $this->captureVersion->handle($question, $actor, 'publish');
        }

        Auditor::record(
            AuditAction::QuestionStatusChanged,
            $actor,
            $question,
            $before,
            AuditSnapshot::question($question),
            metadata: [
                'from_status' => $from->value,
                'to_status' => $to->value,
            ],
        );

        return $question->refresh();
    }

    private function assertTransitionAllowed(
        User $actor,
        Question $question,
        QuestionStatus $from,
        QuestionStatus $to,
    ): void {
        $map = [
            QuestionStatus::Draft->value => [QuestionStatus::InReview],
            QuestionStatus::InReview->value => [
                QuestionStatus::Draft,
                QuestionStatus::PendingPublish,
            ],
            QuestionStatus::PendingPublish->value => [
                QuestionStatus::Published,
                QuestionStatus::Private,
                QuestionStatus::Rejected,
            ],
            QuestionStatus::Published->value => [
                QuestionStatus::Private,
                QuestionStatus::Retired,
            ],
            QuestionStatus::Rejected->value => [QuestionStatus::Draft],
            QuestionStatus::Private->value => [
                QuestionStatus::Published,
                QuestionStatus::Retired,
            ],
            QuestionStatus::Retired->value => [QuestionStatus::Draft],
        ];

        $allowed = $map[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Không chuyển được từ {$from->label()} sang {$to->label()}.",
            ]);
        }

        // Lớp 1 — chỉ role giảng viên + question.review. Admin không duyệt thay.
        if ($to === QuestionStatus::PendingPublish) {
            if (! $actor->hasRole(\App\Support\Enums\Role::Instructor->value)
                || ! $actor->can(Permission::QuestionReview->value)) {
                abort(403, 'Chỉ giảng viên có quyền question.review được duyệt lớp 1.');
            }

            return;
        }

        // Lớp 2 — publish / reject-publish / retire
        $needsPublishPermission = in_array($to, [
            QuestionStatus::Published,
            QuestionStatus::Private,
            QuestionStatus::Retired,
            QuestionStatus::Rejected,
        ], true);

        if ($needsPublishPermission) {
            if (! $actor->can(Permission::QuestionPublish->value)) {
                abort(403, 'Cần quyền question.publish.');
            }

            if ($to === QuestionStatus::Published) {
                if ($from === QuestionStatus::Private) {
                    return;
                }

                if ($from !== QuestionStatus::PendingPublish) {
                    throw ValidationException::withMessages([
                        'status' => 'Chỉ xuất bản được câu đã được giảng viên duyệt (chờ xuất bản).',
                    ]);
                }

                if ($question->instructor_id === null) {
                    throw ValidationException::withMessages([
                        'status' => 'Thiếu giảng viên duyệt lớp 1 — không thể xuất bản.',
                    ]);
                }

                if ((int) $question->instructor_id === (int) $actor->getKey()) {
                    throw ValidationException::withMessages([
                        'status' => 'Cần ít nhất 2 người duyệt: người xuất bản phải khác giảng viên đã duyệt.',
                    ]);
                }
            }

            if ($to === QuestionStatus::Private && $from === QuestionStatus::PendingPublish) {
                if ($question->instructor_id === null) {
                    throw ValidationException::withMessages([
                        'status' => 'Thiếu giảng viên duyệt lớp 1 — không thể đưa vào kho đề thi.',
                    ]);
                }

                if ((int) $question->instructor_id === (int) $actor->getKey()) {
                    throw ValidationException::withMessages([
                        'status' => 'Cần ít nhất 2 người duyệt: người xuất bản phải khác giảng viên đã duyệt.',
                    ]);
                }
            }

            if ($to === QuestionStatus::Rejected && $from !== QuestionStatus::PendingPublish) {
                throw ValidationException::withMessages([
                    'status' => 'Admin chỉ từ chối được câu đang chờ xuất bản. Từ chối lớp 1 thuộc giảng viên.',
                ]);
            }

            return;
        }

        // Submit / withdraw: question.update
        if (! $actor->can(Permission::QuestionUpdate->value)) {
            abort(403, 'Cần quyền question.update.');
        }
    }

    private function assertReadyForStatus(Question $question, QuestionStatus $to): void
    {
        if (! in_array($to, [
            QuestionStatus::InReview,
            QuestionStatus::PendingPublish,
            QuestionStatus::Published,
            QuestionStatus::Private,
        ], true)) {
            return;
        }
        $question->loadMissing('options');

        if (SafeHtml::isBlank($question->stem)) {
            throw ValidationException::withMessages([
                'status' => 'Vui lòng nhập nội dung câu hỏi trước khi gửi duyệt / xuất bản.',
            ]);
        }

        if (! $question->medicalTaxonomyNodes()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Cần chọn ít nhất một mục danh mục y khoa.',
            ]);
        }

        if ($question->options->count() < 2 || $question->options->where('is_correct', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'status' => 'Cần ≥2 đáp án và đúng 1 đáp án đúng.',
            ]);
        }

        if ($to === QuestionStatus::Private && ! $question->exam_flag) {
            throw ValidationException::withMessages([
                'status' => 'Câu exam pool cần bật exam_flag.',
            ]);
        }
    }

    private function queueCreationReview(User $actor, Question $question): QuestionReviewRequest
    {
        $pending = $question->reviewRequests()
            ->where('status', QuestionReviewStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            if ($pending->action !== QuestionReviewAction::Create) {
                throw ValidationException::withMessages([
                    'review' => 'Câu hỏi đang có một yêu cầu khác chờ duyệt.',
                ]);
            }

            $pending->forceFill(['updated_at' => now()])->save();

            return $pending;
        }

        return QuestionReviewRequest::query()->create([
            'question_id' => $question->getKey(),
            'action' => QuestionReviewAction::Create,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $actor->getKey(),
        ]);
    }
}
