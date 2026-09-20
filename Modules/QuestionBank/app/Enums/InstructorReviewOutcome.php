<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum InstructorReviewOutcome: string
{
    use EnumValues;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Miss = 'miss';
    case OverReject = 'over_reject';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa đánh giá',
            self::Confirmed => 'Duyệt đúng',
            // miss (approve sai) + over_reject (reject sai) = cùng nhãn «Duyệt sai» cho Admin.
            self::Miss, self::OverReject => 'Duyệt sai',
            self::Inconclusive => 'Chưa rõ',
        };
    }

    public function isIncorrect(): bool
    {
        return $this === self::Miss || $this === self::OverReject;
    }
}
