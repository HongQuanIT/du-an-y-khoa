<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class PortalRouteAccess
{
    public static function allows(?User $user, string $name): bool
    {
        $routeName = request()->routeIs('editor.*') && str_starts_with($name, 'admin.')
            ? 'editor.'.substr($name, 6)
            : $name;
        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                continue;
            }

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

        return true;
    }
}
