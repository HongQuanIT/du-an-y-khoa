<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum Difficulty: string
{
    use EnumValues;

    case VeryEasy = 'very_easy';
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';
    case VeryHard = 'very_hard';

    public function label(): string
    {
        return match ($this) {
            self::VeryEasy => 'Rất dễ',
            self::Easy => 'Dễ',
            self::Medium => 'Trung bình',
            self::Hard => 'Khó',
            self::VeryHard => 'Rất khó',
        };
    }
}
