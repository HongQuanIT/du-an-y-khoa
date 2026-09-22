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

    public function tone(): string
    {
        return match ($this) {
            self::VeryEasy => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Easy => 'bg-lime-50 text-lime-800 border-lime-200',
            self::Medium => 'bg-sky-50 text-sky-800 border-sky-200',
            self::Hard => 'bg-amber-50 text-amber-800 border-amber-200',
            self::VeryHard => 'bg-rose-50 text-rose-800 border-rose-200',
        };
    }
}
