<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\Staff;
use App\Support\Auth\TwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must have confirmed TOTP for the current session before /admin pages.
 */
final class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! Staff::isStaff($user)) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.setup');
        }

        if (! TwoFactorSession::isConfirmed($request)) {
            return redirect()->route('admin.2fa.challenge');
        }

        return $next($request);
    }
}
