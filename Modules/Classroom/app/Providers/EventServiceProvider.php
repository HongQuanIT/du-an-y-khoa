<?php

declare(strict_types=1);

namespace Modules\Classroom\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<string, array<int, string>> */
    protected $listen = [];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}
