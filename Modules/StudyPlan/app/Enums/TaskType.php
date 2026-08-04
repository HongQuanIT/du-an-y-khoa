<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Task types from srs/modules/04-study-plan.md §5.
 *
 * Only `questions` and `review` are generated today; `read` and `flashcards`
 * wait for Library/Personalization persistence (see `isSupported()`).
 */
enum TaskType: string
{
    use EnumValues;

    case Questions = 'questions';
    case Read = 'read';
    case Flashcards = 'flashcards';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Questions => 'Luyện đề',
            self::Read => 'Lý thuyết',
            self::Flashcards => 'Ghi nhớ',
            self::Review => 'Ôn câu sai',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Questions => 'fact_check',
            self::Read => 'menu_book',
            self::Flashcards => 'style',
            self::Review => 'replay',
        };
    }

    /** Whether the downstream module can actually run this task type. */
    public function isSupported(): bool
    {
        return in_array($this, [self::Questions, self::Review], true);
    }
}
