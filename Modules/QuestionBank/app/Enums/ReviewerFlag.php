<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum ReviewerFlag: string
{
    use EnumValues;

    case Green = 'green';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Cờ xanh — đạt',
            self::Red => 'Cờ đỏ — không đạt',
        };
    }

    /** Short outcome for icon pickers (UI). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Green => 'Đạt',
            self::Red => 'Không đạt',
        };
    }

    public function icon(): string
    {
        return 'flag';
    }

    /** Tailwind classes for selected / idle flag chooser chips. */
    public function chipClasses(bool $selected): string
    {
        return match ($this) {
            self::Green => $selected
                ? 'border-emerald-500 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-500/30'
                : 'border-outline-variant text-on-surface hover:border-emerald-300 hover:bg-emerald-50/50',
            self::Red => $selected
                ? 'border-rose-500 bg-rose-50 text-rose-900 ring-1 ring-rose-500/30'
                : 'border-outline-variant text-on-surface hover:border-rose-300 hover:bg-rose-50/50',
        };
    }

    public function iconClasses(bool $selected): string
    {
        return match ($this) {
            self::Green => $selected ? 'text-emerald-600' : 'text-emerald-500/70',
            self::Red => $selected ? 'text-rose-600' : 'text-rose-500/70',
        };
    }

    public function requiresNote(): bool
    {
        return $this === self::Red;
    }
}
