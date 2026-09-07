<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionStatus;

/**
 * Maintains per-question mastery and spaced-repetition state.
 */
final class QuestionLearningState
{
    public function record(
        int $userId,
        Question $question,
        UserQuestionStatus $result,
        Carbon $attemptedAt,
        bool $incrementAttempts,
        bool $usedHint = false,
    ): QuestionStatus {
        $state = QuestionStatus::firstOrNew([
            'user_id' => $userId,
            'question_id' => $question->getKey(),
        ]);

        // Study-mode completion replays already graded attempts to repair
        // missing rollups. Do not advance mastery twice for the same answer.
        if (
            ! $incrementAttempts
            && $state->exists
            && $state->last_attempt_at?->equalTo($attemptedAt)
        ) {
            return $state;
        }

        $attempts = (int) ($state->attempts_count ?? 0);
        $coverageCount = (int) ($state->coverage_count ?? 0);
        $countsForCoverage = in_array($result, [
            UserQuestionStatus::Correct,
            UserQuestionStatus::Incorrect,
        ], true);
        if ($incrementAttempts) {
            $attempts++;
            if ($countsForCoverage) {
                $coverageCount++;
            }
        } elseif (! $state->exists) {
            $attempts = 1;
            $coverageCount = $countsForCoverage ? 1 : 0;
        }

        $isCorrect = $result === UserQuestionStatus::Correct;
        $isIncorrect = $result === UserQuestionStatus::Incorrect;
        $streak = $isCorrect ? (int) ($state->correct_streak ?? 0) + 1 : 0;
        $incorrectCount = (int) ($state->incorrect_count ?? 0) + ($isIncorrect ? 1 : 0);
        $mastery = $this->masteryScore((int) ($state->mastery_score ?? 0), $result, $usedHint);
        $easeFactor = $this->easeFactor((float) ($state->ease_factor ?: 2.5), $result, $usedHint);
        $interval = $this->reviewInterval(
            previousInterval: (int) ($state->review_interval_days ?? 0),
            correctStreak: $streak,
            result: $result,
            usedHint: $usedHint,
            easeFactor: $easeFactor,
        );

        $storedStatus = $state->exists && $state->status === UserQuestionStatus::Marked
            ? UserQuestionStatus::Marked
            : $result;

        $state->fill([
            'status' => $storedStatus,
            'attempts_count' => $attempts,
            'coverage_count' => $coverageCount,
            'incorrect_count' => $incorrectCount,
            'correct_streak' => $streak,
            'mastery_score' => $mastery,
            'review_interval_days' => $interval,
            'ease_factor' => $easeFactor,
            'last_used_hint' => $usedHint,
            'last_attempt_at' => $attemptedAt,
            'last_correct_at' => $isCorrect ? $attemptedAt : $state->last_correct_at,
            'next_review_at' => $attemptedAt->copy()->addDays($interval),
        ])->save();

        return $state;
    }

    private function masteryScore(
        int $current,
        UserQuestionStatus $result,
        bool $usedHint,
    ): int {
        $change = match ($result) {
            UserQuestionStatus::Correct => $usedHint ? 8 : 18,
            UserQuestionStatus::Incorrect => -25,
            UserQuestionStatus::Omitted => -10,
            default => 0,
        };

        return max(0, min(100, $current + $change));
    }

    private function easeFactor(
        float $current,
        UserQuestionStatus $result,
        bool $usedHint,
    ): float {
        $change = match ($result) {
            UserQuestionStatus::Correct => $usedHint ? -0.05 : 0.10,
            UserQuestionStatus::Incorrect => -0.20,
            UserQuestionStatus::Omitted => -0.10,
            default => 0.0,
        };

        return round(max(1.30, min(3.00, $current + $change)), 2);
    }

    private function reviewInterval(
        int $previousInterval,
        int $correctStreak,
        UserQuestionStatus $result,
        bool $usedHint,
        float $easeFactor,
    ): int {
        if ($result !== UserQuestionStatus::Correct || $usedHint) {
            return 1;
        }

        return match ($correctStreak) {
            1 => 3,
            2 => 7,
            3 => 14,
            default => min(120, max(21, (int) round(max(1, $previousInterval) * $easeFactor))),
        };
    }
}
