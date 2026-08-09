<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Http\Request;

/**
 * Session marker: staff completed TOTP (or recovery) for this browser session.
 */
final class TwoFactorSession
{
    public const KEY = 'auth.two_factor_confirmed_at';

    public static function confirm(Request $request): void
    {
        $request->session()->put(self::KEY, now()->timestamp);
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    public static function isConfirmed(Request $request): bool
    {
        return $request->session()->has(self::KEY);
    }
}
