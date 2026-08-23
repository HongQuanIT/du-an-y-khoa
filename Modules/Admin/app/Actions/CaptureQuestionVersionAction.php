<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionVersion;

final class CaptureQuestionVersionAction
{
    public function handle(
        Question $question,
        ?User $actor = null,
        string $event = 'save',
        ?int $restoredFromVersion = null,
    ): QuestionVersion {
        $question->loadMissing([
            'topics:id',
            'options' => fn ($query) => $query->orderBy('order'),
        ]);

        return QuestionVersion::query()->firstOrCreate(
            [
                'question_id' => $question->getKey(),
                'version' => (int) $question->version,
            ],
            [
                'snapshot' => $this->snapshot($question),
                'created_by' => $actor?->getKey(),
                'event' => $event,
                'restored_from_version' => $restoredFromVersion,
                'created_at' => now(),
            ],
        );
    }

    /** @return array<string, mixed> */
    public function snapshot(Question $question): array
    {
        return [
            'stem' => (string) $question->stem,
            'stem_image_path' => $question->stem_image_path,
            'explanation' => $question->explanation,
            'key_info' => array_values((array) $question->key_info),
            'attending_tip' => $question->attending_tip,
            'difficulty' => $question->difficulty->value,
            'status' => $question->status->value,
            'topic_ids' => $question->topics->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'is_free' => (bool) $question->is_free,
            'options' => $question->options->map(fn (QuestionOption $option): array => [
                'label' => (string) $option->label,
                'content' => (string) $option->content,
                'is_correct' => (bool) $option->is_correct,
                'explanation' => $option->explanation,
                'order' => (int) $option->order,
            ])->values()->all(),
        ];
    }
}
