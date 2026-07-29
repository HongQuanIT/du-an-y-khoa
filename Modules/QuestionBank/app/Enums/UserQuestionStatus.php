<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Per-user progress state for a single question (kien-truc §3, ERD nhóm 3).
 */
enum UserQuestionStatus: string
{
    use EnumValues;

    case Unseen = 'unseen';
    case Incorrect = 'incorrect';
    case Correct = 'correct';
    case Omitted = 'omitted';
    case Marked = 'marked';
}
