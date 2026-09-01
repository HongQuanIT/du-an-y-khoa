<?php

namespace Modules\Admin\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AdminServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Admin';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'admin';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\Admin\Console\RunDueReportSchedulesCommand::class,
        \Modules\Admin\Console\WarmAdminReportCachesCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('admin:reports-warm-cache')
            ->hourly()
            ->when(fn (): bool => \Modules\Admin\Support\AdminReportCache::shouldAutoWarm())
            ->withoutOverlapping();

        $schedule->command('admin:report-schedules-run')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }
}
