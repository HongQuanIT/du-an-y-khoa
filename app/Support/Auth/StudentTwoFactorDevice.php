<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @deprecated Use TwoFactorTrustedDevice
 */
final class StudentTwoFactorDevice
{
    public const COOKIE = TwoFactorTrustedDevice::COOKIE;

    public static function queue(User $user): void
    {
        TwoFactorTrustedDevice::queue($user);
    }

    public static function forget(): void
    {
        TwoFactorTrustedDevice::forget();
    }

    public static function isTrusted(Request $request, User $user): bool
    {
        return TwoFactorTrustedDevice::isTrusted($request, $user);
    }
}
