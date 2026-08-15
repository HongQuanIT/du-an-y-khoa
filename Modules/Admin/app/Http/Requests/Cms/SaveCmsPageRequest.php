<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Cms\CmsPageContentRules;
use Modules\Admin\Support\Cms\CmsPageSeo;
use Modules\Admin\Support\Enums\CmsPageStatus;

final class SaveCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var CmsPage $page */
        $page = $this->route('cmsPage');

        $status = match ($this->input('action')) {
            'publish' => CmsPageStatus::Published->value,
            'unpublish', 'draft' => CmsPageStatus::Draft->value,
            'save' => $page->status?->value ?? CmsPageStatus::Draft->value,
            default => $this->input('status') === CmsPageStatus::Published->value
                ? CmsPageStatus::Published->value
                : CmsPageStatus::Draft->value,
        };

        $this->merge([
            'status' => $status,
            'robots_index' => $this->input('robots_index', 'index'),
            'robots_follow' => $this->input('robots_follow', 'follow'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CmsPage $page */
        $page = $this->route('cmsPage');

        return array_merge([
            'title' => ['required', 'string', 'max:255'],
            'action' => ['nullable', 'string', Rule::in(['save', 'draft', 'publish', 'unpublish'])],
            'status' => ['required', 'string', Rule::enum(CmsPageStatus::class)],
            'content' => ['required', 'array'],
        ], CmsPageSeo::validationRules(), CmsPageContentRules::for($page->key));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề trang.',
            'content.required' => 'Vui lòng nhập nội dung trang.',
            'meta_title.max' => 'Meta title nên tối đa 70 ký tự (chuẩn SEO).',
            'meta_description.max' => 'Meta description nên tối đa 160 ký tự (chuẩn SEO).',
        ];
    }
}
