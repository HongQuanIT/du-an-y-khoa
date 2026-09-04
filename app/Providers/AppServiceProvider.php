<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SettingService;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiters();
        $this->configurePasswordPolicy();
        $this->configureAuthorization();
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

        RateLimiter::for('activity-heartbeat', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(10)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('contact', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->ip());
        });
    }

    /**
     * Super Admin should behave as full-access even if cached permissions lag behind.
     * Exception: question.create / update / submit / review stay with content_editor / instructor
     * so SA không soạn nội dung và không duyệt lớp 1 (SRS module 35).
     */
    private function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if ($user === null || ! method_exists($user, 'hasRole') || ! $user->hasRole(Role::SuperAdmin->value)) {
                return null;
            }

            // Content-editor / instructor-only abilities — SA không bypass.
            if (in_array($ability, [
                Permission::QuestionCreate->value,
                Permission::QuestionUpdate->value,
                Permission::QuestionSubmit->value,
                Permission::QuestionReview->value,
            ], true)) {
                return null;
            }

            return true;
        });
    }
}
