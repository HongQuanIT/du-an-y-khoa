<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Models\TopicMastery;

/**
 * Use case: rebuild a learner's per-topic accuracy from their attempts.
 *
 * Rebuilding (rather than incrementing) keeps the rollup correct even when
 * attempts are backfilled by seeders or imports.
 */
final class RecalculateTopicMasteryAction
{
    use AsAction;

    /** Below this many attempts a topic is not rated yet. */
    private const MIN_ATTEMPTS = 5;

    public function handle(int $userId): int
    {
        $rows = DB::table('question_attempts')
            ->join('questions', 'questions.id', '=', 'question_attempts.question_id')
            ->where('question_attempts.user_id', $userId)
            ->whereNotNull('question_attempts.is_correct')
            ->whereNotNull('questions.topic_id')
            ->groupBy('questions.topic_id')
            ->get([
                'questions.topic_id',
                DB::raw('COUNT(*) as attempts'),
                DB::raw('SUM(CASE WHEN question_attempts.is_correct THEN 1 ELSE 0 END) as correct'),
                DB::raw('MAX(question_attempts.answered_at) as last_activity_at'),
            ]);

        foreach ($rows as $row) {
            $attempts = (int) $row->attempts;
            $correct = (int) $row->correct;
            $rate = $attempts > 0 ? round($correct / $attempts * 100, 2) : 0.0;

            TopicMastery::updateOrCreate(
                ['user_id' => $userId, 'topic_id' => (int) $row->topic_id],
                [
                    'attempts' => $attempts,
                    'correct' => $correct,
                    'correct_rate' => $rate,
                    'mastery_level' => $this->masteryLevel($attempts, $rate),
                    'last_activity_at' => $row->last_activity_at
                        ? Carbon::parse($row->last_activity_at)
                        : null,
                ],
            );
        }

        return $rows->count();
    }

    private function masteryLevel(int $attempts, float $rate): int
    {
        if ($attempts < self::MIN_ATTEMPTS) {
            return 0;
        }

        return match (true) {
            $rate >= 90 => 5,
            $rate >= 80 => 4,
            $rate >= 70 => 3,
            $rate >= 60 => 2,
            default => 1,
        };
    }
}
