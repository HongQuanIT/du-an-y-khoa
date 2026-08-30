<?php

declare(strict_types=1);

namespace Modules\Partner\Enums;

enum PayoutStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Approved => 'Đã duyệt',
            self::Paid => 'Đã chi',
            self::Cancelled => 'Hủy',
        };
    }
}
