<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ReviewerFlag: string
{
    use EnumValues;

    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Cờ xanh — đạt',
            self::Yellow => 'Cờ vàng — cần lưu ý',
            self::Red => 'Cờ đỏ — không đạt',
        };
    }
}
