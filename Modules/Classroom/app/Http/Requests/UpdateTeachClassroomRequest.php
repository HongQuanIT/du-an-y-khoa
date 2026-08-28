<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomVisibility;

final class UpdateTeachClassroomRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['required', Rule::in(array_map(
                static fn (ClassroomPurpose $purpose): string => $purpose->value,
                ClassroomPurpose::teachCases(),
            ))],
            'visibility' => ['required', Rule::enum(ClassroomVisibility::class)],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:5000'],
        ];
    }
}
