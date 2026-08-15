<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Modules\Admin\Support\Enums\CmsPageKey;

final class CmsPageContentSanitizer
{
    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function sanitize(CmsPageKey $key, array $content): array
    {
        return self::walk($content);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private static function walk(array $content): array
    {
        $sanitized = [];

        foreach ($content as $field => $value) {
            if (is_array($value)) {
                $sanitized[$field] = self::walk($value);
            } elseif (is_string($value)) {
                $sanitized[$field] = trim(strip_tags($value));
            } else {
                $sanitized[$field] = $value;
            }
        }

        return $sanitized;
    }
}
