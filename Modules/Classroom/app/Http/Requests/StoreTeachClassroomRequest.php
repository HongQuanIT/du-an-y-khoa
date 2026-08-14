<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;

final class StoreTeachClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Classroom::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $teachPurposes = array_map(
            static fn (ClassroomPurpose $purpose): string => $purpose->value,
            ClassroomPurpose::teachCases(),
        );

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['required', Rule::in($teachPurposes)],
            'visibility' => ['required', Rule::enum(ClassroomVisibility::class)],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'tên lớp',
            'description' => 'mô tả',
            'purpose' => 'loại buổi',
            'visibility' => 'chế độ tham gia',
            'max_members' => 'giới hạn thành viên',
        ];
    }
}
