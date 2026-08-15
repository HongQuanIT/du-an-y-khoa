<?php

declare(strict_types=1);

namespace Modules\Landing\Support;

use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Cms\CmsPageSeo;
use Modules\Admin\Support\Enums\CmsPageKey;

/**
 * Resolves on-page SEO tags + JSON-LD for public Blade layout (Yoast-style).
 */
final class PublicSeo
{
    /**
     * @return array<string, mixed>
     */
    public static function forCms(CmsPageKey $key, ?CmsPage $page = null): array
    {
        $page ??= ResolvedCmsPage::published($key);
        $seo = CmsPageSeo::merged($page, $key);
        $title = trim((string) ($seo['meta_title'] ?? '')) ?: ($page?->title ?? $key->defaultTitle());
        $description = trim((string) ($seo['meta_description'] ?? '')) ?: $key->defaultSeoDescription();
        $url = self::absoluteUrl($seo['canonical_url'] ?? null) ?? route($key->routeName());

        return self::build(
            title: $title,
            description: $description,
            url: $url,
            seo: $seo,
            schemaType: (string) ($seo['schema_type'] ?? CmsPageSeo::defaultSchemaType($key)),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function forStatic(
        string $title,
        string $description,
        string $routeName,
        string $schemaType = CmsPageSeo::SCHEMA_WEB_PAGE,
        array $overrides = [],
    ): array {
        return self::build(
            title: $title,
            description: $description,
            url: route($routeName),
            seo: $overrides,
            schemaType: $schemaType,
        );
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    private static function build(
        string $title,
        string $description,
        string $url,
        array $seo,
        string $schemaType,
    ): array {
        $appName = (string) config('app.name');
        $documentTitle = $title === '' ? $appName : $title.' | '.$appName;

        $ogTitle = trim((string) ($seo['og_title'] ?? '')) ?: $title;
        $ogDescription = trim((string) ($seo['og_description'] ?? '')) ?: $description;
        $ogImage = self::absoluteUrl($seo['og_image'] ?? null)
            ?? self::absoluteUrl(config('app.og_image'))
            ?? null;

        $twitterTitle = trim((string) ($seo['twitter_title'] ?? '')) ?: $ogTitle;
        $twitterDescription = trim((string) ($seo['twitter_description'] ?? '')) ?: $ogDescription;
        $twitterImage = self::absoluteUrl($seo['twitter_image'] ?? null) ?? $ogImage;

        $robotsIndex = ($seo['robots_index'] ?? 'index') === 'noindex' ? 'noindex' : 'index';
        $robotsFollow = ($seo['robots_follow'] ?? 'follow') === 'nofollow' ? 'nofollow' : 'follow';

        $keywords = trim((string) ($seo['meta_keywords'] ?? ''));
        $focus = trim((string) ($seo['focus_keyword'] ?? ''));
        if ($keywords === '' && $focus !== '') {
            $keywords = $focus;
        }

        return [
            'document_title' => $documentTitle,
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords !== '' ? $keywords : null,
            'focus_keyword' => $focus !== '' ? $focus : null,
            'canonical' => $url,
            'robots' => $robotsIndex.', '.$robotsFollow,
            'og_type' => 'website',
            'og_site_name' => $appName,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image' => $ogImage,
            'og_url' => $url,
            'og_locale' => str_replace('_', '-', app()->getLocale()),
            'twitter_card' => $twitterImage ? 'summary_large_image' : 'summary',
            'twitter_title' => $twitterTitle,
            'twitter_description' => $twitterDescription,
            'twitter_image' => $twitterImage,
            'json_ld' => self::jsonLd(
                title: $title,
                description: $description,
                url: $url,
                schemaType: $schemaType,
                image: $ogImage,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function jsonLd(
        string $title,
        string $description,
        string $url,
        string $schemaType,
        ?string $image,
    ): array {
        $appName = (string) config('app.name');
        $home = url('/');
        $orgId = $home.'#organization';
        $websiteId = $home.'#website';
        $pageId = $url.'#webpage';

        $organization = [
            '@type' => 'Organization',
            '@id' => $orgId,
            'name' => $appName,
            'url' => $home,
        ];

        $logo = self::absoluteUrl(config('app.logo_url'));
        if ($logo !== null) {
            $organization['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo,
            ];
        }

        $website = [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => $home,
            'name' => $appName,
            'publisher' => ['@id' => $orgId],
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ];

        $webPage = [
            '@type' => $schemaType,
            '@id' => $pageId,
            'url' => $url,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $orgId],
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ];

        if ($image !== null) {
            $webPage['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $image,
            ];
            $webPage['image'] = $image;
        }

        return [
            [
                '@context' => 'https://schema.org',
                '@graph' => [$organization, $website, $webPage],
            ],
        ];
    }

    private static function absoluteUrl(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $url = trim((string) $value);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return url($url);
    }
}
