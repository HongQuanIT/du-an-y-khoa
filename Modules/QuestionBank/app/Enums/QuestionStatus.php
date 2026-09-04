<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum QuestionStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case InReview = 'in_review';
    case PendingPublish = 'pending_publish';
    case Published = 'published';
    case Rejected = 'rejected';
    case Private = 'private';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::InReview => 'Chờ giảng viên duyệt',
            self::PendingPublish => 'Chờ xuất bản',
            self::Published => 'Đã xuất bản',
            self::Rejected => 'Từ chối',
            self::Private => 'Riêng tư (exam)',
            self::Retired => 'Ngừng dùng',
        };
    }
}
