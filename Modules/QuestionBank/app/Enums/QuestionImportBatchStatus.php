<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum QuestionImportBatchStatus: string
{
    use EnumValues;

    case Uploaded = 'uploaded';
    case Mapped = 'mapped';
    case Validated = 'validated';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Đã tải tệp',
            self::Mapped => 'Đã ánh xạ cột',
            self::Validated => 'Đã kiểm tra',
            self::Done => 'Hoàn tất',
            self::Failed => 'Thất bại',
        };
    }
}
