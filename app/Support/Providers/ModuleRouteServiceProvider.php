<?php

declare(strict_types=1);

namespace App\Support\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Shared route registration for every domain module.
 *
 * - web routes  → `web` middleware group
 * - api routes  → `api` group under the versioned `api/v1` prefix,
 *                 namespaced route names: `api.{module-kebab}.*`
 *
 * Modules only declare their `$name`; the wiring lives here (DRY).
 */
abstract class ModuleRouteServiceProvider extends ServiceProvider
{
    protected string $name;

    public function map(): void
    {
        Route::middleware('web')
            ->group(module_path($this->name, '/routes/web.php'));

        Route::middleware('api')
            ->prefix('api/v1')
            ->name('api.'.Str::kebab($this->name).'.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
