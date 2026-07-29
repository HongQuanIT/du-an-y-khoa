<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Premium gating layer, independent from RBAC. Usage:
 *   Route::middleware('subscription:qbank.full')
 *
 * The server always re-checks entitlements (never trusts the client).
 * See srs/00-nen-tang/03-phan-quyen-rbac.md §6-7.
 */
final class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next, string $entitlement): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasEntitlement($entitlement)) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    code: 'SUBSCRIPTION_REQUIRED',
                    message: 'Tính năng này yêu cầu gói Premium.',
                    status: 403,
                    details: [['entitlement' => $entitlement]],
                );
            }

            return redirect()->route('billing.plans')
                ->with('paywall', $entitlement);
        }

        return $next($request);
    }
}
