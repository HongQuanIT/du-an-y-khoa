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
            self::Confirmed => 'Quyết định đúng',
            self::Miss => 'Duyệt sót',
            self::OverReject => 'Từ chối oan',
            self::Inconclusive => 'Chưa rõ',
        };
    }
}
