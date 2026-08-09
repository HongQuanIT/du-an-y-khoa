<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\HomePath;
use App\Support\Auth\Instructor;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Instructor portal (/teach): only role instructor.
 */
final class EnsureInstructor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Instructor::is($request->user())) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error(
                code: 'INSTRUCTOR_REQUIRED',
                message: 'Chỉ giảng viên được vào khu vực /teach.',
                status: 403,
            );
        }

        return redirect()->to(HomePath::for($request->user()));
    }
}
