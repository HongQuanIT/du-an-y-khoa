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

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Pending => 'bg-amber-50 text-amber-800 border-amber-200',
            self::Suspended => 'bg-slate-50 text-slate-700 border-slate-200',
            self::Banned => 'bg-rose-50 text-rose-800 border-rose-200',
        };
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }
}
