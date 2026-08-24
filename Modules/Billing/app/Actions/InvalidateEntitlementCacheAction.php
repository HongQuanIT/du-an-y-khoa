<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Cache;

final class InvalidateEntitlementCacheAction
{
    use AsAction;

    public function handle(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    public function cacheKey(int $userId): string
    {
        return 'billing:entitlements:'.$userId;
    }
}
