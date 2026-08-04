<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum PlanStrategy: string
{
    use EnumValues;

    case Fixed = 'fixed';
    case Adaptive = 'adaptive';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Cố định',
            self::Adaptive => 'Thích ứng',
        };
    }
}
