<?php

declare(strict_types=1);

namespace Modules\Landing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Cms\CmsPageDefaults;
use Modules\Admin\Support\Cms\CmsPageSeo;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Admin\Support\Enums\CmsPageStatus;
use Tests\TestCase;

final class SitemapAndSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_sitemap_xml_lists_public_and_cms_urls(): void
    {
        CmsPage::query()->create([
            'key' => CmsPageKey::About,
            'slug' => 'about',
            'title' => 'Về chúng tôi',
            'content' => CmsPageDefaults::for(CmsPageKey::About),
            'seo' => CmsPageSeo::defaults(CmsPageKey::About),
            'status' => CmsPageStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee(route('landing.home'), false)
            ->assertSee(route('landing.about'), false)
            ->assertSee(route('landing.faq'), false);
    }

    public function test_sitemap_excludes_noindex_cms_pages(): void
    {
        $seo = CmsPageSeo::defaults(CmsPageKey::Terms);
        $seo['robots_index'] = 'noindex';

        CmsPage::query()->create([
            'key' => CmsPageKey::Terms,
            'slug' => 'terms',
            'title' => 'Điều khoản',
            'content' => CmsPageDefaults::for(CmsPageKey::Terms),
            'seo' => $seo,
            'status' => CmsPageStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee(route('landing.terms'), false);
    }

    public function test_robots_txt_points_to_sitemap(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertSee('User-agent: *', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_cms_page_renders_onpage_seo_tags_and_schema(): void
    {
        $seo = CmsPageSeo::defaults(CmsPageKey::About);
        $seo['meta_title'] = 'SEO About Title';
        $seo['meta_description'] = 'SEO about description for search engines.';
        $seo['focus_keyword'] = 'hoc y khoa';
        $seo['meta_keywords'] = 'y khoa, on thi';
        $seo['og_title'] = 'OG About';
        $seo['og_image'] = 'https://example.com/og-about.jpg';
        $seo['schema_type'] = CmsPageSeo::SCHEMA_ABOUT_PAGE;

        CmsPage::query()->create([
            'key' => CmsPageKey::About,
            'slug' => 'about',
            'title' => 'Về chúng tôi',
            'content' => CmsPageDefaults::for(CmsPageKey::About),
            'seo' => $seo,
            'status' => CmsPageStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('landing.about'))
            ->assertOk()
            ->assertSee('SEO About Title |', false)
            ->assertSee('<meta name="description" content="SEO about description for search engines."', false)
            ->assertSee('<meta name="keywords" content="y khoa, on thi"', false)
            ->assertSee('<meta name="robots" content="index, follow"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title" content="OG About"', false)
            ->assertSee('property="og:image" content="https://example.com/og-about.jpg"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('AboutPage', false)
            ->assertSee('Organization', false);
    }
}
