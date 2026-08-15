<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum CmsPageStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Published => 'Đã xuất bản',
        };
    }
}
