<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RepeatQuestionSessionRequest extends FormRequest
{
    public const STATUSES = ['unanswered', 'correct_with_hints', 'incorrect', 'correct'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'repeat_statuses' => ['required', 'array', 'min:1'],
            'repeat_statuses.*' => ['string', 'distinct', Rule::in(self::STATUSES)],
            'question_count' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
