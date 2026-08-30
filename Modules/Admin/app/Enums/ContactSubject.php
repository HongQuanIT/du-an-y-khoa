<?php

declare(strict_types=1);

namespace Modules\Admin\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ContactSubject: string
{
    use EnumValues;

    case Account = 'account';
    case Payment = 'payment';
    case Partnership = 'partnership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Hỗ trợ tài khoản',
            self::Payment => 'Thanh toán',
            self::Partnership => 'Hợp tác',
            self::Other => 'Khác',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Account => 'manage_accounts',
            self::Payment => 'payments',
            self::Partnership => 'handshake',
            self::Other => 'chat',
        };
    }
}
