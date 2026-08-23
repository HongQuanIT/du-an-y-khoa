<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use App\Support\Html\SafeHtml;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Support\Auditor;
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

    public function handle(User $actor, Question $question, QuestionStatus $to): Question
    {
        $from = $question->status;

        if ($from === $to) {
            return $question;
        }

        $this->assertTransitionAllowed($actor, $from, $to);
        $this->assertReadyForStatus($question, $to);

        $before = ['status' => $from->value];
        $this->captureVersion->handle($question, null, 'baseline');
        $question->forceFill([
            'status' => $to,
            'version' => $question->version + 1,
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
        $question->load(['options' => fn ($query) => $query->orderBy('order'), 'topics:id']);
        $this->captureVersion->handle($question, $actor, 'status');

        Auditor::record(
            'admin.question.status_change',
            $actor,
            $question,
            $before,
            ['status' => $to->value],
        );

        return $question->refresh();
    }

    private function assertTransitionAllowed(User $actor, QuestionStatus $from, QuestionStatus $to): void
    {
        $map = [
            QuestionStatus::Draft->value => [QuestionStatus::InReview, QuestionStatus::Published],
            QuestionStatus::InReview->value => [QuestionStatus::Draft, QuestionStatus::Published],
            QuestionStatus::Published->value => [QuestionStatus::Retired],
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
            QuestionStatus::Retired,
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
        if (! in_array($to, [QuestionStatus::InReview, QuestionStatus::Published], true)) {
            return;
        }

        $question->loadMissing('options');

        if (SafeHtml::isBlank($question->stem) || SafeHtml::isBlank($question->explanation)) {
            throw ValidationException::withMessages([
                'status' => 'Cần nội dung câu hỏi và giải thích chung trước khi gửi duyệt / xuất bản.',
            ]);
        }

        if (! $question->topics()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Cần chọn chủ đề.',
            ]);
        }

        if ($question->options->count() < 2 || $question->options->where('is_correct', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'status' => 'Cần ≥2 đáp án và đúng 1 đáp án đúng.',
            ]);
        }
    }
}
