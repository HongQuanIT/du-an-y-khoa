<?php

declare(strict_types=1);

namespace Modules\Media\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum MediaJobStatus: string
{
    use EnumValues;

    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
