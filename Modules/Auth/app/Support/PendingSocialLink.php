<?php

declare(strict_types=1);

namespace Modules\Auth\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\SocialAccount;

final class PendingSocialLink
{
    private const SESSION_KEY = 'social_auth.pending_link';

    /** @param array{provider: string, provider_user_id: string, email: string, name: string, avatar_url: ?string} $identity */
    public static function store(Request $request, array $identity): void
    {
        $request->session()->put(self::SESSION_KEY, [
            ...$identity,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
    }

    public static function linkAfterPasswordLogin(Request $request, User $user): bool
    {
        $identity = $request->session()->pull(self::SESSION_KEY);
        if (! is_array($identity) || (int) ($identity['expires_at'] ?? 0) < now()->timestamp) {
            return false;
        }

        if (mb_strtolower((string) ($identity['email'] ?? '')) !== mb_strtolower($user->email)) {
            return false;
        }

        return DB::transaction(function () use ($identity, $user): bool {
            $provider = (string) $identity['provider'];
            $providerUserId = (string) $identity['provider_user_id'];
            $claimed = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($claimed !== null && $claimed->user_id !== $user->getKey()) {
                return false;
            }

            SocialAccount::query()->updateOrCreate(
                ['user_id' => $user->getKey(), 'provider' => $provider],
                [
                    'provider_user_id' => $providerUserId,
                    'provider_email' => $identity['email'],
                    'provider_name' => $identity['name'],
                    'avatar_url' => $identity['avatar_url'],
                    'last_login_at' => now(),
                ],
            );

            return true;
        });
    }
}
