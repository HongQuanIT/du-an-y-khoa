<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Accountability mark on editor submit events (working-copy quality).
 *
 * Pending / Confirmed = «Đúng» (default). NeedsRework = «Sai» (đánh dấu khi họp giao ban).
 */
enum EditorSubmitOutcome: string
{
    use EnumValues;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case NeedsRework = 'needs_rework';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Pending, self::Confirmed => 'Đúng',
            self::NeedsRework => 'Sai',
            self::Inconclusive => 'Chưa rõ',
        };
    }

    public function isIncorrect(): bool
    {
        return $this === self::NeedsRework;
    }
}
