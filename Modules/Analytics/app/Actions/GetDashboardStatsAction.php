<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Analytics\Models\DailyLearningStat;

/** @phpstan-type DashboardStats array{questions_answered: int, questions_this_week: int, questions_delta: int|null, correct_rate: int, correct_rate_delta: int|null, study_minutes_this_week: int, streak_days: int} */
final class GetDashboardStatsAction
{
    use AsAction;

    /** @return DashboardStats */
    public function handle(User $user): array
    {
        $stats = DailyLearningStat::query()
            ->where('user_id', $user->getKey())
            ->orderBy('date')
            ->get();

        $currentWeek = $stats->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
        $previousWeek = $stats->whereBetween('date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);

        return [
            'questions_answered' => (int) $stats->sum('questions_answered'),
            'questions_this_week' => (int) $currentWeek->sum('questions_answered'),
            'questions_delta' => $this->difference($currentWeek, $previousWeek, 'questions_answered'),
            'correct_rate' => $this->correctRate($stats),
            'correct_rate_delta' => $previousWeek->sum('questions_answered') > 0
                ? $this->correctRate($currentWeek) - $this->correctRate($previousWeek)
                : null,
            'study_minutes_this_week' => (int) round($currentWeek->sum('study_seconds') / 60),
            'streak_days' => $this->streak($stats),
        ];
    }

    /** @param Collection<int, DailyLearningStat> $current @param Collection<int, DailyLearningStat> $previous */
    private function difference(Collection $current, Collection $previous, string $field): ?int
    {
        return $previous->isEmpty() ? null : (int) ($current->sum($field) - $previous->sum($field));
    }

    /** @param Collection<int, DailyLearningStat> $stats */
    private function correctRate(Collection $stats): int
    {
        $answered = (int) $stats->sum('questions_answered');

        return $answered > 0 ? (int) round($stats->sum('correct_answers') / $answered * 100) : 0;
    }

    /** @param Collection<int, DailyLearningStat> $stats */
    private function streak(Collection $stats): int
    {
        // A study streak represents consecutive days with learning activity.
        // Reaching the daily question target is tracked separately by
        // `daily_goal_reached` and must not prevent a learner from starting a
        // streak after completing fewer questions than their daily target.
        $activeDates = $stats
            ->filter(fn (DailyLearningStat $stat): bool => $stat->questions_answered > 0)
            ->mapWithKeys(fn (DailyLearningStat $stat): array => [$stat->date->toDateString() => true]);
        $cursor = Carbon::today();

        if (! $activeDates->has($cursor->toDateString())) {
            $cursor->subDay();
        }

        $streak = 0;
        while ($activeDates->has($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
