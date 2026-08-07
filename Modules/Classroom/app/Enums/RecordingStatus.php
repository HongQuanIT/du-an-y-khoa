<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum RecordingStatus: string
{
    use EnumValues;

    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
