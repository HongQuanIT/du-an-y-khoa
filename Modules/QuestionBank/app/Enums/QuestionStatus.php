<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum QuestionStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case InReview = 'in_review';
    case InFlagReview = 'in_flag_review';
    case FlagConflict = 'flag_conflict';
    case PendingPublish = 'pending_publish';
    case Published = 'published';
    case Rejected = 'rejected';
    case Private = 'private';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::InReview => 'Chờ giảng viên',
            self::InFlagReview => 'Chờ reviewer',
            self::FlagConflict => 'Cảnh báo cờ',
            self::PendingPublish => 'Chờ xuất bản',
            self::Published => 'Đã xuất bản',
            self::Rejected => 'Từ chối',
            self::Private => 'Riêng tư (exam)',
            self::Retired => 'Ngừng dùng',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-50 text-slate-700 border-slate-200',
            self::InReview => 'bg-amber-50 text-amber-800 border-amber-200',
            self::InFlagReview => 'bg-sky-50 text-sky-800 border-sky-200',
            self::FlagConflict => 'bg-amber-50 text-amber-900 border-amber-300',
            self::PendingPublish => 'bg-violet-50 text-violet-800 border-violet-200',
            self::Published => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Rejected => 'bg-rose-50 text-rose-800 border-rose-200',
            self::Private => 'bg-indigo-50 text-indigo-800 border-indigo-200',
            self::Retired => 'bg-surface-container-high text-on-surface-variant border-outline-variant',
        };
    }
}
