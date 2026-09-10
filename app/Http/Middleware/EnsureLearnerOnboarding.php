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

/** Require profile completion only for learners explicitly enrolled into onboarding. */
final class EnsureLearnerOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('onboarding.*', 'logout', 'student.2fa.*', 'password.*')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || Staff::isStaff($user) || Instructor::is($user) || Partner::is($user)) {
            return $next($request);
        }

        // Existing accounts without a learner_profiles row remain compatible.
        // Self-service registration creates an incomplete row and is therefore gated.
        $profile = $user->learnerProfile;
        if ($profile === null || $profile->onboarding_completed_at !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error(
                code: 'ONBOARDING_REQUIRED',
                message: 'Vui lòng hoàn thiện hồ sơ trước khi tiếp tục.',
                status: 409,
            );
        }

        return redirect()->route('onboarding.profile');
    }
}
