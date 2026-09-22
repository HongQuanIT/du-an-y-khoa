<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ClassroomStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::PendingApproval => 'Chờ duyệt',
            self::Active => 'Đang hoạt động',
            self::Closed => 'Đã đóng',
            self::Archived => 'Đã lưu trữ',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-50 text-slate-700 border-slate-200',
            self::PendingApproval => 'bg-amber-50 text-amber-800 border-amber-200',
            self::Active => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Closed => 'bg-sky-50 text-sky-800 border-sky-200',
            self::Archived => 'bg-surface-container-high text-on-surface-variant border-outline-variant',
        };
    }

    public function isVisibleToLearners(): bool
    {
        return $this === self::Active;
    }
}
