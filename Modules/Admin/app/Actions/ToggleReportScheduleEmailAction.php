<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Modules\Admin\Models\ReportSchedule;

final class ToggleReportScheduleEmailAction
{
    use AsAction;

    public function handle(ReportSchedule $schedule): ReportSchedule
    {
        $schedule->forceFill([
            'send_email' => ! $schedule->send_email,
        ])->save();

        return $schedule->fresh() ?? $schedule;
    }
}
