<?php

declare(strict_types=1);

namespace Modules\Auth\Data;

use App\Support\Data\DataTransferObject;
use Illuminate\Support\Str;

/**
 * Validated credentials for a session (web guard) login attempt.
 */
final class LoginData extends DataTransferObject
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $remember = false,
        public readonly ?string $ip = null,
    ) {}

    /**
     * Per-account lockout key so one attacker cannot lock every account.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.($this->ip ?? 'unknown'));
    }
}
