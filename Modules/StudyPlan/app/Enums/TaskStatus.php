<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum TaskStatus: string
{
    use EnumValues;

    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa xong',
            self::Done => 'Hoàn thành',
            self::Skipped => 'Bỏ qua',
        };
    }
}
