<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum QuestionStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';
}
