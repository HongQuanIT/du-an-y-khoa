<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RenameQuestionSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
