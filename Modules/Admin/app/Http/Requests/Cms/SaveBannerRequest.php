<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Admin\Support\Enums\BannerAudience;
use Modules\Admin\Support\Enums\BannerPlacement;
use Modules\Admin\Support\Enums\BannerVariant;

final class SaveBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var \Modules\Admin\Models\Banner|null $existing */
        $existing = $this->route('banner');

        $enabled = match ($this->input('action')) {
            'enable', 'publish' => true,
            'disable', 'draft' => false,
            'save' => $existing?->is_enabled ?? false,
            default => $this->boolean('is_enabled'),
        };

        $this->merge([
            'is_enabled' => $enabled,
            'is_dismissible' => $this->boolean('is_dismissible'),
            'cta_label' => $this->filled('cta_label') ? trim((string) $this->input('cta_label')) : null,
            'cta_url' => $this->filled('cta_url') ? trim((string) $this->input('cta_url')) : null,
            'starts_at' => $this->filled('starts_at') ? $this->input('starts_at') : null,
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1000'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'variant' => ['required', 'string', Rule::enum(BannerVariant::class)],
            'placement' => ['required', 'string', Rule::enum(BannerPlacement::class)],
            'audience' => ['required', 'string', Rule::enum(BannerAudience::class)],
            'is_enabled' => ['required', 'boolean'],
            'is_dismissible' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'action' => ['nullable', 'string', Rule::in(['save', 'enable', 'disable', 'publish', 'draft'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề nội bộ.',
            'body.required' => 'Vui lòng nhập nội dung banner.',
            'ends_at.after_or_equal' => 'Thời gian kết thúc phải sau hoặc bằng thời gian bắt đầu.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $label = $this->input('cta_label');
            $url = $this->input('cta_url');

            if (filled($label) && blank($url)) {
                $validator->errors()->add('cta_url', 'Nhập URL khi có nhãn nút CTA.');
            }

            if (filled($url) && blank($label)) {
                $validator->errors()->add('cta_label', 'Nhập nhãn nút khi có URL CTA.');
            }
        });
    }
}
