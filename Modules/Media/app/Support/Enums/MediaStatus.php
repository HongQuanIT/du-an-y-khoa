<?php

declare(strict_types=1);

namespace Modules\Media\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum MediaStatus: string
{
    use EnumValues;

    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Đang xử lý',
            self::Ready => 'Sẵn sàng',
            self::Failed => 'Lỗi',
        };
    }
}
