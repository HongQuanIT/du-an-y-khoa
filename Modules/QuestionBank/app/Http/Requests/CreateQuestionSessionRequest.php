<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Requests;

use App\Support\ScopeFilters;
use App\Support\TargetExams;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;

/**
 * Validates the custom-session builder before a question snapshot is drawn.
 */
final class CreateQuestionSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $difficulties = $this->input('difficulties');
        if ($difficulties === null && $this->filled('difficulty')) {
            $difficulties = [$this->input('difficulty')];
        }

        $folderId = $this->input('folder_id');
        $folderId = is_numeric($folderId) && (int) $folderId > 0 ? (int) $folderId : null;

        $this->merge([
            'difficulties' => array_values(array_filter(
                (array) $difficulties,
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            )),
            'saved_only' => $this->boolean('saved_only') || $folderId !== null,
            'folder_id' => $folderId,
            'source' => $this->input('source', SessionSource::Custom->value),
            'question_status_mode' => $this->input('question_status_mode', 'latest'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(SessionMode::class)],
            'source' => ['required', Rule::enum(SessionSource::class), 'in:custom,weak_topics'],
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
            'topic_ids' => ['nullable', 'array'],
            'topic_ids.*' => ['integer', 'distinct', 'exists:topics,id'],
            'difficulties' => ['nullable', 'array', 'max:'.count(Difficulty::cases())],
            'difficulties.*' => ['string', 'distinct', Rule::enum(Difficulty::class)],
            'question_statuses' => ['nullable', 'array'],
            'question_statuses.*' => [
                'string',
                'distinct',
                'in:unanswered,correct_with_hints,incorrect,correct,omitted,marked',
            ],
            'question_status_mode' => ['nullable', 'string', 'in:all,latest'],
            'saved_only' => ['nullable', 'boolean'],
            'folder_id' => ['nullable', 'integer', 'exists:bookmark_folders,id'],
            'exam_key' => ['nullable', 'string', Rule::in(TargetExams::keys())],
            'articles' => ['nullable', 'array'],
            'articles.*' => ['string', 'distinct', Rule::in(array_column(ScopeFilters::articles(), 'id'))],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', 'distinct', Rule::in(array_column(ScopeFilters::symptoms(), 'id'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'count.max' => 'Số câu làm không được vượt quá tổng câu phù hợp.',
        ];
    }

    public function toData(): CreateSessionData
    {
        return new CreateSessionData(
            mode: SessionMode::from((string) $this->input('mode')),
            source: SessionSource::from((string) $this->input('source', SessionSource::Custom->value)),
            count: $this->integer('count'),
            topicIds: array_values(array_unique(array_map('intval', $this->input('topic_ids', [])))),
            difficulties: array_values(array_unique(array_map('strval', $this->input('difficulties', [])))),
            questionStatuses: array_values(array_unique(array_map('strval', $this->input('question_statuses', [])))),
            questionStatusMode: (string) $this->input('question_status_mode', 'latest'),
            savedOnly: $this->boolean('saved_only') || $this->filled('folder_id'),
            folderId: $this->filled('folder_id') ? $this->integer('folder_id') : null,
            examKey: $this->filled('exam_key') ? (string) $this->input('exam_key') : null,
            articles: array_values(array_unique(array_map('strval', $this->input('articles', [])))),
            symptoms: array_values(array_unique(array_map('strval', $this->input('symptoms', [])))),
        );
    }
}
