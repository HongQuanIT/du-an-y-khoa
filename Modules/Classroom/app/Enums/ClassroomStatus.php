<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ClassroomStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
