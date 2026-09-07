<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use Modules\StudyPlan\Data\StudyPlanData;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Support\TargetExams;

/**
 * Wizard input for creating/editing a plan (srs/modules/04 §3, §7).
 */
final class StudyPlanRequest extends FormRequest
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

        $hoursPerDay = $this->filled('hours_per_day')
            ? (float) $this->input('hours_per_day')
            : max(0.5, ((int) $this->input('daily_goal_questions', 20)) / 20);

        $this->merge([
            'difficulties' => array_values(array_filter(
                (array) $difficulties,
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            )),
            'saved_only' => $this->boolean('saved_only'),
            'question_status_mode' => 'latest',
            'hours_per_day' => $hoursPerDay,
            'daily_goal_questions' => (int) round($hoursPerDay * 20),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_key' => ['required', 'string', 'in:'.implode(',', TargetExams::keys())],
            'exam_target_date' => ['required', 'date', 'after:today'],
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'daily_goal_questions' => ['required', 'integer', 'min:10', 'max:200'],
            'medical_taxonomy_node_ids' => ['nullable', 'array'],
            'medical_taxonomy_node_ids.*' => ['integer', 'exists:medical_taxonomy_nodes,id'],
            'topic_ids' => ['nullable', 'array'],
            'topic_ids.*' => ['integer', 'exists:medical_taxonomy_nodes,id'],
            'system_ids' => ['nullable', 'array'],
            'system_ids.*' => ['integer', 'distinct', 'exists:medical_taxonomy_nodes,id'],
            'discipline_ids' => ['nullable', 'array'],
            'discipline_ids.*' => ['integer', 'distinct', 'exists:medical_taxonomy_nodes,id'],
            'exam_tags' => ['nullable', 'array'],
            'exam_tags.*' => ['string', 'max:64'],
            'articles' => ['nullable', 'array'],
            'articles.*' => ['string', 'max:120'],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', 'max:120'],
            'saved_only' => ['nullable', 'boolean'],
            'difficulties' => ['nullable', 'array', 'max:'.count(Difficulty::cases())],
            'difficulties.*' => ['string', 'distinct', 'in:'.implode(',', Difficulty::values())],
            'question_statuses' => ['nullable', 'array'],
            'question_statuses.*' => ['string', 'in:unanswered,correct_with_hints,incorrect,correct'],
            'question_status_mode' => ['nullable', 'string', 'in:all,latest'],
            'blueprint_id' => ['nullable', 'integer', 'exists:blueprints,id'],
            'blueprint_section_id' => ['nullable', 'integer', 'exists:blueprint_sections,id'],
            'core_clinical_topic_ids' => ['nullable', 'array'],
            'core_clinical_topic_ids.*' => ['integer', 'distinct', 'exists:core_clinical_topics,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'study_days' => ['required', 'array', 'min:1'],
            'study_days.*' => ['integer', 'distinct', 'between:1,7'],
            'strategy' => ['required', 'string', 'in:'.implode(',', PlanStrategy::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exam_target_date.after' => 'Ngày thi phải ở tương lai.',
            'study_days.required' => 'Chọn ít nhất một ngày học trong tuần.',
            'hours_per_day.min' => 'Thời gian học tối thiểu là 0,5 giờ mỗi ngày.',
            'hours_per_day.max' => 'Thời gian học không được vượt quá 10 giờ mỗi ngày.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->failed()) {
                return;
            }

            $user = $this->user();
            if ($user === null) {
                return;
            }

            $data = $this->toData();
            if (! $this->hasStudyDate($data)) {
                $validator->errors()->add(
                    'study_days',
                    'Không có ngày học nào đã chọn nằm trong thời hạn của kế hoạch.',
                );

                return;
            }

            $selector = app(SessionQuestionSelector::class);
            $poolData = new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::Custom,
                count: 1,
                blueprintId: $data->blueprintId,
                blueprintSectionId: $data->blueprintSectionId,
                coreClinicalTopicIds: $data->coreClinicalTopicIds,
                medicalTaxonomyNodeIds: $data->additionalTopicIds,
                systemIds: $data->systemIds,
                disciplineIds: $data->disciplineIds,
                tagIds: $data->tagIds,
                difficulties: $data->difficulties,
                questionStatuses: $data->questionStatuses,
                questionStatusMode: $data->questionStatusMode,
                savedOnly: $data->savedOnly,
                examKey: $data->examKey,
                articles: $data->articles,
                symptoms: $data->symptoms,
            );

            $availableCount = count($selector->questionPoolIds($user, $poolData));
            if ($availableCount === 0) {
                $validator->errors()->add('topic_ids', 'Phạm vi đã chọn không còn câu hỏi phù hợp.');
            }
        });
    }

    public function toData(): StudyPlanData
    {
        $examKey = (string) $this->string('exam_key');
        $targetDate = $this->date('exam_target_date') ?? Carbon::tomorrow();

        return new StudyPlanData(
            name: TargetExams::planName($examKey, $targetDate),
            examKey: $examKey,
            examTargetDate: $targetDate->toDateString(),
            dailyGoalQuestions: $this->integer('daily_goal_questions'),
            hoursPerDay: (float) $this->input('hours_per_day'),
            topicIds: array_values(array_unique(array_map('intval', array_merge(
                $this->input('medical_taxonomy_node_ids', []),
                $this->input('topic_ids', []),
                $this->input('system_ids', []),
                $this->input('discipline_ids', []),
            )))),
            systemIds: array_values(array_unique(array_map('intval', $this->input('system_ids', [])))),
            disciplineIds: array_values(array_unique(array_map('intval', $this->input('discipline_ids', [])))),
            studyDays: array_values(array_map('intval', $this->input('study_days', []))),
            strategy: (string) $this->string('strategy'),
            examTags: array_values(array_map('strval', $this->input('exam_tags', []))),
            articles: array_values(array_map('strval', $this->input('articles', []))),
            symptoms: array_values(array_map('strval', $this->input('symptoms', []))),
            savedOnly: $this->boolean('saved_only'),
            difficulties: array_values(array_unique(array_map('strval', $this->input('difficulties', [])))),
            questionStatuses: array_values(array_unique(array_map('strval', $this->input('question_statuses', [])))),
            questionStatusMode: (string) $this->input('question_status_mode', 'latest'),
            blueprintId: $this->filled('blueprint_id') ? $this->integer('blueprint_id') : null,
            blueprintSectionId: $this->filled('blueprint_section_id') ? $this->integer('blueprint_section_id') : null,
            coreClinicalTopicIds: array_values(array_unique(array_map('intval', $this->input('core_clinical_topic_ids', [])))),
            tagIds: array_values(array_unique(array_map('intval', $this->input('tag_ids', [])))),
            additionalTopicIds: array_values(array_unique(array_map('intval', $this->input('medical_taxonomy_node_ids', [])))),
        );
    }

    private function hasStudyDate(StudyPlanData $data): bool
    {
        $cursor = Carbon::today();
        $deadline = Carbon::parse($data->examTargetDate)->startOfDay();

        while ($cursor->lessThanOrEqualTo($deadline)) {
            if (in_array($cursor->dayOfWeekIso, $data->studyDays, true)) {
                return true;
            }

            $cursor->addDay();
        }

        return false;
    }
}
