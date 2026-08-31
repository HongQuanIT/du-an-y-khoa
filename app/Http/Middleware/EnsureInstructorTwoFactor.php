<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\Instructor;
use App\Support\Auth\TwoFactorGate;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Instructors with 2FA must pass TOTP or use a trusted device on /teach pages.
 */
final class EnsureInstructorTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('teach.2fa.challenge', 'teach.2fa.challenge.verify', 'teach.logout')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! Instructor::is($user)) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (TwoFactorGate::isSatisfied($request, $user)) {
            TwoFactorGate::confirmIfTrusted($request, $user);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error(
                code: 'TWO_FACTOR_REQUIRED',
                message: 'Cần xác thực hai bước để tiếp tục.',
                status: 403,
            );
        }

        return redirect()->route('teach.2fa.challenge');
    }
}
