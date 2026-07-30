<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Data\LoginData;

/**
 * Use case: authenticate a user on the `web` guard.
 *
 * Session regeneration stays in the HTTP layer; this action owns credential
 * checking and the per-account lockout.
 */
final class AttemptLoginAction
{
    use AsAction;

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * @throws ValidationException when locked out or credentials are wrong
     */
    public function handle(LoginData $data): User
    {
        $key = $data->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau '
                    .RateLimiter::availableIn($key).' giây.',
            ]);
        }

        $credentials = ['email' => $data->email, 'password' => $data->password];

        if (! Auth::attempt($credentials, $data->remember)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        RateLimiter::clear($key);

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
