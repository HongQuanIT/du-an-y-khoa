<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ClassroomVisibility: string
{
    use EnumValues;

    case Public = 'public';
    case Unlisted = 'unlisted';
    case InviteOnly = 'invite_only';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Công khai',
            self::Unlisted => 'Không liệt kê (link/mã)',
            self::InviteOnly => 'Chỉ mời',
        };
    }
}
