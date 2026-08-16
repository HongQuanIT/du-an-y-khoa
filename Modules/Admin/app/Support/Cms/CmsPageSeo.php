<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Media\Support\HydrateMediaUrls;

/**
 * WordPress/Yoast-style SEO bag stored in cms_pages.seo JSON.
 */
final class CmsPageSeo
{
    public const SCHEMA_WEB_PAGE = 'WebPage';

    public const SCHEMA_ABOUT_PAGE = 'AboutPage';

    public const SCHEMA_CONTACT_PAGE = 'ContactPage';

    /**
     * @return list<string>
     */
    public static function schemaTypes(): array
    {
        return [
            self::SCHEMA_WEB_PAGE,
            self::SCHEMA_ABOUT_PAGE,
            self::SCHEMA_CONTACT_PAGE,
        ];
    }

    public static function defaultSchemaType(CmsPageKey $key): string
    {
        return match ($key) {
            CmsPageKey::About => self::SCHEMA_ABOUT_PAGE,
            CmsPageKey::Contact => self::SCHEMA_CONTACT_PAGE,
            default => self::SCHEMA_WEB_PAGE,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(CmsPageKey $key): array
    {
        return [
            'meta_title' => $key->defaultTitle(),
            'meta_description' => $key->defaultSeoDescription(),
            'focus_keyword' => null,
            'meta_keywords' => null,
            'canonical_url' => null,
            'robots_index' => 'index',
            'robots_follow' => 'follow',
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_image_media_id' => null,
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'twitter_image_media_id' => null,
            'schema_type' => self::defaultSchemaType($key),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function fromInput(array $input, CmsPageKey $key): array
    {
        $defaults = self::defaults($key);

        $trim = static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }

            $text = trim(strip_tags((string) $value));

            return $text === '' ? null : $text;
        };

        $robotsIndex = (string) ($input['robots_index'] ?? 'index');
        $robotsFollow = (string) ($input['robots_follow'] ?? 'follow');
        $schemaType = (string) ($input['schema_type'] ?? self::defaultSchemaType($key));

        if (! in_array($robotsIndex, ['index', 'noindex'], true)) {
            $robotsIndex = 'index';
        }

        if (! in_array($robotsFollow, ['follow', 'nofollow'], true)) {
            $robotsFollow = 'follow';
        }

        if (! in_array($schemaType, self::schemaTypes(), true)) {
            $schemaType = self::defaultSchemaType($key);
        }

        return [
            'meta_title' => $trim($input['meta_title'] ?? null) ?? $defaults['meta_title'],
            'meta_description' => $trim($input['meta_description'] ?? null) ?? $defaults['meta_description'],
            'focus_keyword' => $trim($input['focus_keyword'] ?? null),
            'meta_keywords' => $trim($input['meta_keywords'] ?? null),
            'canonical_url' => $trim($input['canonical_url'] ?? null),
            'robots_index' => $robotsIndex,
            'robots_follow' => $robotsFollow,
            'og_title' => $trim($input['og_title'] ?? null),
            'og_description' => $trim($input['og_description'] ?? null),
            'og_image' => $trim($input['og_image'] ?? null),
            'og_image_media_id' => self::intOrNull($input['og_image_media_id'] ?? null),
            'twitter_title' => $trim($input['twitter_title'] ?? null),
            'twitter_description' => $trim($input['twitter_description'] ?? null),
            'twitter_image' => $trim($input['twitter_image'] ?? null),
            'twitter_image_media_id' => self::intOrNull($input['twitter_image_media_id'] ?? null),
            'schema_type' => $schemaType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function merged(?CmsPage $page, CmsPageKey $key): array
    {
        $defaults = self::defaults($key);
        $stored = is_array($page?->seo ?? null) ? $page->seo : [];

        return HydrateMediaUrls::apply(array_merge($defaults, array_filter(
            $stored,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'string', 'max:2048'],
            'robots_index' => ['nullable', 'string', 'in:index,noindex'],
            'robots_follow' => ['nullable', 'string', 'in:follow,nofollow'],
            'og_title' => ['nullable', 'string', 'max:95'],
            'og_description' => ['nullable', 'string', 'max:200'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'og_image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'twitter_title' => ['nullable', 'string', 'max:70'],
            'twitter_description' => ['nullable', 'string', 'max:200'],
            'twitter_image' => ['nullable', 'string', 'max:2048'],
            'twitter_image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'schema_type' => ['nullable', 'string', 'in:'.implode(',', self::schemaTypes())],
        ];
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
