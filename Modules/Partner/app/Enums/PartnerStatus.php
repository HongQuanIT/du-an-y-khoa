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
}
