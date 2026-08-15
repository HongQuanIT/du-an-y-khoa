<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum BannerVariant: string
{
    use EnumValues;

    case Info = 'info';
    case Promo = 'promo';
    case Warning = 'warning';
    case Success = 'success';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Thông tin',
            self::Promo => 'Khuyến mãi',
            self::Warning => 'Cảnh báo',
            self::Success => 'Thành công',
        };
    }
}
