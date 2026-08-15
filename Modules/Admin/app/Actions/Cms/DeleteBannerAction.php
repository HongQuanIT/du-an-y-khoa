<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Models\Banner;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\Cms\ActiveBanners;

final class DeleteBannerAction
{
    use AsAction;

    public function handle(User $actor, Banner $banner): void
    {
        $before = $banner->only(['title', 'is_enabled', 'placement', 'audience']);

        $banner->delete();
        ActiveBanners::forget();

        Auditor::record('cms.banner.delete', $actor, null, $before, null);
    }
}
