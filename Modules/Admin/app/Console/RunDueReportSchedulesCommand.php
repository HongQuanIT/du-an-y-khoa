<?php

declare(strict_types=1);

namespace Modules\Admin\Console;

use Illuminate\Console\Command;
use Modules\Admin\Actions\RunDueReportSchedulesAction;

final class RunDueReportSchedulesCommand extends Command
{
    protected $signature = 'admin:report-schedules-run';

    protected $description = 'Gửi các báo cáo đã đến lịch (email + CSV)';

    public function handle(RunDueReportSchedulesAction $runner): int
    {
        $sent = $runner->handle();

        $this->info(sprintf('Đã xử lý %d lịch báo cáo đến hạn.', $sent));

        return self::SUCCESS;
    }
}
