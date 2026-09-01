<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Mail\ScheduledReportMail;
use Modules\Admin\Models\ReportSchedule;
use Modules\Admin\Support\AdminReportCatalog;
use Throwable;

final class RunDueReportSchedulesAction
{
    use AsAction;

    public function handle(?Carbon $now = null): int
    {
        $now ??= now();
        $processed = 0;

        $schedules = ReportSchedule::query()
            ->due($now)
            ->orderBy('next_run_at')
            ->limit(50)
            ->get();

        foreach ($schedules as $schedule) {
            try {
                $this->runOne($schedule, $now);
                $processed++;
            } catch (Throwable $e) {
                Log::error('report_schedule_failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);

                $schedule->forceFill(['last_run_at' => $now])->save();
                $schedule->refreshNextRunAt($now);
            }
        }

        return $processed;
    }

    private function runOne(ReportSchedule $schedule, Carbon $now): void
    {
        $match = AdminReportCatalog::findReport($schedule->category_slug, $schedule->report_slug);
        if ($match === null) {
            $schedule->forceFill(['is_active' => false, 'next_run_at' => null])->save();

            return;
        }

        // Prefer cache; if miss, compute once and store.
        $csvData = GetAdminReportDataAction::make()->exportRows(
            $schedule->category_slug,
            $schedule->report_slug,
            $schedule->range_key,
        );

        if ($schedule->send_email && $schedule->recipients !== []) {
            Mail::to($schedule->recipients)->send(new ScheduledReportMail(
                schedule: $schedule,
                export: $csvData,
                reportTitle: $match['report']['title'],
                categoryTitle: $match['category']['title'],
            ));
        }

        $schedule->forceFill(['last_run_at' => $now])->save();
        $schedule->refreshNextRunAt($now);
    }
}
