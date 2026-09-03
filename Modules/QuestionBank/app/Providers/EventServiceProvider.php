<?php

namespace Modules\QuestionBank\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Modules\QuestionBank\Listeners\SyncQuestionStatsOnSessionProgress;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        QuestionSessionProgressed::class => [
            SyncQuestionStatsOnSessionProgress::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
