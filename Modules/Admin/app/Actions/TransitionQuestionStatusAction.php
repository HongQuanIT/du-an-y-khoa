<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use App\Support\Html\SafeHtml;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Actions\AdjudicateReviewOutcomesAction;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;
use Modules\QuestionBank\Support\AssignedInstructorMatcher;
use Modules\QuestionBank\Support\QuestionInstructorReviewCycle;
use Modules\QuestionBank\Support\QuestionQaCompleteness;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;
use Modules\QuestionBank\Support\QuestionReviewTimeline;
use Modules\QuestionBank\Support\QuestionWorkflowRecorder;

/**
 * Transition publication workflow for a question.
 */
final class TransitionQuestionStatusAction
{
    use AsAction;

    public function __construct(
        private readonly CaptureQuestionVersionAction $captureVersion,
        private readonly QuestionInstructorReviewCycle $reviewCycle,
        private readonly AssignedInstructorMatcher $instructorMatcher,
        private readonly QuestionReviewerFlagCycle $flagCycle,
        private readonly QuestionWorkflowRecorder $workflowRecorder,
        private readonly QuestionReviewTimeline $reviewTimeline,
        private readonly AdjudicateReviewOutcomesAction $adjudicateOutcomes,
        private readonly QuestionQaCompleteness $qaCompleteness,
    ) {}

