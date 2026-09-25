<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Accountability mark on a reviewer flag (monthly standup).
 * Pending = default «Đúng» until Admin marks otherwise.
 */
enum ReviewFlagOutcome: string
{
    use EnumValues;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case FalsePositive = 'false_positive';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Pending, self::Confirmed => 'Đúng',
            self::FalsePositive => 'Sai',
            self::Inconclusive => 'Chưa rõ',
        };
    }

    public function isIncorrect(): bool
    {
        return $this === self::FalsePositive;
    }
}
