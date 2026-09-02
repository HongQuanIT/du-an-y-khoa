<?php

declare(strict_types=1);

namespace Modules\Admin\Jobs;

use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Admin\Actions\GetAdminReportDataAction;
use Modules\Admin\Support\AdminReportCache;
use Throwable;

final class RefreshAdminReportCacheJob implements ShouldBeUnique, ShouldQueue
{
    use HasQueueDisplayName;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /** Không refresh trùng snapshot trong 10 phút. */
    public int $uniqueFor = 600;

    public function __construct(
        public string $category,
        public string $report,
        public string $range,
    ) {
        $this->onQueue(QueueName::AdminReports->value);
    }

    public function displayName(): string
    {
        return sprintf(
            'admin-reports:refresh:%s/%s@%s',
            $this->category,
            $this->report,
            $this->range,
        );
    }

    public function uniqueId(): string
    {
        return implode(':', [$this->category, $this->report, $this->range]);
    }

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

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags(
            'admin-reports',
            'refresh',
            'category:'.$this->category,
            'report:'.$this->report,
            'range:'.$this->range,
        );
    }
}
