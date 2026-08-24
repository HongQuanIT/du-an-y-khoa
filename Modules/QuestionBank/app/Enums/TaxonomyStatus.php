<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

enum TaxonomyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
