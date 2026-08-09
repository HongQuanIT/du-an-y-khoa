<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Account lifecycle status (srs Identity User.status).
 */
enum UserStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Pending => 'Chờ xác minh',
            self::Suspended => 'Tạm khóa',
            self::Banned => 'Cấm',
        };
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }
}
