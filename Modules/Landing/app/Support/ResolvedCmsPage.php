<?php

declare(strict_types=1);

namespace Modules\Landing\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Cms\CmsPageContentResolver;
use Modules\Admin\Support\Enums\CmsPageKey;

final class ResolvedCmsPage
{
    public static function published(CmsPageKey $key): ?CmsPage
    {
        if (app()->environment('testing')) {
            return CmsPage::findPublished($key);
        }

        $cacheKey = 'cms.page.'.$key->value;

        $id = Cache::remember(
            $cacheKey.'.id',
            3600,
            static fn (): ?int => CmsPage::findPublished($key)?->id,
        );

        if ($id === null) {
            return null;
        }

        return CmsPage::query()->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public static function content(CmsPageKey $key): array
    {
        return CmsPageContentResolver::resolve(self::published($key), $key);
    }

    public static function forget(CmsPageKey $key): void
    {
        Cache::forget('cms.page.'.$key->value.'.id');
    }
}
