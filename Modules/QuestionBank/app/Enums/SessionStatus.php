<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum SessionStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Expired = 'expired';
    case Abandoned = 'abandoned';
}
