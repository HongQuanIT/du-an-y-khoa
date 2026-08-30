<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\HomePath;
use App\Support\Auth\Partner;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Partner portal (/partner): only role partner.
 */
final class EnsurePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Partner::is($request->user())) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error(
                code: 'PARTNER_REQUIRED',
                message: 'Chỉ cộng tác viên được vào khu vực /partner.',
                status: 403,
            );
        }

        return redirect()->to(HomePath::for($request->user()));
    }
}
