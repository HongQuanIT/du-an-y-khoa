<?php

declare(strict_types=1);

namespace Modules\Admin\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Modules\Admin\Actions\WarmAdminReportCachesAction;
use Throwable;

final class WarmAllAdminReportCachesJob implements ShouldQueue
{
    use Queueable;

    /** Warm toàn bộ catalog có thể lâu hơn timeout Horizon mặc định (60s). */
    public int $timeout = 300;

    public int $tries = 1;

    public function handle(WarmAdminReportCachesAction $warm): void
    {
        Cache::put('admin:report:warm-all:status', [
            'status' => 'processing',
            'started_at' => now()->toIso8601String(),
        ], 1800);

        try {
            $count = $warm->handle();
            Cache::put('admin:report:warm-all:status', [
                'status' => 'ready',
                'finished_at' => now()->toIso8601String(),
                'count' => $count,
            ], 1800);
        } catch (Throwable $e) {
            Cache::put('admin:report:warm-all:status', [
                'status' => 'failed',
                'finished_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], 1800);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put('admin:report:warm-all:status', [
            'status' => 'failed',
            'finished_at' => now()->toIso8601String(),
            'error' => $exception?->getMessage() ?? 'Job failed',
        ], 1800);
    }
}
