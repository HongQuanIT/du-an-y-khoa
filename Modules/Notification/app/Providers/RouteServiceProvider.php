<?php

declare(strict_types=1);

namespace Modules\Notification\Providers;

use App\Support\Providers\ModuleRouteServiceProvider;

class RouteServiceProvider extends ModuleRouteServiceProvider
{
    protected string $name = 'Notification';
}
