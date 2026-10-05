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
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\Personalization\Models\BookmarkFolder;

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
        $source = (string) $this->input('source', SessionSource::Custom->value);
        $isAdaptive = $source === SessionSource::WeakTopics->value;

        $difficulties = $this->input('difficulties');
        if ($difficulties === null && $this->filled('difficulty')) {
            $difficulties = [$this->input('difficulty')];
        }

        $folderId = $this->input('folder_id');
        $folderId = is_numeric($folderId) && (int) $folderId > 0 ? (int) $folderId : null;
        $folderIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->input('folder_ids', [])),
            static fn (int $id): bool => $id > 0,
        )));
        if ($folderIds === [] && $folderId !== null) {
            $folderIds = [$folderId];
        }

        // Adaptive: keep exam + optional hệ/môn; drop lesson / difficulty / status / saved.
        if ($isAdaptive) {
            $focus = (string) $this->input('adaptive_focus', 'balanced');
            if (! in_array($focus, ['weak_focus', 'balanced', 'retention'], true)) {
                $focus = 'balanced';
            }

            $this->merge([
                'source' => SessionSource::WeakTopics->value,
                'adaptive_focus' => $focus,
                'difficulties' => [],
                'question_statuses' => [],
                'question_status_mode' => 'latest',
                'lesson_ids' => [],
                'core_clinical_topic_ids' => [],
                'tag_ids' => [],
                'articles' => [],
                'symptoms' => [],
                'saved_only' => false,
                'folder_id' => null,
                'folder_ids' => [],
            ]);

            return;
        }

        $this->merge([
            'difficulties' => array_values(array_filter(
                (array) $difficulties,
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            )),
            'saved_only' => $this->boolean('saved_only') || $folderIds !== [],
            'folder_id' => $folderIds[0] ?? null,
            'folder_ids' => $folderIds,
            'source' => SessionSource::Custom->value,
            'question_status_mode' => $this->input('question_status_mode', 'latest'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $professionId = $this->user()?->learnerProfile?->profession_id;
            if ($professionId === null) {
                $validator->errors()->add('profession', 'Hãy chọn chức danh trên hồ sơ trước khi tạo phiên luyện.');

                return;
            }

            $folderIds = $this->input('folder_ids', []);
            if ($folderIds !== [] && BookmarkFolder::query()
                ->where('user_id', (int) $this->user()->getKey())
                ->whereIn('id', $folderIds)
                ->count() !== count($folderIds)) {
                $validator->errors()->add('folder_ids', 'Bộ sưu tập câu hỏi đã lưu không hợp lệ.');
            }

            if (! $this->filled('exam_catalog_id')) {
                return;
            }

            $allowed = ExamCatalog::query()
                ->whereKey($this->integer('exam_catalog_id'))
                ->where('status', TaxonomyStatus::Active)
                ->whereHas(
                    'professions',
                    fn ($professions) => $professions->where('professions.id', (int) $professionId),
                )
                ->exists();

            if (! $allowed) {
                $validator->errors()->add('exam_catalog_id', 'Kỳ thi không thuộc chức danh của bạn.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isAdaptive = $this->input('source') === SessionSource::WeakTopics->value;

        return [
            'mode' => ['required', Rule::enum(SessionMode::class)],
            'source' => ['required', Rule::enum(SessionSource::class), 'in:custom,weak_topics'],
            'name' => ['nullable', 'string', 'max:120'],
            'adaptive_focus' => [
                Rule::requiredIf($isAdaptive),
                'nullable',
                'string',
                'in:weak_focus,balanced,retention',
            ],
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
            'blueprint_id' => [
                'nullable',
                'integer',
                'exists:blueprints,id',
            ],
            'exam_catalog_id' => [
                'nullable',
                'integer',
                'exists:exam_catalogs,id',
            ],
            'blueprint_section_id' => ['nullable', 'integer', 'exists:blueprint_sections,id'],
            'core_clinical_topic_ids' => ['nullable', 'array'],
            'core_clinical_topic_ids.*' => ['integer', 'distinct', 'exists:core_clinical_topics,id'],
            'organ_system_ids' => ['nullable', 'array'],
            'organ_system_ids.*' => ['integer', 'distinct', 'exists:organ_systems,id'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
            'lesson_ids' => ['nullable', 'array'],
            'lesson_ids.*' => ['integer', 'distinct', 'exists:lessons,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'difficulties' => ['nullable', 'array', 'max:'.count(Difficulty::cases())],
            'difficulties.*' => ['string', 'distinct', Rule::enum(Difficulty::class)],
            'question_statuses' => ['nullable', 'array'],
            'question_statuses.*' => [
                'string',
                'distinct',
                'in:unanswered,correct_with_hints,incorrect,correct,omitted,flagged,marked',
            ],
            'question_status_mode' => ['nullable', 'string', 'in:all,latest'],
            'saved_only' => ['nullable', 'boolean'],
            'folder_id' => ['nullable', 'integer', 'exists:bookmark_folders,id'],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['integer', 'distinct', 'exists:bookmark_folders,id'],
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
        $isAdaptive = $this->input('source') === SessionSource::WeakTopics->value;

        $name = trim((string) $this->input('name', ''));

        return new CreateSessionData(
            mode: SessionMode::from((string) $this->input('mode')),
            source: SessionSource::from((string) $this->input('source', SessionSource::Custom->value)),
            count: $this->integer('count'),
            blueprintId: $this->filled('blueprint_id') ? $this->integer('blueprint_id') : null,
            examCatalogId: $this->filled('exam_catalog_id') ? $this->integer('exam_catalog_id') : null,
            blueprintSectionId: $this->filled('blueprint_section_id') ? $this->integer('blueprint_section_id') : null,
            coreClinicalTopicIds: array_values(array_unique(array_map('intval', $this->input('core_clinical_topic_ids', [])))),
            organSystemIds: array_values(array_unique(array_map('intval', $this->input('organ_system_ids', [])))),
            subjectIds: array_values(array_unique(array_map('intval', $this->input('subject_ids', [])))),
            lessonIds: array_values(array_unique(array_map('intval', $this->input('lesson_ids', [])))),
            tagIds: array_values(array_unique(array_map('intval', $this->input('tag_ids', [])))),
            difficulties: array_values(array_unique(array_map('strval', $this->input('difficulties', [])))),
            questionStatuses: array_values(array_unique(array_map('strval', $this->input('question_statuses', [])))),
            questionStatusMode: (string) $this->input('question_status_mode', 'latest'),
            savedOnly: $this->boolean('saved_only') || $this->input('folder_ids', []) !== [],
            folderId: $this->filled('folder_id') ? $this->integer('folder_id') : null,
            folderIds: array_values(array_unique(array_map('intval', $this->input('folder_ids', [])))),
            examKey: $this->filled('exam_key') ? (string) $this->input('exam_key') : null,
            articles: array_values(array_unique(array_map('strval', $this->input('articles', [])))),
            symptoms: array_values(array_unique(array_map('strval', $this->input('symptoms', [])))),
            adaptiveFocus: $isAdaptive
                ? (string) $this->input('adaptive_focus', 'balanced')
                : null,
            name: $name !== '' ? $name : null,
        );
    }
}
