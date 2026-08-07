<?php

declare(strict_types=1);

namespace Modules\Classroom\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Policies\ClassroomPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ClassroomServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Classroom';

    protected string $nameLower = 'classroom';

    /** @var string[] */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Classroom::class, ClassroomPolicy::class);
    }
}
