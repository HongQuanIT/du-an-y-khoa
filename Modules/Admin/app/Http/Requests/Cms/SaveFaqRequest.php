<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests\Cms;

use App\Support\Html\SafeHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Admin\Support\Enums\FaqCategory;

final class SaveFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $publish = match ($this->input('action')) {
            'publish' => true,
            'draft' => false,
            default => $this->boolean('is_published'),
        };

        $this->merge(['is_published' => $publish]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::enum(FaqCategory::class)],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:65535'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'action' => ['nullable', 'string', Rule::in(['draft', 'publish'])],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Vui lòng nhập câu hỏi.',
            'answer.required' => 'Vui lòng nhập câu trả lời.',
            'category.required' => 'Vui lòng chọn danh mục.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (SafeHtml::isBlank($this->input('answer'))) {
                $validator->errors()->add('answer', 'Vui lòng nhập câu trả lời.');
            }
        });
    }
}
