<?php

namespace Modules\StudyPlan\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Policies\StudyPlanPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class StudyPlanServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'StudyPlan';

    public function boot(): void
    {
        parent::boot();

        Gate::policy(StudyPlan::class, StudyPlanPolicy::class);
    }

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'studyplan';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

}
