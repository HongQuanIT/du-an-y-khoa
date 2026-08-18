<?php

declare(strict_types=1);

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alt' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'credit' => ['nullable', 'string', 'max:255'],
            'is_premium' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alt.required' => 'Vui lòng nhập mô tả ảnh (alt) — bắt buộc cho accessibility.',
        ];
    }
}
