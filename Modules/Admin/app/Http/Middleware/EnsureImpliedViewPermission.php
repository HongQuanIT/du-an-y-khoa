<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Admin\Support\ImpliedViewPermissions;
use Symfony\Component\HttpFoundation\Response;

final class EnsureImpliedViewPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                continue;
            }

            $permissions = explode(',', substr($middleware, 11), 2)[0];

            foreach (explode('|', $permissions) as $permission) {
                if (ImpliedViewPermissions::missingFor($user, $permission) !== null) {
                    abort(403);
                }
            }
        }

        return $next($request);
    }
}
