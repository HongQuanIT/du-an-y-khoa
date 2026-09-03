<?php

namespace Modules\QuestionBank\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Policies\QuestionPolicy;
use Modules\QuestionBank\Policies\QuestionSessionPolicy;
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
        Gate::policy(QuestionSession::class, QuestionSessionPolicy::class);
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
    protected array $commands = [
        \Modules\QuestionBank\Console\SyncQuestionStatsCommand::class,
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
     */
    protected function configureSchedules(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command('question-bank:sync-stats')
            ->hourly()
            ->withoutOverlapping();
    }
}