    public function handle(
        User $actor,
        Question $question,
        QuestionStatus $to,
        ?string $rejectionReason = null,
        ?string $redFlagOutcome = null,
    ): Question {
        $from = $question->status;

        if ($from === $to) {
            if ($to === QuestionStatus::InReview) {
                $this->assertCanSubmit($actor);
                $this->assertReadyForStatus($question, $to);
                $before = AuditSnapshot::question($question);
                $this->reviewCycle->startOrReset($question);
                $this->queueCreationReview($actor, $question->refresh());
                $this->workflowRecorder->record(
                    $question->refresh(),
                    QuestionWorkflowEventType::Submit,
                    $actor,
                    actorRole: 'content_editor',
                );
                Auditor::record(
                    AuditAction::QuestionStatusChanged,
                    $actor,
                    $question,
                    $before,
                    AuditSnapshot::question($question->refresh()),
                    metadata: [
                        'from_status' => $from->value,
                        'to_status' => $to->value,
                        'instructor_reviews_reset' => true,
                    ],
                );

                return $question->refresh();
            }

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

            if ($from === QuestionStatus::PendingPublish) {
                $this->workflowRecorder->record(
                    $question->refresh(),
                    QuestionWorkflowEventType::AdminReject,
                    $actor,
                    note: trim((string) $rejectionReason),
                    actorRole: 'admin',
                    meta: [
                        'had_red_flag' => $this->flagCycle->hasRedFlag($question),
                        'red_flag_outcome' => $redFlagOutcome,
                    ],
                );
                $this->workflowRecorder->bumpRejectCount($question->refresh());

                if ($this->flagCycle->hasRedFlag($question)) {
                    $this->adjudicateOutcomes->onAdminReject(
                        $question->refresh(),
                        $actor,
                        in_array($redFlagOutcome, ['confirmed', 'false_positive'], true)
                            ? $redFlagOutcome
                            : 'confirmed',
                    );
                }
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

        if ($to === QuestionStatus::Draft && $from === QuestionStatus::Rejected) {
            $question->forceFill([
                'status' => $to,
                'rejection_reason' => null,
                'rejected_by_role' => null,
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->reviewCycle->clearSlots($question);

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
            $this->reviewCycle->startOrReset($question);
            $this->workflowRecorder->record(
                $question->refresh(),
                QuestionWorkflowEventType::Submit,
                $actor,
                actorRole: 'content_editor',
            );
        }

        if ($to === QuestionStatus::Draft && in_array($from, [
            QuestionStatus::InReview,
            QuestionStatus::InFlagReview,
        ], true)) {
            $question->reviewRequests()
                ->where('status', QuestionReviewStatus::Pending->value)
                ->where('action', QuestionReviewAction::Create->value)
                ->delete();
            $this->reviewCycle->clearSlots($question);
        }

        if ($isPublishing) {
            $question->load([
                'options' => fn ($query) => $query->orderBy('order'),
                'lessons:id',
                'instructor:id,name',
                'assignedInstructor:id,name',
                'publisher:id,name',
                'reviewerSlot1:id,name',
                'reviewerSlot2:id,name',
            ]);
            $pipelineMeta = $this->reviewTimeline->publishMeta($question);
            $this->captureVersion->handle(
                $question,
                $actor,
                'publish',
                reviewPipeline: $pipelineMeta,
            );
            $this->workflowRecorder->record(
                $question,
                QuestionWorkflowEventType::Publish,
                $actor,
                actorRole: 'admin',
                meta: $pipelineMeta,
                publishedVersion: (int) $question->version,
            );
            $this->adjudicateOutcomes->onPublish($question->refresh(), $actor);
            $this->workflowRecorder->resetRejectCount($question->refresh());
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
            ],
            QuestionStatus::InFlagReview->value => [],
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

        if ($to === QuestionStatus::PendingPublish) {
            abort(403, 'Chỉ reviewer gắn đủ 2 cờ mới chuyển câu sang chờ xuất bản.');
        }

        // Retire follows the publisher permission after retiring the legacy question.retire ability.
        if ($to === QuestionStatus::Retired) {
            if (! $actor->can(Permission::QuestionPublish->value)) {
                abort(403, 'Cần quyền question.publish.');
            }

            return;
        }

        if ($to === QuestionStatus::Rejected) {
            if (! $actor->can('question.reject')) {
                abort(403, 'Cần quyền question.reject.');
            }

            return;
        }

        // Lớp 2 — publish / private
        $needsPublishPermission = in_array($to, [
            QuestionStatus::Published,
            QuestionStatus::Private,
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

                $this->assertLayerOneComplete($question, $actor, 'xuất bản');
            }

            if ($to === QuestionStatus::Private && $from === QuestionStatus::PendingPublish) {
                $this->assertLayerOneComplete($question, $actor, 'ẩn khỏi ngân hàng câu hỏi');
            }

            if ($to === QuestionStatus::Rejected && $from !== QuestionStatus::PendingPublish) {
                throw ValidationException::withMessages([
                    'status' => 'Admin chỉ từ chối được câu đang chờ xuất bản. Từ chối lớp 1 thuộc giảng viên.',
                ]);
            }

            return;
        }

        // Submit / withdraw (chỉ khi GV chưa duyệt)
        if (
            ($from === QuestionStatus::Draft && $to === QuestionStatus::InReview)
            || ($from === QuestionStatus::InReview && $to === QuestionStatus::Draft)
        ) {
            $this->assertCanSubmit($actor);

            return;
        }

        // Rejected / retired → draft (creator resumes editing)
        if (! $actor->can(Permission::QuestionUpdate->value)) {
            abort(403, 'Cần quyền question.update.');
        }
    }

    private function assertCanSubmit(User $actor): void
    {
        if (! $actor->can(Permission::QuestionSubmit->value)) {
            abort(403, 'Cần quyền question.submit.');
        }
    }

    private function assertLayerOneComplete(Question $question, User $actor, string $actionLabel): void
    {
        if ($this->flagCycle->hasRedFlag($question)) {
            throw ValidationException::withMessages([
                'status' => 'Có cờ đỏ — không xuất bản được. Chỉ được trả về biên tập.',
            ]);
        }

        if (! $this->reviewCycle->hasRequiredApprovals($question)) {
            throw ValidationException::withMessages([
                'status' => 'Cần giảng viên duyệt và đủ 2 cờ xanh trước khi '.$actionLabel.'.',
            ]);
        }

        if ($this->qaCompleteness->blocksPublish($question)) {
            throw ValidationException::withMessages([
                'status' => $this->qaCompleteness->publishBlockedMessage($question),
            ]);
        }

        $blockedIds = $this->reviewCycle->blockedPublisherIds($question);
        if (in_array((int) $actor->getKey(), $blockedIds, true)) {
            throw ValidationException::withMessages([
                'status' => 'Người xuất bản phải khác giảng viên được gán và 2 reviewer đã gắn cờ.',
            ]);
        }
    }

    private function assertReadyForStatus(Question $question, QuestionStatus $to): void
    {
        if (! in_array($to, [
            QuestionStatus::InReview,
            QuestionStatus::InFlagReview,
            QuestionStatus::PendingPublish,
            QuestionStatus::Published,
            QuestionStatus::Private,
        ], true)) {
            return;
        }

        if ($to === QuestionStatus::InReview) {
            $this->assertAssignedInstructorReady($question);
        }
        $question->loadMissing('options');

        if (SafeHtml::isBlank($question->stem)) {
            throw ValidationException::withMessages([
                'status' => 'Vui lòng nhập nội dung câu hỏi trước khi gửi duyệt / xuất bản.',
            ]);
        }

        if (! $question->lessons()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Cần chọn ít nhất một bài học.',
            ]);
        }

        if ($question->options->count() !== 4 || $question->options->where('is_correct', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'status' => 'Cần đúng 4 đáp án và đúng 1 đáp án đúng.',
            ]);
        }
    }

    private function assertAssignedInstructorReady(Question $question): void
    {
        $instructorId = (int) $question->assigned_instructor_id;
        if ($instructorId <= 0) {
            throw ValidationException::withMessages([
                'assigned_instructor_id' => 'Vui lòng chọn giảng viên đúng chuyên môn trước khi gửi duyệt.',
            ]);
        }

        $instructor = User::query()->find($instructorId);
        if ($instructor === null || ! $this->instructorMatcher->instructorMatchesQuestion($instructor, $question)) {
            throw ValidationException::withMessages([
                'assigned_instructor_id' => 'Giảng viên đã chọn không còn khớp môn học của câu hỏi. Hãy chọn lại.',
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
