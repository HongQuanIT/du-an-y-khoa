<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum Difficulty: string
{
    use EnumValues;

    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';
}
