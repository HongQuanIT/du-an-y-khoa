<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Single active web session per user + absolute/idle session timeouts.
 */
final class WebSessionManager
{
    public const LOGGED_IN_AT = 'auth.logged_in_at';

    public const LAST_ACTIVITY_AT = 'auth.last_activity_at';

    public const BOUND_SESSION_ID = 'auth.bound_web_session_id';

    public static function bindToUser(User $user, Request $request): void
    {
        $session = $request->session();
        $newId = $session->getId();
        $previousId = self::activeSessionId($user);

        if ($previousId !== null && $previousId !== $newId) {
            $session->getHandler()->destroy($previousId);
        }

        $user->forceFill(['active_web_session_id' => $newId])->save();

        $now = now()->timestamp;
        $session->put(self::BOUND_SESSION_ID, $newId);
        $session->put(self::LOGGED_IN_AT, $now);
        $session->put(self::LAST_ACTIVITY_AT, $now);
    }

    public static function clearForUser(User $user): void
    {
        $user->forceFill(['active_web_session_id' => null])->save();
    }

    public static function isActiveSession(User $user, Request $request): bool
    {
        $currentId = $request->session()->getId();
        $boundId = $request->session()->get(self::BOUND_SESSION_ID);

        if (is_string($boundId) && $boundId !== '') {
            return $boundId === $currentId;
        }

        $activeId = self::activeSessionId($user);

        if ($activeId === null) {
            return true;
        }

        return $activeId === $currentId;
    }

    public static function ensureInitialized(User $user, Request $request): void
    {
        $session = $request->session();
        $now = now()->timestamp;
        $currentId = $session->getId();

        if (! is_string($session->get(self::BOUND_SESSION_ID))) {
            $session->put(self::BOUND_SESSION_ID, $currentId);
        }

        if (! is_numeric($session->get(self::LOGGED_IN_AT))) {
            $session->put(self::LOGGED_IN_AT, $now);
            $session->put(self::LAST_ACTIVITY_AT, $now);
        }

        if (self::activeSessionId($user) === null) {
            $user->forceFill(['active_web_session_id' => $currentId])->save();
        }
    }

    private static function activeSessionId(User $user): ?string
    {
        $attributes = $user->getAttributes();

        if (! array_key_exists('active_web_session_id', $attributes)) {
            return $user->fresh()?->active_web_session_id;
        }

        return $attributes['active_web_session_id'];
    }

    public static function isExpired(Request $request): bool
    {
        $session = $request->session();
        $loggedInAt = $session->get(self::LOGGED_IN_AT);
        $lastActivity = $session->get(self::LAST_ACTIVITY_AT);

        if (! is_numeric($loggedInAt)) {
            return true;
        }

        $maxAgeSeconds = config('auth-session.max_lifetime_days') * 86400;
        $idleSeconds = config('auth-session.idle_timeout_hours') * 3600;
        $now = now()->timestamp;

        if ($now - (int) $loggedInAt > $maxAgeSeconds) {
            return true;
        }

        if (is_numeric($lastActivity) && $now - (int) $lastActivity > $idleSeconds) {
            return true;
        }

        return false;
    }

    public static function touchActivity(Request $request): void
    {
        $request->session()->put(self::LAST_ACTIVITY_AT, now()->timestamp);
    }

    public static function logout(Request $request, ?string $message = null): void
    {
        $user = $request->user();

        if ($user !== null) {
            self::clearForUser($user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($message !== null) {
            $request->session()->flash('status', $message);
        }
    }
}
