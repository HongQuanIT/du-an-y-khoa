<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Http\Requests\Cms\SaveBannerRequest;
use Modules\Admin\Models\Banner;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\Cms\ActiveBanners;
use Modules\Admin\Support\Enums\BannerAudience;
use Modules\Admin\Support\Enums\BannerPlacement;
use Modules\Admin\Support\Enums\BannerVariant;

final class SaveBannerAction
{
    use AsAction;

    public function handle(User $actor, SaveBannerRequest $request, ?Banner $banner = null): Banner
    {
        $isNew = $banner === null;
        $banner ??= new Banner;

        $before = $isNew ? null : $banner->only([
            'title',
            'body',
            'variant',
            'placement',
            'audience',
            'is_enabled',
            'sort_order',
            'starts_at',
            'ends_at',
        ]);

        $enabled = $request->boolean('is_enabled');
        $wasEnabled = (bool) ($before['is_enabled'] ?? false);

        $banner->fill([
            'title' => trim((string) $request->validated('title')),
            'body' => trim(strip_tags((string) $request->validated('body'))),
            'cta_label' => $request->validated('cta_label'),
            'cta_url' => $request->validated('cta_url'),
            'variant' => BannerVariant::from((string) $request->validated('variant')),
            'placement' => BannerPlacement::from((string) $request->validated('placement')),
            'audience' => BannerAudience::from((string) $request->validated('audience')),
            'is_enabled' => $enabled,
            'is_dismissible' => $request->boolean('is_dismissible'),
            'sort_order' => $request->integer('sort_order') ?: ($banner->sort_order ?: Banner::nextSortOrder()),
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
        ]);

        $banner->save();
        ActiveBanners::forget();

        Auditor::record(
            match (true) {
                $isNew && $enabled => 'cms.banner.publish',
                $isNew => 'cms.banner.create',
                $enabled && ! $wasEnabled => 'cms.banner.publish',
                ! $enabled && $wasEnabled => 'cms.banner.disable',
                default => 'cms.banner.update',
            },
            $actor,
            $banner,
            $before,
            $banner->only([
                'title',
                'body',
                'variant',
                'placement',
                'audience',
                'is_enabled',
                'sort_order',
                'starts_at',
                'ends_at',
            ]),
        );

        return $banner->refresh();
    }
}
