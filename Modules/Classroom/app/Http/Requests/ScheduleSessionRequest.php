<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Requests;

use App\Support\Enums\Entitlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\QuestionBank\Enums\QuestionStatus;
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
            'expected_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'qbank_session_id' => [
                'nullable',
                'uuid',
                Rule::exists('question_sessions', 'id')->where(
                    fn ($q) => $q->where('user_id', $this->user()?->getKey())
                        ->where('status', SessionStatus::Completed->value),
                ),
            ],
            'question_ids' => ['nullable', 'array', 'max:50'],
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

        if (isset($data['expected_duration_minutes'])) {
            $data['expected_duration_seconds'] = (int) $data['expected_duration_minutes'] * 60;
            unset($data['expected_duration_minutes']);
        }

        return $data;
    }
}
