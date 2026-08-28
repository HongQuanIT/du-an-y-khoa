<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum LiveSessionStatus: string
{
    use EnumValues;

    case Scheduled = 'scheduled';
    case Starting = 'starting';
    case Live = 'live';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Đã lên lịch',
            self::Starting => 'Đang khởi tạo',
            self::Live => 'Đang live',
            self::Ended => 'Đã kết thúc',
            self::Cancelled => 'Đã hủy',
            self::Failed => 'Khởi tạo thất bại',
        };
    }

    public function isLive(): bool
    {
        return $this === self::Live;
    }

    public function allowsChatSend(): bool
    {
        return $this === self::Live;
    }
}
