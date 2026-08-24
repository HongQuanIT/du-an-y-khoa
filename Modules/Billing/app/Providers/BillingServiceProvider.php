<?php

namespace Modules\Billing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Billing\Jobs\ReconcilePendingCheckoutsJob;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Billing';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'billing';

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
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new ReconcilePendingCheckoutsJob)->hourly();
    }
}
