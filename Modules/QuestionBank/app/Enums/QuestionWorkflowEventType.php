<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum QuestionWorkflowEventType: string
{
    use EnumValues;

    case Submit = 'submit';
    case AdminReject = 'admin_reject';
    case DualRedReject = 'dual_red_reject';
    case Publish = 'publish';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Gửi duyệt',
            self::AdminReject => 'Admin trả về',
            self::DualRedReject => 'Hai cờ đỏ · trả về',
            self::Publish => 'Xuất bản',
        };
    }
}
