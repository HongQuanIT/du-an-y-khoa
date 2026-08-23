<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\QuestionBank\Models\Question;

/**
 * @mixin Question
 */
final class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'question',
            'attributes' => [
                'stem' => $this->stem,
                'difficulty' => $this->difficulty->value,
                'status' => $this->status->value,
                'topic_id' => $this->topic_id,
                'topic_ids' => ($this->relationLoaded('topics') ? $this->topics : $this->topics()->get())
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
                'is_free' => $this->is_free,
                'created_at' => $this->created_at?->toIso8601String(),
            ],
        ];
    }
}
