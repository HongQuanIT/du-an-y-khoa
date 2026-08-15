<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Models\Banner;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\Cms\ActiveBanners;

final class ToggleBannerAction
{
    use AsAction;

    public function handle(User $actor, Banner $banner): Banner
    {
        $before = $banner->only(['is_enabled']);
        $banner->is_enabled = ! $banner->is_enabled;
        $banner->save();
        ActiveBanners::forget();

        Auditor::record(
            $banner->is_enabled ? 'cms.banner.publish' : 'cms.banner.disable',
            $actor,
            $banner,
            $before,
            $banner->only(['is_enabled']),
        );

        return $banner->refresh();
    }
}
