<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Illuminate\Support\Facades\Cache;
use Modules\Admin\Models\Menu;
use Modules\Admin\Support\Enums\MenuKey;

/**
 * Resolves public navigation from CMS menus (plain arrays only — no Eloquent in cache).
 */
final class ResolvedMenu
{
    /**
     * @return list<array{label: string, href: string, route: ?string}>
     */
    public static function headerLinks(): array
    {
        $items = self::items(MenuKey::Header);
        $links = is_array($items['links'] ?? null) ? $items['links'] : [];

        return self::mapLinks($links);
    }

    /**
     * @return array{
     *     brand_blurb: string,
     *     columns: list<array{title: string, links: list<array{label: string, href: string, route: ?string}>}>,
     *     bottom_links: list<array{label: string, href: string, route: ?string}>
     * }
     */
    public static function footer(): array
    {
        $items = self::items(MenuKey::Footer);
        $columns = [];

        foreach (is_array($items['columns'] ?? null) ? $items['columns'] : [] as $column) {
            if (! is_array($column)) {
                continue;
            }

            $title = trim((string) ($column['title'] ?? ''));
            $links = self::mapLinks(is_array($column['links'] ?? null) ? $column['links'] : []);

            if ($title === '' || $links === []) {
                continue;
            }

            $columns[] = [
                'title' => $title,
                'links' => $links,
            ];
        }

        return [
            'brand_blurb' => trim((string) ($items['brand_blurb'] ?? '')),
            'columns' => $columns,
            'bottom_links' => self::mapLinks(is_array($items['bottom_links'] ?? null) ? $items['bottom_links'] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function items(MenuKey $key): array
    {
        if (app()->environment('testing')) {
            return self::loadItems($key);
        }

        /** @var array<string, mixed> $cached */
        $cached = Cache::remember(
            self::cacheKey($key),
            3600,
            static fn (): array => self::loadItems($key),
        );

        return $cached;
    }

    public static function forget(?MenuKey $key = null): void
    {
        if ($key !== null) {
            Cache::forget(self::cacheKey($key));

            return;
        }

        foreach (MenuKey::cases() as $case) {
            Cache::forget(self::cacheKey($case));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadItems(MenuKey $key): array
    {
        Menu::syncCatalog();

        $menu = Menu::findByKey($key);

        if ($menu === null) {
            return MenuDefaults::for($key);
        }

        return $menu->resolvedItems();
    }

    private static function cacheKey(MenuKey $key): string
    {
        return 'cms.menu.'.$key->value.'.items';
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array{label: string, href: string, route: ?string}>
     */
    private static function mapLinks(array $links): array
    {
        $resolved = [];

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $item = MenuLinkRules::resolve($link);
            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }
}
