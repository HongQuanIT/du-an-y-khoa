<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\Instructor;
use App\Support\Auth\Partner;
use App\Support\Auth\Staff;
use App\Support\Auth\TwoFactorGate;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Learners with 2FA must pass TOTP or use a trusted device before app pages.
 */
final class EnsureStudentTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('student.2fa.challenge', 'student.2fa.challenge.verify', 'logout', 'password.*')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || Staff::isStaff($user) || Instructor::is($user) || Partner::is($user)) {
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

        return redirect()->route('student.2fa.challenge');
    }
}
