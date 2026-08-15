<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Admin\Models\Menu;
use Modules\Admin\Support\Cms\MenuLinkRules;
use Modules\Admin\Support\Enums\MenuKey;

final class SaveMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $normalizeLink = static function (mixed $link): ?array {
            if (! is_array($link)) {
                return null;
            }

            return [
                'label' => (string) ($link['label'] ?? ''),
                'type' => (string) ($link['type'] ?? 'url'),
                'value' => (string) ($link['value'] ?? ''),
                'enabled' => filter_var($link['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        };

        if (isset($items['links']) && is_array($items['links'])) {
            $items['links'] = array_values(array_filter(array_map($normalizeLink, $items['links'])));
        }

        if (isset($items['bottom_links']) && is_array($items['bottom_links'])) {
            $items['bottom_links'] = array_values(array_filter(array_map($normalizeLink, $items['bottom_links'])));
        }

        if (isset($items['columns']) && is_array($items['columns'])) {
            $items['columns'] = array_values(array_map(
                static function (mixed $column) use ($normalizeLink): array {
                    $column = is_array($column) ? $column : [];
                    $links = is_array($column['links'] ?? null) ? $column['links'] : [];

                    return [
                        'title' => (string) ($column['title'] ?? ''),
                        'links' => array_values(array_filter(array_map($normalizeLink, $links))),
                    ];
                },
                $items['columns'],
            ));
        }

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Menu $menu */
        $menu = $this->route('menu');
        $key = $menu->key ?? MenuKey::Header;

        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array'],
        ], MenuLinkRules::validationRules($key));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Menu $menu */
            $menu = $this->route('menu');
            $key = $menu->key ?? MenuKey::Header;
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            $this->assertLinks($validator, $key, $items);
        });
    }

    /**
     * @param  \Illuminate\Validation\Validator  $validator
     * @param  array<string, mixed>  $items
     */
    private function assertLinks($validator, MenuKey $key, array $items): void
    {
        $check = function (string $path, array $link) use ($validator): void {
            $type = (string) ($link['type'] ?? '');
            $value = (string) ($link['value'] ?? '');

            if ($type === 'route' && ! in_array($value, MenuLinkRules::allowedRoutes(), true)) {
                $validator->errors()->add($path.'.value', 'Route không nằm trong danh sách cho phép.');
            }

            if ($type === 'url' && ! MenuLinkRules::isSafeUrl($value)) {
                $validator->errors()->add($path.'.value', 'URL không hợp lệ (chỉ http/https, đường dẫn /, #, mailto, tel).');
            }
        };

        if ($key === MenuKey::Header) {
            foreach (is_array($items['links'] ?? null) ? $items['links'] : [] as $i => $link) {
                if (is_array($link)) {
                    $check("items.links.$i", $link);
                }
            }

            return;
        }

        foreach (is_array($items['columns'] ?? null) ? $items['columns'] : [] as $c => $column) {
            foreach (is_array($column['links'] ?? null) ? $column['links'] : [] as $i => $link) {
                if (is_array($link)) {
                    $check("items.columns.$c.links.$i", $link);
                }
            }
        }

        foreach (is_array($items['bottom_links'] ?? null) ? $items['bottom_links'] : [] as $i => $link) {
            if (is_array($link)) {
                $check("items.bottom_links.$i", $link);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên menu.',
            'items.required' => 'Vui lòng cấu hình nội dung menu.',
            'items.links.min' => 'Menu header cần ít nhất 1 liên kết.',
        ];
    }
}
