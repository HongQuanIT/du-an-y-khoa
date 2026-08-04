<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum PlanStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang học',
            self::Paused => 'Tạm dừng',
            self::Completed => 'Hoàn thành',
        };
    }
}
