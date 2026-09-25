<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum QuestionRejectReasonCode: string
{
    use EnumValues;

    case Instructor = 'instructor';
    case DualRed = 'dual_red';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Instructor => 'Giảng viên từ chối',
            self::DualRed => 'Hai reviewer gắn cờ đỏ',
            self::Admin => 'Admin trả về',
        };
    }
}
