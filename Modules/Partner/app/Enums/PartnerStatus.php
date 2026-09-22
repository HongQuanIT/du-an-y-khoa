<?php

declare(strict_types=1);

namespace Modules\Partner\Enums;

enum PartnerStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Hoạt động',
            self::Suspended => 'Tạm dừng',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Suspended => 'bg-amber-50 text-amber-800 border-amber-200',
        };
    }
}
