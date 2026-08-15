<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Http\Requests\Cms\SaveCmsPageRequest;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\Cms\CmsPageContentSanitizer;
use Modules\Admin\Support\Cms\CmsPageSeo;
use Modules\Admin\Support\Enums\CmsPageStatus;
use Modules\Landing\Support\ResolvedCmsPage;

final class SaveCmsPageAction
{
    use AsAction;

    public function handle(User $actor, SaveCmsPageRequest $request, CmsPage $page): CmsPage
    {
        $before = $page->only([
            'title',
            'status',
            'published_at',
            'seo',
            'content',
        ]);

        $publish = $request->input('status') === CmsPageStatus::Published->value;
        $key = $page->key;

        $content = $key !== null
            ? CmsPageContentSanitizer::sanitize($key, (array) $request->validated('content'))
            : (array) $request->validated('content');

        $seoInput = $request->safe()->only([
            'meta_title',
            'meta_description',
            'focus_keyword',
            'meta_keywords',
            'canonical_url',
            'robots_index',
            'robots_follow',
            'og_title',
            'og_description',
            'og_image',
            'twitter_title',
            'twitter_description',
            'twitter_image',
            'schema_type',
        ]);

        $page->fill([
            'title' => trim((string) $request->validated('title')),
            'content' => $content,
            'status' => CmsPageStatus::from((string) $request->validated('status')),
            'published_at' => $publish ? ($page->published_at ?? now()) : null,
            'seo' => $key !== null
                ? CmsPageSeo::fromInput($seoInput, $key)
                : $seoInput,
        ]);

        $page->save();

        if ($key !== null) {
            ResolvedCmsPage::forget($key);
        }

        $wasPublished = ($before['status'] ?? null) instanceof CmsPageStatus
            ? $before['status'] === CmsPageStatus::Published
            : ($before['status'] ?? null) === CmsPageStatus::Published->value;

        Auditor::record(
            match (true) {
                $publish && ! $wasPublished => 'cms.page.publish',
                ! $publish && $wasPublished => 'cms.page.unpublish',
                default => 'cms.page.update',
            },
            $actor,
            $page,
            $before,
            $page->only([
                'title',
                'status',
                'published_at',
                'seo',
                'content',
            ]),
        );

        return $page->refresh();
    }
}
