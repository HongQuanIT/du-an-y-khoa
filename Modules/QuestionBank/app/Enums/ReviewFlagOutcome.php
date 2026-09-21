<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

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
            self::Pending => 'Chưa đánh giá',
            self::Confirmed => 'Gắn đúng',
            self::FalsePositive => 'Gắn sai',
            self::Inconclusive => 'Chưa rõ',
        };
    }
}
