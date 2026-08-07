<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum MemberStatus: string
{
    use EnumValues;

    case Invited = 'invited';
    case Active = 'active';
    case Left = 'left';
    case Banned = 'banned';
}
