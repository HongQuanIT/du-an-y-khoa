<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Partner\Support\PartnerInviteIntent;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persist ?ref= invite codes into session for later registration.
 */
final class CapturePartnerInvite
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            PartnerInviteIntent::capture($request);
        }

        return $next($request);
    }
}
