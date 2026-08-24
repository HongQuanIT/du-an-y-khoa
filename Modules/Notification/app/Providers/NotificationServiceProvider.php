<?php

namespace Modules\Notification\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Nwidart\Modules\Support\ModuleServiceProvider;

class NotificationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Notification';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'notification';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\Notification\Console\SendStudyPlanRemindersCommand::class,
        \Modules\Notification\Console\SendLiveUpcomingRemindersCommand::class,
        \Modules\Notification\Console\SendStreakWarningsCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
        NotificationViewServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('notification:study-plan-reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        $schedule->command('notification:live-upcoming')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('notification:streak-warnings')
            ->hourly()
            ->withoutOverlapping();
    }
}