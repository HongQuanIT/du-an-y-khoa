<?php

declare(strict_types=1);

namespace Modules\Admin\Enums;

enum ReportScheduleFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Hàng ngày',
            self::Weekly => 'Hàng tuần',
            self::Monthly => 'Hàng tháng',
        };
    }
}
