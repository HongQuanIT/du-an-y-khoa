<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Modules\Admin\Models\ReportSchedule;

final class ToggleReportScheduleAction
{
    use AsAction;

    public function handle(ReportSchedule $schedule): ReportSchedule
    {
        $schedule->forceFill([
            'is_active' => ! $schedule->is_active,
        ])->save();

        if ($schedule->is_active) {
            $schedule->refreshNextRunAt();
        } else {
            $schedule->forceFill(['next_run_at' => null])->save();
        }

        return $schedule->fresh() ?? $schedule;
    }
}
