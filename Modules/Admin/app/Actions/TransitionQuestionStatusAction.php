<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use App\Support\Html\SafeHtml;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Support\Auditor;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

/**
 * Transition publication workflow for a question.
 */
final class TransitionQuestionStatusAction
{
    use AsAction;

    public function handle(User $actor, Question $question, QuestionStatus $to): Question
    {
        $from = $question->status;

        if ($from === $to) {
            return $question;
        }

        $this->assertTransitionAllowed($actor, $from, $to);
        $this->assertReadyForStatus($question, $to);

        $before = ['status' => $from->value];
        $question->forceFill([
            'status' => $to,
            'version' => $question->version + 1,
        ])->save();

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

        if ($question->topic_id === null) {
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
