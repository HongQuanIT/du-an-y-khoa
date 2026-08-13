<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Trusted-device cookie for learner 2FA (30 days). Bound to user + 2FA enrollment time
 * so disable/re-enable invalidates older cookies.
 */
final class StudentTwoFactorDevice
{
    public const COOKIE = 'student_2fa_device';

    public const TTL_DAYS = 30;

    public static function queue(User $user): void
    {
        $confirmedAt = $user->twoFactorSecret?->confirmed_at;

        if ($confirmedAt === null) {
            return;
        }

        $payload = json_encode([
            'uid' => $user->id,
            'cid' => $confirmedAt->timestamp,
            'exp' => now()->addDays(self::TTL_DAYS)->timestamp,
        ], JSON_THROW_ON_ERROR);

        Cookie::queue(cookie(
            self::COOKIE,
            $payload,
            self::TTL_DAYS * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public static function isTrusted(Request $request, User $user): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $raw = $request->cookie(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return false;
        }

        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (! is_array($data)) {
            return false;
        }

        $uid = $data['uid'] ?? null;
        $cid = $data['cid'] ?? null;
        $exp = $data['exp'] ?? null;

        if (! is_numeric($uid) || (int) $uid !== (int) $user->id) {
            return false;
        }

        $confirmedAt = $user->twoFactorSecret?->confirmed_at;

        if ($confirmedAt === null || ! is_numeric($cid) || (int) $cid !== $confirmedAt->timestamp) {
            return false;
        }

        return is_numeric($exp) && (int) $exp >= now()->timestamp;
    }
}
