<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Illuminate\Support\Facades\Route;
use Modules\Admin\Support\Enums\MenuKey;

/** Allowed public route names + URL sanitization for CMS menus. */
final class MenuLinkRules
{
    /**
     * @return list<string>
     */
    public static function allowedRoutes(): array
    {
        return [
            'landing.home',
            'landing.features',
            'landing.pricing',
            'landing.about',
            'landing.contact',
            'landing.faq',
            'landing.terms',
            'landing.privacy',
            'sitemap',
            'login',
            'register',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function routeOptions(): array
    {
        $labels = [
            'landing.home' => 'Trang chủ (/)',
            'landing.features' => 'Tính năng (/features)',
            'landing.pricing' => 'Bảng giá (/pricing)',
            'landing.about' => 'Về chúng tôi (/about)',
            'landing.contact' => 'Liên hệ (/contact)',
            'landing.faq' => 'FAQ (/faq)',
            'landing.terms' => 'Điều khoản (/terms)',
            'landing.privacy' => 'Bảo mật (/privacy)',
            'sitemap' => 'Sitemap (/sitemap.xml)',
            'login' => 'Đăng nhập (/login)',
            'register' => 'Đăng ký (/register)',
        ];

        $options = [];
        foreach (self::allowedRoutes() as $route) {
            if (! Route::has($route)) {
                continue;
            }
            $options[$route] = $labels[$route] ?? $route;
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(MenuKey $key): array
    {
        $linkRules = [
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:route,url'],
            'value' => ['required', 'string', 'max:2048'],
            'enabled' => ['sometimes', 'boolean'],
        ];

        return match ($key) {
            MenuKey::Header => [
                'items.links' => ['required', 'array', 'min:1', 'max:20'],
                'items.links.*.label' => $linkRules['label'],
                'items.links.*.type' => $linkRules['type'],
                'items.links.*.value' => $linkRules['value'],
                'items.links.*.enabled' => $linkRules['enabled'],
            ],
            MenuKey::Footer => [
                'items.brand_blurb' => ['required', 'string', 'max:1000'],
                'items.columns' => ['required', 'array', 'min:1', 'max:6'],
                'items.columns.*.title' => ['required', 'string', 'max:120'],
                'items.columns.*.links' => ['required', 'array', 'min:1', 'max:12'],
                'items.columns.*.links.*.label' => $linkRules['label'],
                'items.columns.*.links.*.type' => $linkRules['type'],
                'items.columns.*.links.*.value' => $linkRules['value'],
                'items.columns.*.links.*.enabled' => $linkRules['enabled'],
                'items.bottom_links' => ['required', 'array', 'min:0', 'max:8'],
                'items.bottom_links.*.label' => $linkRules['label'],
                'items.bottom_links.*.type' => $linkRules['type'],
                'items.bottom_links.*.value' => $linkRules['value'],
                'items.bottom_links.*.enabled' => $linkRules['enabled'],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    public static function sanitize(MenuKey $key, array $items): array
    {
        return match ($key) {
            MenuKey::Header => [
                'links' => self::sanitizeLinks($items['links'] ?? []),
            ],
            MenuKey::Footer => [
                'brand_blurb' => trim(strip_tags((string) ($items['brand_blurb'] ?? ''))),
                'columns' => array_values(array_map(
                    static function (mixed $column): array {
                        $column = is_array($column) ? $column : [];

                        return [
                            'title' => trim(strip_tags((string) ($column['title'] ?? ''))),
                            'links' => self::sanitizeLinks($column['links'] ?? []),
                        ];
                    },
                    is_array($items['columns'] ?? null) ? $items['columns'] : [],
                )),
                'bottom_links' => self::sanitizeLinks($items['bottom_links'] ?? []),
            ],
        };
    }

    /**
     * @param  mixed  $links
     * @return list<array{label: string, type: string, value: string, enabled: bool}>
     */
    public static function sanitizeLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $allowed = self::allowedRoutes();
        $result = [];

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $label = trim(strip_tags((string) ($link['label'] ?? '')));
            $type = (string) ($link['type'] ?? 'url');
            $value = trim(strip_tags((string) ($link['value'] ?? '')));
            $enabled = filter_var($link['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if ($label === '' || $value === '') {
                continue;
            }

            if ($type === 'route') {
                if (! in_array($value, $allowed, true) || ! Route::has($value)) {
                    continue;
                }
            } else {
                $type = 'url';
                if (! self::isSafeUrl($value)) {
                    continue;
                }
            }

            $result[] = [
                'label' => mb_substr($label, 0, 120),
                'type' => $type,
                'value' => mb_substr($value, 0, 2048),
                'enabled' => $enabled,
            ];
        }

        return $result;
    }

    public static function isSafeUrl(string $url): bool
    {
        if ($url === '#' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return true;
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && (str_starts_with($url, 'https://') || str_starts_with($url, 'http://'));
    }

    /**
     * @param  array{label: string, type: string, value: string, enabled?: bool}  $link
     * @return array{label: string, href: string, route: ?string}|null
     */
    public static function resolve(array $link): ?array
    {
        if (! ($link['enabled'] ?? true)) {
            return null;
        }

        $label = trim((string) ($link['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $type = (string) ($link['type'] ?? 'url');
        $value = trim((string) ($link['value'] ?? ''));

        if ($type === 'route') {
            if (! in_array($value, self::allowedRoutes(), true) || ! Route::has($value)) {
                return null;
            }

            return [
                'label' => $label,
                'href' => route($value),
                'route' => $value,
            ];
        }

        if (! self::isSafeUrl($value)) {
            return null;
        }

        return [
            'label' => $label,
            'href' => $value,
            'route' => null,
        ];
    }
}
