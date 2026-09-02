<?php

declare(strict_types=1);

namespace Modules\Admin\Jobs;

use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Modules\Admin\Actions\WarmAdminReportCachesAction;
use Throwable;

final class WarmAllAdminReportCachesJob implements ShouldBeUnique, ShouldQueue
{
    use HasQueueDisplayName;
    use Queueable;

    /** Warm toàn bộ catalog có thể lâu hơn timeout Horizon mặc định (60s). */
    public int $timeout = 300;

    public int $tries = 1;

    /** Chỉ một warm-all cùng lúc. */
    public int $uniqueFor = 1800;

    public function __construct()
    {
        $this->onQueue(QueueName::AdminReports->value);
    }

    public function displayName(): string
    {
        return 'admin-reports:warm-all';
    }

    public function uniqueId(): string
    {
        return 'warm-all';
    }

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

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags('admin-reports', 'warm-all');
    }
}
