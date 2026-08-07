<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;

final class ScheduleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'scheduled_at' => ['nullable', 'date'],
            'qbank_session_id' => [
                'nullable',
                'uuid',
                Rule::exists('question_sessions', 'id')->where(
                    fn ($q) => $q->where('user_id', $this->user()?->getKey())
                        ->where('status', SessionStatus::Completed->value),
                ),
            ],
            'question_ids' => ['nullable', 'array', 'max:50'],
            'question_ids.*' => ['uuid', 'exists:questions,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function sessionPayload(): array
    {
        $data = $this->validated();
        $questionIds = [];

        if (! empty($data['qbank_session_id'])) {
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

        unset($data['qbank_session_id'], $data['question_ids']);

        return $data;
    }
}
