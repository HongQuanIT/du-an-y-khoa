<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Enums\Difficulty;

final class ListQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'filter.difficulty' => ['nullable', 'string', 'in:'.implode(',', Difficulty::values())],
            'filter.topic_id' => ['nullable', 'integer', 'min:1'],
            'filter.is_free' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toData(): ListQuestionsData
    {
        return new ListQuestionsData(
            query: $this->string('q')->value() ?: null,
            difficulty: $this->input('filter.difficulty'),
            topicId: $this->has('filter.topic_id') ? $this->integer('filter.topic_id') : null,
            freeOnly: $this->has('filter.is_free') ? $this->boolean('filter.is_free') : null,
            perPage: (int) min((int) $this->integer('per_page', 20) ?: 20, 100),
        );
    }
}
