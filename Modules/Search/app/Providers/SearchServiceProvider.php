<?php

namespace Modules\Search\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Classroom\Models\Classroom;
use Modules\Exam\Models\Exam;
use Modules\Search\Observers\ClassroomSearchObserver;
use Modules\Search\Observers\ExamSearchObserver;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SearchServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Exam::observe(ExamSearchObserver::class);
        Classroom::observe(ClassroomSearchObserver::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Search';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'search';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

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
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
