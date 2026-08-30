<?php

declare(strict_types=1);

namespace Modules\Admin\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ContactInquiryStatus: string
{
    use EnumValues;

    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Spam = 'spam';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::InProgress => 'Đang xử lý',
            self::Resolved => 'Đã xử lý',
            self::Spam => 'Spam',
            self::Archived => 'Lưu trữ',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'bg-amber-50 text-amber-800 border-amber-200',
            self::InProgress => 'bg-sky-50 text-sky-800 border-sky-200',
            self::Resolved => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Spam => 'bg-rose-50 text-rose-800 border-rose-200',
            self::Archived => 'bg-surface-container-high text-on-surface-variant border-outline-variant',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::InProgress], true);
    }
}
