<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\HomePath;
use App\Support\Auth\PortalAccess;
use App\Support\Enums\PortalGroup;
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

        if (PortalAccess::allows($user, PortalGroup::Learner)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error(
                code: 'LEARNER_REQUIRED',
                message: 'Chỉ tài khoản học viên được vào khu vực học tập.',
                status: 403,
            );
        }

        if (PortalAccess::primaryPortal($user) === null) {
            abort(403, 'Tài khoản chưa được gán vai trò truy cập.');
        }

        return redirect()->to(HomePath::for($user));
    }
}
