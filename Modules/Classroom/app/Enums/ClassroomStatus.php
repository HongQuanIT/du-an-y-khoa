<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ClassroomStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Active => 'Đang hoạt động',
            self::Archived => 'Đã lưu trữ',
        };
    }
}
