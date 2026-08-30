<?php

declare(strict_types=1);

namespace Modules\Partner\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class PartnerServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Partner';

    protected string $nameLower = 'partner';

    /** @var string[] */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
