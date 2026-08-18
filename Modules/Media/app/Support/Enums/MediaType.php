<?php

declare(strict_types=1);

namespace Modules\Media\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum MediaType: string
{
    use EnumValues;

    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Ảnh',
            self::Video => 'Video',
            self::Audio => 'Âm thanh',
            self::Document => 'Tài liệu',
        };
    }
}
