<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum QuestionStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::InReview => 'Chờ duyệt',
            self::Published => 'Đã xuất bản',
            self::Retired => 'Ngừng dùng',
        };
    }
}
