<?php

declare(strict_types=1);

namespace Modules\Analytics\Support;

use Illuminate\Support\Facades\Cache;

final class DashboardCache
{
    public const TTL_SECONDS = 120;

    private const VERSION = 5;

    public static function key(int $userId, string $range): string
    {
        return 'analytics:dashboard:v'.self::VERSION.":{$userId}:{$range}";
    }

    public static function forget(int $userId): void
    {
        foreach (['7d', '30d', 'all'] as $range) {
            Cache::forget(self::key($userId, $range));
        }
    }
}
