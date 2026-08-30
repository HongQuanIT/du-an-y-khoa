<?php

declare(strict_types=1);

namespace Modules\Partner\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Modules\Partner\Models\PartnerInviteCode;

/**
 * Capture invite/referral code from ?ref= (or form) until registration.
 *
 * Persistence:
 * - Session (same-browser short path)
 * - Encrypted HttpOnly cookie lasting {@see PartnerSettings::attributionWindowDays()}
 *   so click → register within N days still attributes (rule A).
 */
final class PartnerInviteIntent
{
    public const QUERY_KEY = 'ref';

    public const SESSION_KEY = 'partner_invite.ref';

    public const SESSION_CAPTURED_AT_KEY = 'partner_invite.captured_at';

    public const COOKIE = 'partner_invite_ref';

    public static function capture(Request $request): void
    {
        $code = self::normalize($request->query(self::QUERY_KEY) ?? $request->input(self::QUERY_KEY));
        if ($code === null) {
            return;
        }

        if (! self::isValidCode($code)) {
            return;
        }

        if (! PartnerSettings::overwriteAttribution() && self::hasUsableStoredRef($request)) {
            return;
        }

        self::store($request, $code, Carbon::now());
    }

    public static function peek(Request $request): ?string
    {
        $stored = self::resolveStored($request);

        return $stored['code'] ?? null;
    }

    public static function pull(Request $request): ?string
    {
        $stored = self::resolveStored($request);
        self::clear($request);

        return $stored['code'] ?? null;
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget([self::SESSION_KEY, self::SESSION_CAPTURED_AT_KEY]);
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * Prefer typed form field, then session/cookie within attribution window.
     */
    public static function resolveForRegistration(Request $request): ?string
    {
        $fromInput = self::normalize($request->input('invite_code') ?? $request->input(self::QUERY_KEY));
        if ($fromInput !== null && self::isValidCode($fromInput)) {
            return $fromInput;
        }

        $stored = self::resolveStored($request);
        if ($stored === null) {
            return null;
        }

        if (! self::isValidCode($stored['code'])) {
            self::clear($request);

            return null;
        }

        return $stored['code'];
    }

    public static function isValidCode(string $code): bool
    {
        $invite = PartnerInviteCode::query()
            ->with('partner')
            ->forCode($code)
            ->first();

        if ($invite === null) {
            return false;
        }

        if (! $invite->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($invite->starts_at !== null && $now->lt($invite->starts_at)) {
            return false;
        }

        if ($invite->expires_at !== null && $now->gt($invite->expires_at)) {
            return false;
        }

        if ($invite->max_uses !== null && $invite->use_count >= $invite->max_uses) {
            return false;
        }

        if (PartnerSettings::requireActivePartner()) {
            $partner = $invite->relationLoaded('partner') ? $invite->partner : $invite->partner()->first();

            return $partner !== null && $partner->isActive();
        }

        return true;
    }

    public static function authQuery(?string $code): array
    {
        if ($code === null || $code === '') {
            return [];
        }

        return [self::QUERY_KEY => Str::upper($code)];
    }

    /**
     * @return array{code: string, captured_at: Carbon}|null
     */
    private static function resolveStored(Request $request): ?array
    {
        $windowDays = PartnerSettings::attributionWindowDays();
        $deadline = Carbon::now()->subDays($windowDays);

        $sessionCode = self::normalize($request->session()->get(self::SESSION_KEY));
        $sessionAt = self::parseTimestamp($request->session()->get(self::SESSION_CAPTURED_AT_KEY));

        if ($sessionCode !== null && $sessionAt !== null && $sessionAt->gte($deadline)) {
            return ['code' => $sessionCode, 'captured_at' => $sessionAt];
        }

        $cookie = self::readCookie($request);
        if ($cookie !== null && $cookie['captured_at']->gte($deadline)) {
            // Re-hydrate session for the rest of the request lifecycle.
            $request->session()->put(self::SESSION_KEY, $cookie['code']);
            $request->session()->put(self::SESSION_CAPTURED_AT_KEY, $cookie['captured_at']->timestamp);

            return $cookie;
        }

        if ($sessionCode !== null || $cookie !== null) {
            self::clear($request);
        }

        return null;
    }

    private static function hasUsableStoredRef(Request $request): bool
    {
        $stored = self::resolveStored($request);

        return $stored !== null && self::isValidCode($stored['code']);
    }

    private static function store(Request $request, string $code, Carbon $capturedAt): void
    {
        $request->session()->put(self::SESSION_KEY, $code);
        $request->session()->put(self::SESSION_CAPTURED_AT_KEY, $capturedAt->timestamp);

        $ttlDays = PartnerSettings::attributionWindowDays();
        $payload = json_encode([
            'code' => $code,
            'ts' => $capturedAt->timestamp,
        ], JSON_THROW_ON_ERROR);

        Cookie::queue(cookie(
            self::COOKIE,
            $payload,
            $ttlDays * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /**
     * @return array{code: string, captured_at: Carbon}|null
     */
    private static function readCookie(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $code = self::normalize($data['code'] ?? null);
        $capturedAt = self::parseTimestamp($data['ts'] ?? null);

        if ($code === null || $capturedAt === null) {
            return null;
        }

        return ['code' => $code, 'captured_at' => $capturedAt];
    }

    private static function parseTimestamp(mixed $raw): ?Carbon
    {
        if (! is_numeric($raw)) {
            return null;
        }

        $ts = (int) $raw;
        if ($ts <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp($ts);
    }

    private static function normalize(mixed $raw): ?string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $code = Str::upper(trim((string) $raw));

        return $code !== '' ? $code : null;
    }
}
