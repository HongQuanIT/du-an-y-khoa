<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum MenuKey: string
{
    use EnumValues;

    case Header = 'header';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Menu header (nav)',
            self::Footer => 'Menu footer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Header => 'Liên kết điều hướng trên thanh header landing (desktop + mobile).',
            self::Footer => 'Cột liên kết và mô tả thương hiệu ở chân trang công khai.',
        };
    }
}
