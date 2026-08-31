<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Whether the current browser session satisfies 2FA policy (session marker or trusted device).
 */
final class TwoFactorGate
{
    public static function isSatisfied(Request $request, User $user): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return true;
        }

        return TwoFactorSession::isConfirmed($request)
            || TwoFactorTrustedDevice::isTrusted($request, $user);
    }

    public static function confirmIfTrusted(Request $request, User $user): void
    {
        if (self::isSatisfied($request, $user)) {
            TwoFactorSession::confirm($request);
        }
    }
}
