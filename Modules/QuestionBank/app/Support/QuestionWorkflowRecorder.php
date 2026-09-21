<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Models\User;
use Modules\QuestionBank\Enums\EditorSubmitOutcome;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;

final class QuestionWorkflowRecorder
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        Question $question,
        QuestionWorkflowEventType $type,
        ?User $actor = null,
        ?string $note = null,
        ?string $actorRole = null,
        ?array $meta = null,
        ?int $publishedVersion = null,
    ): QuestionWorkflowEvent {
        $note = filled($note) ? mb_substr(trim(strip_tags((string) $note)), 0, 2000) : null;
        $isSubmit = $type === QuestionWorkflowEventType::Submit;

        return QuestionWorkflowEvent::query()->create([
            'question_id' => $question->getKey(),
            'review_cycle' => (int) $question->instructor_review_cycle,
            'published_version' => $publishedVersion,
            'event_type' => $type,
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actorRole,
            'note' => $note,
            'outcome' => EditorSubmitOutcome::Pending->value,
            'content_fingerprint' => $isSubmit
                ? ($question->content_fingerprint ?: null)
                : null,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);
    }

    public function bumpRejectCount(Question $question): void
    {
        $question->forceFill([
            'pipeline_reject_count' => (int) $question->pipeline_reject_count + 1,
        ])->save();
    }

    public function resetRejectCount(Question $question): void
    {
        $question->forceFill([
            'pipeline_reject_count' => 0,
        ])->save();
    }
}
