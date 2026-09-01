<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Snapshot báo cáo admin — cron warm theo setting, viewer đọc cache.
 */
final class AdminReportCache
{
    /** Fallback TTL khi chưa có setting (2 ngày). */
    public const TTL_SECONDS = 172800;

    public static function key(string $category, string $report, string $range): string
    {
        return sprintf('admin:report:%s:%s:%s', $category, $report, $range);
    }

    public static function metaKey(): string
    {
        return 'admin:report:cache:meta';
    }

    /** Số ngày giữa các lần warm cache tự động (mặc định 1 ngày). */
    public static function warmIntervalDays(): int
    {
        try {
            $days = (int) app(SettingService::class)->get('reports.cache_warm_interval_days', 1);
        } catch (\Throwable) {
            $days = 1;
        }

        return max(1, min(30, $days));
    }

    public static function ttlSeconds(): int
    {
        // TTL dài hơn chu kỳ warm một chút để tránh miss trước lần warm kế tiếp.
        return (self::warmIntervalDays() * 86400) + 3600;
    }

    public static function shouldAutoWarm(?Carbon $now = null): bool
    {
        $now ??= now();
        $meta = self::meta();
        $warmedAt = $meta['warmed_at'];

        if ($warmedAt === null) {
            return true;
        }

        return $warmedAt->lte($now->copy()->subDays(self::warmIntervalDays()));
    }

    /**
     * @return array{
     *     range: string,
     *     from: string,
     *     to: string,
     *     kpis: list<mixed>,
     *     charts: list<mixed>,
     *     columns: list<mixed>,
     *     rows: list<mixed>,
     *     empty_message: ?string,
     *     cached_at: string,
     * }|null
     */
    public static function get(string $category, string $report, string $range): ?array
    {
        $cached = Cache::get(self::key($category, $report, $range));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array{
     *     range: string,
     *     from: Carbon,
     *     to: Carbon,
     *     kpis: list<mixed>,
     *     charts: list<mixed>,
     *     columns: list<mixed>,
     *     rows: list<mixed>,
     *     empty_message: ?string,
     * }  $payload
     */
    public static function put(string $category, string $report, string $range, array $payload): void
    {
        $stored = [
            'range' => $payload['range'],
            'from' => $payload['from']->toIso8601String(),
            'to' => $payload['to']->toIso8601String(),
            'kpis' => $payload['kpis'],
            'charts' => $payload['charts'],
            'columns' => $payload['columns'],
            'rows' => $payload['rows'],
            'empty_message' => $payload['empty_message'] ?? null,
            'cached_at' => now()->toIso8601String(),
        ];

        Cache::put(self::key($category, $report, $range), $stored, self::ttlSeconds());
    }

    public static function forget(string $category, string $report, string $range): void
    {
        Cache::forget(self::key($category, $report, $range));
    }

    public static function statusKey(string $category, string $report, string $range): string
    {
        return sprintf('admin:report:refresh:%s:%s:%s', $category, $report, $range);
    }

    /**
     * @return array{
     *     status: 'idle'|'queued'|'processing'|'ready'|'failed',
     *     queued_at: ?string,
     *     started_at: ?string,
     *     finished_at: ?string,
     *     error: ?string,
     *     requested_by: ?int,
     * }
     */
    public static function refreshStatus(string $category, string $report, string $range): array
    {
        $raw = Cache::get(self::statusKey($category, $report, $range));

        if (! is_array($raw)) {
            return [
                'status' => 'idle',
                'queued_at' => null,
                'started_at' => null,
                'finished_at' => null,
                'error' => null,
                'requested_by' => null,
            ];
        }

        return [
            'status' => (string) ($raw['status'] ?? 'idle'),
            'queued_at' => isset($raw['queued_at']) ? (string) $raw['queued_at'] : null,
            'started_at' => isset($raw['started_at']) ? (string) $raw['started_at'] : null,
            'finished_at' => isset($raw['finished_at']) ? (string) $raw['finished_at'] : null,
            'error' => isset($raw['error']) ? (string) $raw['error'] : null,
            'requested_by' => isset($raw['requested_by']) ? (int) $raw['requested_by'] : null,
        ];
    }

    public static function markRefreshQueued(string $category, string $report, string $range, ?int $userId = null): void
    {
        Cache::put(self::statusKey($category, $report, $range), [
            'status' => 'queued',
            'queued_at' => now()->toIso8601String(),
            'started_at' => null,
            'finished_at' => null,
            'error' => null,
            'requested_by' => $userId,
        ], 1800);
    }

    public static function markRefreshProcessing(string $category, string $report, string $range): void
    {
        $current = self::refreshStatus($category, $report, $range);

        Cache::put(self::statusKey($category, $report, $range), [
            ...$current,
            'status' => 'processing',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'error' => null,
        ], 1800);
    }

    public static function markRefreshReady(string $category, string $report, string $range): void
    {
        $current = self::refreshStatus($category, $report, $range);

        Cache::put(self::statusKey($category, $report, $range), [
            ...$current,
            'status' => 'ready',
            'finished_at' => now()->toIso8601String(),
            'error' => null,
        ], 1800);
    }

    public static function markRefreshFailed(string $category, string $report, string $range, string $error): void
    {
        $current = self::refreshStatus($category, $report, $range);

        Cache::put(self::statusKey($category, $report, $range), [
            ...$current,
            'status' => 'failed',
            'finished_at' => now()->toIso8601String(),
            'error' => $error,
        ], 1800);
    }

    public static function isRefreshInFlight(string $category, string $report, string $range): bool
    {
        return in_array(self::refreshStatus($category, $report, $range)['status'], ['queued', 'processing'], true);
    }

    /**
     * @param  array{
     *     range: string,
     *     from: string,
     *     to: string,
     *     kpis: list<mixed>,
     *     charts: list<mixed>,
     *     columns: list<mixed>,
     *     rows: list<mixed>,
     *     empty_message: ?string,
     *     cached_at: string,
     * }  $cached
     * @return array{
     *     range: string,
     *     from: Carbon,
     *     to: Carbon,
     *     kpis: list<mixed>,
     *     charts: list<mixed>,
     *     columns: list<mixed>,
     *     rows: list<mixed>,
     *     empty_message: ?string,
     *     cached_at: Carbon,
     * }
     */
    public static function hydrate(array $cached): array
    {
        return [
            'range' => $cached['range'],
            'from' => Carbon::parse($cached['from']),
            'to' => Carbon::parse($cached['to']),
            'kpis' => $cached['kpis'],
            'charts' => $cached['charts'],
            'columns' => $cached['columns'],
            'rows' => $cached['rows'],
            'empty_message' => $cached['empty_message'] ?? null,
            'cached_at' => Carbon::parse($cached['cached_at']),
        ];
    }

    public static function markWarmed(int $count): void
    {
        Cache::put(self::metaKey(), [
            'warmed_at' => now()->toIso8601String(),
            'count' => $count,
            'interval_days' => self::warmIntervalDays(),
        ], self::ttlSeconds());
    }

    /** @return array{warmed_at: ?Carbon, count: int, interval_days: int} */
    public static function meta(): array
    {
        $meta = Cache::get(self::metaKey());

        if (! is_array($meta)) {
            return [
                'warmed_at' => null,
                'count' => 0,
                'interval_days' => self::warmIntervalDays(),
            ];
        }

        return [
            'warmed_at' => isset($meta['warmed_at']) ? Carbon::parse($meta['warmed_at']) : null,
            'count' => (int) ($meta['count'] ?? 0),
            'interval_days' => (int) ($meta['interval_days'] ?? self::warmIntervalDays()),
        ];
    }
}
