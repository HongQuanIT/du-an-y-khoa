<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Models\StudyPlan;

/** Rebuilds the learner rollup from authoritative attempts and sessions. */
final class RecalculateDailyLearningStatsAction
{
    use AsAction;

    private const DAILY_QUESTION_GOAL = 10;

    /** @param array<int, string>|null $onlyDates */
    public function handle(int $userId, ?array $onlyDates = null): void
    {
        $onlyDates = $onlyDates === null
            ? null
            : collect($onlyDates)->filter()->unique()->values()->all();

        if ($onlyDates === []) {
            return;
        }

        $dailyQuestionGoal = (int) (StudyPlan::query()
            ->where('user_id', $userId)
            ->where('status', PlanStatus::Active)
            ->latest('id')
            ->value('daily_goal_questions') ?? self::DAILY_QUESTION_GOAL);
        $attemptQuery = QuestionAttempt::query()
            ->where('user_id', $userId)
            ->whereNotNull('answered_at')
            ->whereNotNull('is_correct');
        $sessionQuery = QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed);

        if ($onlyDates !== null) {
            $attemptQuery->where(function ($query) use ($onlyDates): void {
                foreach ($onlyDates as $date) {
                    $query->orWhereDate('answered_at', $date);
                }
            });
            $sessionQuery->where(function ($query) use ($onlyDates): void {
                foreach ($onlyDates as $date) {
                    $query->orWhereDate('updated_at', $date);
                }
            });
        }

        $attempts = $attemptQuery
            ->get(['is_correct', 'time_spent_seconds', 'answered_at'])
            ->groupBy(fn (QuestionAttempt $attempt): string => $attempt->answered_at->toDateString());
        $sessions = $sessionQuery
            ->get(['answered_count', 'correct_count', 'updated_at'])
            ->groupBy(fn (QuestionSession $session): string => $session->updated_at->toDateString());

        $dates = collect($onlyDates ?? [])
            ->merge($attempts->keys())
            ->merge($sessions->keys())
            ->unique()
            ->sort()
            ->values();
        $now = now();
        $rows = $dates->map(function (string $date) use ($attempts, $dailyQuestionGoal, $sessions, $userId, $now): array {
            $dailyAttempts = $attempts->get($date, collect());
            $dailySessions = $sessions->get($date, collect());
            $answered = (int) $dailySessions->sum('answered_count');

            return [
                'user_id' => $userId,
                'date' => $date,
                'questions_answered' => $answered,
                'correct_answers' => (int) $dailySessions->sum('correct_count'),
                'study_seconds' => (int) $dailyAttempts->sum('time_spent_seconds'),
                'completed_sessions' => $dailySessions->count(),
                'daily_goal_reached' => $answered >= $dailyQuestionGoal,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::transaction(function () use ($dates, $onlyDates, $rows, $userId): void {
            $deleteQuery = DailyLearningStat::query()->where('user_id', $userId);
            if ($onlyDates !== null) {
                $deleteQuery->whereIn('date', $dates->all());
            }
            $deleteQuery->delete();

            if ($rows !== []) {
                DailyLearningStat::query()->insert($rows);
            }
        });
    }
}
