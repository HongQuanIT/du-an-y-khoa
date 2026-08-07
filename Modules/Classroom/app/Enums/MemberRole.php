<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum MemberRole: string
{
    use EnumValues;

    case Host = 'host';
    case Cohost = 'cohost';
    case Member = 'member';

    public function canPublish(): bool
    {
        return $this === self::Host || $this === self::Cohost;
    }

    public function canModerate(): bool
    {
        return $this->canPublish();
    }
}
