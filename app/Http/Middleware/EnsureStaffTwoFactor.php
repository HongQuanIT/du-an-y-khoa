<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\Staff;
use App\Support\Auth\PortalAccess;
use App\Support\Enums\PortalGroup;
use App\Support\Auth\TwoFactorGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must confirm TOTP when 2FA is enabled (optional for admin accounts).
 */
final class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || (! Staff::isStaff($user) && ! PortalAccess::allows($user, PortalGroup::Editor)) || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (TwoFactorGate::isSatisfied($request, $user)) {
            TwoFactorGate::confirmIfTrusted($request, $user);

            return $next($request);
        }

        return redirect()->route($request->is('editor') || $request->is('editor/*')
            ? 'editor.2fa.challenge'
            : 'admin.2fa.challenge');
    }
}
