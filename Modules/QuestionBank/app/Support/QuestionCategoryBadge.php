<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\Lesson;

/**
 * Compact category + difficulty chips for the session/exam chrome.
 *
 * A question can attach several bài học (lessons); the player only needs a
 * high-level orientation label, so we surface the primary lesson (or the first
 * available) rather than every lesson.
 */
final class QuestionCategoryBadge
{
    /**
     * @param  Collection<int, Lesson>|iterable<Lesson>  $lessons
     * @return array{category: string, difficulty: string, difficulty_tone: string}
     */
    public static function resolve(iterable $lessons, Difficulty|string|null $difficulty): array
    {
        $collection = $lessons instanceof Collection ? $lessons : collect($lessons);
        $difficultyEnum = $difficulty instanceof Difficulty
            ? $difficulty
            : (is_string($difficulty) && $difficulty !== '' ? Difficulty::tryFrom($difficulty) : null);

        return [
            'category' => self::categoryLabel($collection),
            'difficulty' => $difficultyEnum?->label() ?? '—',
            'difficulty_tone' => self::difficultyTone($difficultyEnum),
        ];
    }

    /**
     * @param  Collection<int, Lesson>  $lessons
     */
    public static function categoryLabel(Collection $lessons): string
    {
        if ($lessons->isEmpty()) {
            return 'Tổng hợp';
        }

        $lesson = $lessons->first();

        return self::shorten((string) ($lesson?->name ?? '')) ?: 'Tổng hợp';
    }

    public static function difficultyTone(?Difficulty $difficulty): string
    {
        return match ($difficulty) {
            Difficulty::VeryEasy, Difficulty::Easy => 'bg-emerald-50 text-emerald-700',
            Difficulty::Hard, Difficulty::VeryHard => 'bg-amber-50 text-amber-800',
            Difficulty::Medium => 'bg-sky-50 text-sky-800',
            default => 'bg-surface-container-highest text-on-surface-variant',
        };
    }

    private static function shorten(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Tổng hợp';
        }

        if (mb_strlen($name) > 28 && str_starts_with($name, 'Hệ ')) {
            $name = mb_substr($name, 3);
        }

        if (mb_strlen($name) > 32) {
            return mb_substr($name, 0, 31).'…';
        }

        return $name;
    }
}
