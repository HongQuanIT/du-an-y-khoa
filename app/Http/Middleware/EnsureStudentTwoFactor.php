<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\Instructor;
use App\Support\Auth\Staff;
use App\Support\Auth\StudentTwoFactorDevice;
use App\Support\Auth\TwoFactorSession;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Learners with 2FA enabled must pass TOTP (or a trusted-device cookie) before app pages.
 * Staff and instructors are skipped — admin has its own gate; teach portal is out of scope.
 */
final class EnsureStudentTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('student.2fa.challenge', 'student.2fa.challenge.verify', 'logout')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || Staff::isStaff($user) || Instructor::is($user)) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (TwoFactorSession::isConfirmed($request)) {
            return $next($request);
        }

        if (StudentTwoFactorDevice::isTrusted($request, $user)) {
            TwoFactorSession::confirm($request);

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
