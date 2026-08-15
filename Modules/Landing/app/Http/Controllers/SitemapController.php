<?php

declare(strict_types=1);

namespace Modules\Landing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Admin\Support\Enums\CmsPageStatus;

final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $now = now()->toAtomString();

        $urls = [
            ['loc' => route('landing.home'), 'changefreq' => 'weekly', 'priority' => '1.0', 'lastmod' => $now],
            ['loc' => route('landing.features'), 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $now],
            ['loc' => route('landing.pricing'), 'changefreq' => 'weekly', 'priority' => '0.9', 'lastmod' => $now],
            ['loc' => route('landing.faq'), 'changefreq' => 'weekly', 'priority' => '0.7', 'lastmod' => $now],
        ];

        $cmsPages = CmsPage::query()
            ->where('status', CmsPageStatus::Published->value)
            ->get()
            ->keyBy(fn (CmsPage $page): string => $page->key?->value ?? '');

        foreach (CmsPageKey::cases() as $key) {
            // Home/Features already listed above — avoid duplicate URLs.
            if ($key->alwaysPublic()) {
                continue;
            }

            $page = $cmsPages->get($key->value);

            if ($page === null) {
                continue;
            }

            $seo = is_array($page->seo) ? $page->seo : [];

            if (($seo['robots_index'] ?? 'index') === 'noindex') {
                continue;
            }

            $lastmod = $page->updated_at instanceof Carbon
                ? $page->updated_at->toAtomString()
                : $now;

            $urls[] = [
                'loc' => route($key->routeName()),
                'changefreq' => 'monthly',
                'priority' => match ($key) {
                    CmsPageKey::About, CmsPageKey::Contact => '0.8',
                    default => '0.5',
                },
                'lastmod' => $lastmod,
            ];
        }

        return response($this->toXml($urls), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @param  list<array{loc: string, lastmod: string, changefreq: string, priority: string}>  $urls
     */
    private function toXml(array $urls): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.e($url['loc']).'</loc>';
            $lines[] = '    <lastmod>'.e($url['lastmod']).'</lastmod>';
            $lines[] = '    <changefreq>'.e($url['changefreq']).'</changefreq>';
            $lines[] = '    <priority>'.e($url['priority']).'</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
