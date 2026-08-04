<?php

namespace Modules\StudyPlan\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\StudyPlan\Console\ReplanStudyPlansCommand;
use Modules\StudyPlan\Jobs\ReplanActivePlansJob;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Policies\StudyPlanPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class StudyPlanServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'StudyPlan';

    public function boot(): void
    {
        parent::boot();

        Gate::policy(StudyPlan::class, StudyPlanPolicy::class);
    }

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'studyplan';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ReplanStudyPlansCommand::class,
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
     * Runs after midnight so a day that was missed is already in the past.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new ReplanActivePlansJob)
            ->dailyAt('01:00')
            ->withoutOverlapping();
    }
}
