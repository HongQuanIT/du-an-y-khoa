<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\Instructor;
use App\Support\Auth\Partner;
use App\Support\Auth\Staff;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Learner portal only: staff, instructors, and partners must stay in their own portals.
 */
final class EnsureLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (Staff::isStaff($user)) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    code: 'STAFF_PORTAL_REQUIRED',
                    message: 'Tài khoản quản trị chỉ dùng khu vực /admin.',
                    status: 403,
                );
            }

            return redirect()->route('admin.dashboard');
        }

        if (Instructor::is($user)) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    code: 'TEACH_PORTAL_REQUIRED',
                    message: 'Tài khoản giảng viên chỉ dùng khu vực /teach.',
                    status: 403,
                );
            }

            return redirect()->route('teach.dashboard');
        }

        if (Partner::is($user)) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    code: 'PARTNER_PORTAL_REQUIRED',
                    message: 'Tài khoản cộng tác viên chỉ dùng khu vực /partner.',
                    status: 403,
                );
            }

            return redirect()->route('partner.dashboard');
        }

        return $next($request);
    }
}
