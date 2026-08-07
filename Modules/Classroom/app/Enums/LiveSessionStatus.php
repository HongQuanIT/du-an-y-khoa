<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum LiveSessionStatus: string
{
    use EnumValues;

    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Đã lên lịch',
            self::Live => 'Đang live',
            self::Ended => 'Đã kết thúc',
            self::Cancelled => 'Đã hủy',
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
