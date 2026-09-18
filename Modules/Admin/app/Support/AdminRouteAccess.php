<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/** Keeps links and forms aligned with the target route's permission checks. */
final class AdminRouteAccess
{
    public static function allows(?User $user, string $name): bool
    {
        $route = Route::getRoutes()->getByName($name);
        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                if ($user === null) {
                    return false;
                }

                $permissions = explode(',', substr($middleware, 11), 2)[0];
                if (! $user->canAny(explode('|', $permissions))) {
                    return false;
                }

                foreach (explode('|', $permissions) as $permission) {
                    if (ImpliedViewPermissions::missingFor($user, $permission) !== null) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
