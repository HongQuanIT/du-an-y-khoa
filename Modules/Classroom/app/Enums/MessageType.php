<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum MessageType: string
{
    use EnumValues;

    case Chat = 'chat';
    case Question = 'question';
    case System = 'system';
}
