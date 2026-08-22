<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\QuestionBank\Models\Topic;

final class SaveTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => Str::limit((string) Str::slug($slug !== '' ? $slug : $name), 191, ''),
            'order' => $this->input('order', 0),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Topic|null $topic */
        $topic = $this->route('topic');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:191', Rule::unique('topics', 'slug')->ignore($topic?->id)],
            'order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên chủ đề.',
            'slug.required' => 'Không thể tạo slug từ tên chủ đề.',
            'slug.unique' => 'Slug này đã được sử dụng.',
            'order.min' => 'Thứ tự không được nhỏ hơn 0.',
        ];
    }
}
