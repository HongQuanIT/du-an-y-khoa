<?php

declare(strict_types=1);

namespace Modules\Auth\Enums;

enum AuthenticationMethod: string
{
    case Email = 'email';
    case Google = 'google';
    case Facebook = 'facebook';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email/Mật khẩu',
            self::Google => 'Google',
            self::Facebook => 'Facebook',
        };
    }
}
