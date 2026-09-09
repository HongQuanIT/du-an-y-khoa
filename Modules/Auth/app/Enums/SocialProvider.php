<?php

declare(strict_types=1);

namespace Modules\Auth\Enums;

enum SocialProvider: string
{
    case Google = 'google';
    case Facebook = 'facebook';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
        };
    }
}
