<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
        $this->merge([
            'difficulty' => $this->filled('difficulty') ? $this->input('difficulty') : null,
            'saved_only' => $this->boolean('saved_only'),
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
            'daily_goal_questions' => ['required', 'integer', 'min:5', 'max:200'],
            'topic_ids' => ['nullable', 'array'],
            'topic_ids.*' => ['integer', 'exists:topics,id'],
            'exam_tags' => ['nullable', 'array'],
            'exam_tags.*' => ['string', 'max:64'],
            'articles' => ['nullable', 'array'],
            'articles.*' => ['string', 'max:120'],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', 'max:120'],
            'saved_only' => ['nullable', 'boolean'],
            'difficulty' => ['nullable', 'string', 'in:easy,medium,hard'],
            'question_statuses' => ['nullable', 'array'],
            'question_statuses.*' => ['string', 'in:unanswered,correct_with_hints,incorrect,correct'],
            'question_status_mode' => ['nullable', 'string', 'in:all,latest'],
            'study_days' => ['required', 'array', 'min:1'],
            'study_days.*' => ['integer', 'between:1,7'],
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
        ];
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
            topicIds: array_values(array_map('intval', $this->input('topic_ids', []))),
            studyDays: array_values(array_map('intval', $this->input('study_days', []))),
            strategy: (string) $this->string('strategy'),
            examTags: array_values(array_map('strval', $this->input('exam_tags', []))),
            articles: array_values(array_map('strval', $this->input('articles', []))),
            symptoms: array_values(array_map('strval', $this->input('symptoms', []))),
            savedOnly: $this->boolean('saved_only'),
            difficulty: filled($this->input('difficulty')) ? (string) $this->input('difficulty') : null,
            questionStatuses: array_values(array_unique(array_map('strval', $this->input('question_statuses', [])))),
            questionStatusMode: (string) $this->input('question_status_mode', 'latest'),
        );
    }
}
