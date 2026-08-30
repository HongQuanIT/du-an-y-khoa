<?php

declare(strict_types=1);

namespace Modules\Partner\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Paid => 'Đã chi',
            self::Void => 'Hủy',
        };
    }
}
