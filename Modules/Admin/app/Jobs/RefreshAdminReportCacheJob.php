<?php

declare(strict_types=1);

namespace Modules\Admin\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Admin\Actions\GetAdminReportDataAction;
use Modules\Admin\Support\AdminReportCache;
use Throwable;

final class RefreshAdminReportCacheJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $category,
        public string $report,
        public string $range,
    ) {}

    public function handle(GetAdminReportDataAction $action): void
    {
        AdminReportCache::markRefreshProcessing($this->category, $this->report, $this->range);

        try {
            // forceFresh ghi đè snapshot; viewer vẫn đọc cache cũ đến khi xong.
            $action->handle($this->category, $this->report, $this->range, forceFresh: true);
            AdminReportCache::markRefreshReady($this->category, $this->report, $this->range);
        } catch (Throwable $e) {
            AdminReportCache::markRefreshFailed(
                $this->category,
                $this->report,
                $this->range,
                $e->getMessage(),
            );

            throw $e;
        }
    }
}
