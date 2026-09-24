<?php

declare(strict_types=1);

namespace Modules\Editor\Http\Middleware;

use App\Support\Auth\ImpliedViewPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEditorViewPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                continue;
            }

            $permissions = explode(',', substr($middleware, 11), 2)[0];

            foreach (explode('|', $permissions) as $permission) {
                if ($user?->can($permission) && ImpliedViewPermissions::missingFor($user, $permission) !== null) {
                    abort(403);
                }
            }
        }

        return $next($request);
    }
}
