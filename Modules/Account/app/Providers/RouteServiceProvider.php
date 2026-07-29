<?php

declare(strict_types=1);

namespace Modules\Account\Providers;

use App\Support\Providers\ModuleRouteServiceProvider;

class RouteServiceProvider extends ModuleRouteServiceProvider
{
    protected string $name = 'Account';
}
