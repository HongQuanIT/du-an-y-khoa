<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum SessionMode: string
{
    use EnumValues;

    case Study = 'study';
    case Exam = 'exam';
}
