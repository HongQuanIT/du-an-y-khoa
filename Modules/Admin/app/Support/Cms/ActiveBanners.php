<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Admin\Models\Banner;
use Modules\Admin\Support\Enums\BannerAudience;
use Modules\Admin\Support\Enums\BannerPlacement;
use Modules\Billing\Support\CurrentSubscription;

final class ActiveBanners
{
    private const CACHE_KEY = 'cms.banners.enabled.ids';

    /**
     * @return Collection<int, Banner>
     */
    public static function for(BannerPlacement $placement, ?User $user = null): Collection
    {
        $banners = self::cachedEnabled();

        return $banners
            ->filter(fn (Banner $banner): bool => $banner->placement->matches($placement))
            ->filter(fn (Banner $banner): bool => $banner->isCurrentlyScheduled())
            ->filter(fn (Banner $banner): bool => self::matchesAudience($banner->audience, $user))
            ->values();
    }

    /**
     * @return Collection<int, Banner>
     */
    private static function cachedEnabled(): Collection
    {
        if (app()->environment('testing')) {
            return Banner::query()->enabled()->ordered()->get();
        }

        /** @var list<int> $ids */
        $ids = Cache::remember(
            self::CACHE_KEY,
            300,
            static fn (): array => Banner::query()
                ->enabled()
                ->ordered()
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );

        if ($ids === []) {
            return collect();
        }

        return Banner::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(static fn (Banner $banner): int => array_search($banner->id, $ids, true) ?: 0)
            ->values();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        // Key cũ (cache cả model) gây __PHP_Incomplete_Class.
        Cache::forget('cms.banners.enabled');
    }

    private static function matchesAudience(BannerAudience $audience, ?User $user): bool
    {
        return match ($audience) {
            BannerAudience::All => true,
            BannerAudience::Guests => $user === null,
            BannerAudience::Authenticated => $user !== null,
            BannerAudience::Free => $user !== null && CurrentSubscription::for($user)['is_free'] === true,
            BannerAudience::Premium => $user !== null && CurrentSubscription::for($user)['is_free'] === false,
        };
    }
}
