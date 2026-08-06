<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Modules\QuestionBank\Models\Question;

/**
 * Exact-set grading shared by immediate Study answers and deferred Exam
 * completion. Multi-select answers are correct only when every correct option
 * and no incorrect option is selected.
 */
final class QuestionGrader
{
    /**
     * @param  array<int, int>  $selectedOptionIds
     */
    public function isCorrect(Question $question, array $selectedOptionIds): bool
    {
        $correctIds = ($question->relationLoaded('options')
            ? $question->options
            : $question->options()->get()
        )
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $selected = array_values(array_unique(array_map('intval', $selectedOptionIds)));
        sort($selected);

        return $correctIds !== [] && $correctIds === $selected;
    }
}
