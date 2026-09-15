<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\PortalAccess;
use App\Support\Enums\PortalGroup;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic portal boundary used by both system and custom roles.
 */
final class EnsurePortal
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $expected = PortalGroup::tryFrom($portal);

        if ($expected === null) {
            abort(500, "Portal [{$portal}] chưa được cấu hình.");
        }

        if (PortalAccess::allows($request->user(), $expected)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error(
                code: 'PORTAL_ACCESS_DENIED',
                message: 'Tài khoản không có quyền truy cập cổng này.',
                status: 403,
            );
        }

        abort(403, PortalAccess::primaryPortal($request->user()) === null
            ? 'Tài khoản chưa được gán vai trò truy cập.'
            : 'Tài khoản không có quyền truy cập cổng này.');
    }
}
