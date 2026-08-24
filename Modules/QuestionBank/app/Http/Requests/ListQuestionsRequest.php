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
            'filter.blueprint_id' => ['nullable', 'integer', 'min:1'],
            'filter.blueprint_section_id' => ['nullable', 'integer', 'min:1'],
            'filter.core_clinical_topic_id' => ['nullable', 'array'],
            'filter.core_clinical_topic_id.*' => ['integer', 'min:1'],
            'filter.medical_taxonomy_node_id' => ['nullable', 'array'],
            'filter.medical_taxonomy_node_id.*' => ['integer', 'min:1'],
            'filter.tag_id' => ['nullable', 'array'],
            'filter.tag_id.*' => ['integer', 'min:1'],
            'filter.is_free' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toData(): ListQuestionsData
    {
        $query = strip_tags($this->string('q')->trim()->value());
        $query = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $query) ?? '';
        $query = preg_replace('/\s+/u', ' ', $query) ?? '';

        return new ListQuestionsData(
            query: $query !== '' ? $query : null,
            difficulty: $this->input('filter.difficulty'),
            blueprintId: $this->has('filter.blueprint_id') ? $this->integer('filter.blueprint_id') : null,
            blueprintSectionId: $this->has('filter.blueprint_section_id')
                ? $this->integer('filter.blueprint_section_id')
                : null,
            coreClinicalTopicIds: $this->integerList('filter.core_clinical_topic_id'),
            medicalTaxonomyNodeIds: $this->integerList('filter.medical_taxonomy_node_id'),
            tagIds: $this->integerList('filter.tag_id'),
            freeOnly: $this->has('filter.is_free') ? $this->boolean('filter.is_free') : null,
            perPage: (int) min((int) $this->integer('per_page', 20) ?: 20, 100),
        );
    }

    /** @return list<int> */
    private function integerList(string $key): array
    {
        if (! $this->has($key)) {
            return [];
        }

        $values = $this->input($key);
        if (! is_array($values)) {
            $values = [$values];
        }

        return collect($values)
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
}
