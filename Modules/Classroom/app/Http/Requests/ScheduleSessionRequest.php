<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Requests;

use App\Support\Enums\Entitlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Models\Classroom;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;

final class ScheduleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');

        return $classroom instanceof Classroom
            && ($this->user()?->can('manageLive', $classroom) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $classroom = $this->route('classroom');
        $isExamReview = $classroom instanceof Classroom
            && $classroom->purpose === ClassroomPurpose::ExamReview;

        return [
            'title' => ['required', 'string', 'max:200'],
            'scheduled_at' => ['nullable', 'date'],
            'expected_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'qbank_session_id' => [
                'nullable',
                Rule::prohibitedIf($isExamReview),
                'uuid',
                Rule::exists('question_sessions', 'id')->where(
                    fn ($q) => $q->where('user_id', $this->user()?->getKey())
                        ->where('status', SessionStatus::Completed->value),
                ),
            ],
            'exam_id' => [
                Rule::requiredIf($isExamReview),
                Rule::prohibitedIf(! $isExamReview),
                'integer',
                Rule::exists('exams', 'id')->where('status', ExamStatus::Published->value),
            ],
            'question_ids' => ['nullable', Rule::prohibitedIf($isExamReview), 'array'],
            'question_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('questions', 'id')->where(function ($query): void {
                    $query->where('status', QuestionStatus::Published->value);
                    if (! $this->user()?->hasEntitlement(Entitlement::QbankFull->value)) {
                        $query->where('is_free', true);
                    }
                }),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function sessionPayload(): array
    {
        $data = $this->validated();
        $questionIds = [];

        if (! empty($data['exam_id'])) {
            $exam = Exam::query()
                ->with(['questions' => fn ($query) => $query->where('status', QuestionStatus::Published->value)])
                ->findOrFail($data['exam_id']);
            $questionIds = $exam->questions->modelKeys();
            $data['linked_exam_id'] = $exam->getKey();
            $data['question_set'] = [
                'source' => 'exam',
                'exam_id' => $exam->getKey(),
                'question_ids' => array_values(array_map('strval', $questionIds)),
            ];
        } elseif (! empty($data['qbank_session_id'])) {
            $session = QuestionSession::query()->find($data['qbank_session_id']);
            $questionIds = array_values(array_map('strval', $session?->question_ids ?? []));
            $data['question_set'] = [
                'source' => 'qbank_session',
                'qbank_session_id' => (string) $data['qbank_session_id'],
                'question_ids' => $questionIds,
            ];
        } elseif (! empty($data['question_ids'])) {
            $data['question_set'] = [
                'source' => 'manual',
                'question_ids' => array_values(array_unique(array_map('strval', $data['question_ids']))),
            ];
        }

        unset($data['exam_id'], $data['qbank_session_id'], $data['question_ids']);

        if (isset($data['expected_duration_minutes'])) {
            $data['expected_duration_seconds'] = (int) $data['expected_duration_minutes'] * 60;
            unset($data['expected_duration_minutes']);
        }

        return $data;
    }
}
