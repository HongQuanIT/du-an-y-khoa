<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum BannerAudience: string
{
    use EnumValues;

    case All = 'all';
    case Guests = 'guests';
    case Authenticated = 'authenticated';
    case Free = 'free';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tất cả mọi người',
            self::Guests => 'Khách (chưa đăng nhập)',
            self::Authenticated => 'Đã đăng nhập',
            self::Free => 'Học viên Free',
            self::Premium => 'Học viên Premium',
        };
    }
}
