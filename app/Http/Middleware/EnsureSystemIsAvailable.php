<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSystemIsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('features.maintenance_mode', false)) {
            return $next($request);
        }

        if ($this->isAllowedDuringMaintenance($request)) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'siteName' => setting('general.site_name', config('app.name')),
            'supportEmail' => setting('general.support_email'),
            'supportHotline' => setting('general.support_hotline'),
        ], 503);
    }

    private function isAllowedDuringMaintenance(Request $request): bool
    {
        if ($request->is('admin') || $request->is('admin/*') || $request->is('health') || $request->is('health/*')) {
            return true;
        }

        $user = $request->user();

        return $user !== null
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([Role::SuperAdmin->value, Role::Admin->value]);
    }
}
