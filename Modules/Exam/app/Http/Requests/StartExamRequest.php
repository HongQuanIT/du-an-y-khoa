<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function count(): int
    {
        return max(1, min(200, $this->integer('count')));
    }
}
