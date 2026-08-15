<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Cms\CmsPageDefaults;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Admin\Support\Enums\CmsPageStatus;

final class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        CmsPage::syncCatalog();

        $now = now();

        foreach (CmsPageKey::cases() as $key) {
            $page = CmsPage::query()->where('key', $key->value)->first();

            if ($page === null) {
                continue;
            }

            $page->update([
                'content' => CmsPageDefaults::for($key),
                'status' => CmsPageStatus::Published,
                'published_at' => $page->published_at ?? $now,
            ]);
        }
    }
}
