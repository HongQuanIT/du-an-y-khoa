<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Modules\Admin\Models\ReportSchedule;

final class DeleteReportScheduleAction
{
    use AsAction;

    public function handle(ReportSchedule $schedule): void
    {
        $schedule->delete();
    }
}
