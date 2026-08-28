<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ClassroomLifecycleStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Closed => 'Đã đóng',
            self::Archived => 'Đã lưu trữ',
        };
    }
}
