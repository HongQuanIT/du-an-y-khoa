<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Models\QuestionAttempt;

/**
 * Streak = consecutive calendar days (app timezone) meeting daily goal.
 * Daily goal = answered question attempts count >= min_questions_per_day.
 */
final class StudyStreakCalculator
{
    public function currentStreak(User $user, ?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $dates = $this->activeDates($user, $asOf);

        if ($dates->isEmpty()) {
            return 0;
        }

        $cursor = $asOf->copy();
        if (! $dates->contains($cursor->toDateString())) {
            $cursor->subDay();
        }

        $streak = 0;
        while ($dates->contains($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    public function metGoalOn(User $user, Carbon $day): bool
    {
        return $this->activeDates($user, $day)->contains($day->toDateString());
    }

    /**
     * @return Collection<int, string> Y-m-d strings
     */
    public function activeDates(User $user, Carbon $asOf): Collection
    {
        $minQuestions = max(1, (int) config('notification.streak.min_questions_per_day', 1));
        $lookback = max(7, (int) config('notification.streak.lookback_days', 90));
        $from = $asOf->copy()->subDays($lookback)->startOfDay();

        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', COALESCE(answered_at, created_at))",
            default => 'DATE(COALESCE(answered_at, created_at))',
        };

        return QuestionAttempt::query()
            ->where('user_id', $user->getKey())
            ->where(function ($query) use ($from): void {
                $query
                    ->where('answered_at', '>=', $from)
                    ->orWhere(function ($inner) use ($from): void {
                        $inner->whereNull('answered_at')->where('created_at', '>=', $from);
                    });
            })
            ->selectRaw("{$dateExpr} as study_date")
            ->selectRaw('COUNT(*) as answers')
            ->groupBy('study_date')
            ->havingRaw('COUNT(*) >= ?', [$minQuestions])
            ->pluck('study_date')
            ->map(fn ($date): string => Carbon::parse((string) $date)->toDateString())
            ->unique()
            ->values();
    }
}
