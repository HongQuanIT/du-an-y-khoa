<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiters();
        $this->configurePasswordPolicy();
    }

    /**
     * Single password policy for every flow that accepts a new password
     * (registration, reset, profile change).
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }

    /**
     * Fail fast in non-production: catch N+1s, missing attributes and silent
     * mass-assignment issues early — a performance and correctness safeguard.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }

    /**
     * Named rate limiters per srs/00-nen-tang/05-api-conventions.md §7.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('auth', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
