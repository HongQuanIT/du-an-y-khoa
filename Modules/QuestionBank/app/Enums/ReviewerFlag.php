<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ReviewerFlag: string
{
    use EnumValues;

    case Green = 'green';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Cờ xanh — đạt',
            self::Red => 'Cờ đỏ — không đạt',
        };
    }

    public function requiresNote(): bool
    {
        return $this === self::Red;
    }
}
