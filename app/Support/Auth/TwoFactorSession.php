<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Http\Request;

/**
 * Session marker: user completed TOTP (or recovery) for this browser session.
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
        $timestamp = $request->session()->get(self::KEY);

        if (! is_numeric($timestamp)) {
            return false;
        }

        $ttlSeconds = (int) config('auth-session.two_factor_trust_days', 30) * 86400;

        return (int) $timestamp + $ttlSeconds >= now()->timestamp;
    }
}
