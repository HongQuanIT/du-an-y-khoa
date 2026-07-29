<?php

namespace Modules\QuestionBank\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Policies\QuestionPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class QuestionBankServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'QuestionBank';

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Question::class, QuestionPolicy::class);
    }

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'questionbank';

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
