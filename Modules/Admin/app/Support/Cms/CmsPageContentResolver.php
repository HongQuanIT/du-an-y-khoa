<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Media\Support\HydrateMediaUrls;

final class CmsPageContentResolver
{
    /**
     * @return array<string, mixed>
     */
    public static function resolve(?CmsPage $page, CmsPageKey $key): array
    {
        $defaults = CmsPageDefaults::for($key);
        $stored = [];

        if ($page !== null && array_key_exists('content', $page->getAttributes())) {
            $value = $page->getAttribute('content');
            $stored = is_array($value) ? $value : [];
        }

        return HydrateMediaUrls::apply(self::mergeRecursive($defaults, $stored));
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private static function mergeRecursive(array $defaults, array $stored): array
    {
        $merged = $defaults;

        foreach ($stored as $key => $value) {
            if (! is_array($value) || ! isset($merged[$key]) || ! is_array($merged[$key])) {
                $merged[$key] = $value;

                continue;
            }

            if (self::isList($merged[$key])) {
                $merged[$key] = self::mergeList($merged[$key], $value);
            } else {
                $merged[$key] = self::mergeRecursive($merged[$key], $value);
            }
        }

        return $merged;
    }

    /**
     * @param  array<int, mixed>  $defaults
     * @param  array<int, mixed>  $stored
     * @return array<int, mixed>
     */
    private static function mergeList(array $defaults, array $stored): array
    {
        $result = $defaults;

        foreach ($stored as $index => $item) {
            if (! array_key_exists($index, $result)) {
                continue;
            }

            if (is_array($item) && is_array($result[$index])) {
                $result[$index] = self::isAssoc($item)
                    ? self::mergeRecursive($result[$index], $item)
                    : $item;
            } else {
                $result[$index] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $array
     */
    private static function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * @param  array<mixed>  $array
     */
    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
