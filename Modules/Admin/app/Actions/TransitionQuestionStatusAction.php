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

        $this->assertTransitionAllowed($actor, $from, $to);
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
                'rejection_reason' => trim((string) $rejectionReason),
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

        $this->captureVersion->handle($question, null, 'baseline');
        $question->forceFill([
            'status' => $to,
            'version' => $question->version + 1,
            'updated_by' => $actor->getKey(),
            'reviewer_id' => in_array($to, [QuestionStatus::Published, QuestionStatus::Private], true)
                ? $actor->getKey()
                : $question->reviewer_id,
            'rejection_reason' => in_array($to, [QuestionStatus::Published, QuestionStatus::Private], true)
                ? null
                : $question->rejection_reason,
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

        $question->load(['options' => fn ($query) => $query->orderBy('order'), 'medicalTaxonomyNodes:id']);
        $this->captureVersion->handle($question, $actor, 'status');

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

    private function assertTransitionAllowed(User $actor, QuestionStatus $from, QuestionStatus $to): void
    {
        $map = [
            QuestionStatus::Draft->value => [QuestionStatus::InReview, QuestionStatus::Published, QuestionStatus::Private],
            QuestionStatus::InReview->value => [QuestionStatus::Draft, QuestionStatus::Published, QuestionStatus::Rejected],
            QuestionStatus::Published->value => [QuestionStatus::Retired, QuestionStatus::InReview],
            QuestionStatus::Rejected->value => [QuestionStatus::Draft],
            QuestionStatus::Private->value => [QuestionStatus::Retired, QuestionStatus::InReview],
            QuestionStatus::Retired->value => [QuestionStatus::Draft],
        ];

        $allowed = $map[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Không chuyển được từ {$from->label()} sang {$to->label()}.",
            ]);
        }

        $needsPublishPermission = in_array($to, [
            QuestionStatus::Published,
            QuestionStatus::Private,
            QuestionStatus::Retired,
            QuestionStatus::Rejected,
        ], true);

        if ($needsPublishPermission && ! $actor->can(Permission::QuestionPublish->value)) {
            abort(403, 'Cần quyền question.publish.');
        }

        if (! $needsPublishPermission && ! $actor->can(Permission::QuestionUpdate->value)) {
            abort(403, 'Cần quyền question.update.');
        }
    }

    private function assertReadyForStatus(Question $question, QuestionStatus $to): void
    {
        if (! in_array($to, [QuestionStatus::InReview, QuestionStatus::Published, QuestionStatus::Private], true)) {
            return;
        }

        $question->loadMissing('options');

        if (SafeHtml::isBlank($question->stem) || SafeHtml::isBlank($question->explanation)) {
            throw ValidationException::withMessages([
                'status' => 'Cần nội dung câu hỏi và giải thích cho đáp án đúng trước khi gửi duyệt / xuất bản.',
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
}
