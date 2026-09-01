<?php

declare(strict_types=1);

namespace Modules\Admin\Console;

use Illuminate\Console\Command;
use Modules\Admin\Actions\WarmAdminReportCachesAction;

final class WarmAdminReportCachesCommand extends Command
{
    protected $signature = 'admin:reports-warm-cache';

    protected $description = 'Tính sẵn snapshot báo cáo admin và ghi vào cache (không query DB khi xem trang)';

    public function handle(WarmAdminReportCachesAction $warm): int
    {
        $count = $warm->handle();

        $this->info(sprintf('Đã warm %d snapshot báo cáo vào cache.', $count));

        return self::SUCCESS;
    }
}
