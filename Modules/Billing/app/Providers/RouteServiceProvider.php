<?php

declare(strict_types=1);

namespace Modules\Billing\Providers;

use App\Support\Providers\ModuleRouteServiceProvider;

class RouteServiceProvider extends ModuleRouteServiceProvider
{
    protected string $name = 'Billing';
}
