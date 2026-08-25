<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Analytics\Actions\ListWeakTopicsAction;
use Modules\Analytics\Actions\ResolveContinueLearningAction;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\StudyPlan\Actions\ListTodayTasksAction;
use Modules\StudyPlan\Repositories\StudyPlanRepository;

/**
 * Student dashboard — the landing page after login (srs/modules/03).
 *
 * Study-plan tasks, weak topics, learning progress and "continue learning"
 * are generated from the authenticated student's data.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly StudyPlanRepository $plans,
        private readonly ListTodayTasksAction $todayTasks,
        private readonly ListWeakTopicsAction $weakTopics,
        private readonly ResolveContinueLearningAction $continueLearning,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $plan = $this->plans->currentFor($user);
        $range = in_array($request->query('range'), ['7d', '30d', 'all'], true)
            ? (string) $request->query('range')
            : '30d';
        $progress = $this->learningProgress((int) $user->getKey(), $range);
        $streakDays = $this->streakDays((int) $user->getKey());

        return view('analytics::dashboard', [
            'plan' => $plan,
            'planTasks' => $plan !== null ? $this->todayTasks->handle($plan, 3) : collect(),
            'weakTopics' => $this->weakTopics->handle($user),
            'continueCard' => $this->continueLearning->handle($user),
            'streakDays' => $streakDays,
            'headlineStats' => $this->headlineStats((int) $user->getKey(), $streakDays),
            'recentActivities' => $this->recentActivities((int) $user->getKey()),
            'progressRange' => $range,
            'chartBars' => $progress['days'],
            'progressSummary' => $progress['summary'],
        ]);
    }

    /**
     * @return list<array{icon: string, circle: string, iconClass: string, title: string, detail: string, occurred_at: string, time: string, url: string}>
     */
    private function recentActivities(int $userId): array
    {
        return QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->whereNotNull('updated_at')
            ->withSum('attempts as time_spent_seconds', 'time_spent_seconds')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function (QuestionSession $session): array {
                $answered = (int) $session->answered_count;
                $correct = (int) $session->correct_count;
                $rate = $answered > 0 ? (int) round($correct / $answered * 100) : 0;
                $duration = $this->formatDuration((int) ($session->getAttribute('time_spent_seconds') ?? 0));
                $isExam = $session->mode === SessionMode::Exam;
                $occurredAt = $session->updated_at ?? Carbon::now();

                return [
                    'icon' => $isExam ? 'quiz' : 'fact_check',
                    'circle' => $isExam ? 'bg-secondary-fixed' : 'bg-primary-container',
                    'iconClass' => $isExam ? 'text-secondary' : 'text-primary',
                    'title' => 'Hoàn thành '.Str::lcfirst($session->displayName()),
                    'detail' => "Đúng {$correct}/{$answered} câu ({$rate}%) · {$duration}",
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'time' => $occurredAt->locale('vi')->diffForHumans(),
                    'url' => route($isExam ? 'exam.summary' : 'qbank.summary', $session),
                ];
            })
            ->all();
    }

    /**
     * @return array{
     *     questions: array{value: string, delta: string|null},
     *     accuracy: array{value: string, delta: string|null},
     *     weekly_time: array{value: string, delta: null},
     *     streak: array{value: string, delta: null}
     * }
     */
    private function headlineStats(int $userId, int $streakDays): array
    {
        $lifetime = QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->selectRaw('COALESCE(SUM(answered_count), 0) as questions')
            ->selectRaw('COALESCE(SUM(correct_count), 0) as correct')
            ->first();

        $weekStart = Carbon::now()->startOfWeek();
        $previousWeekStart = $weekStart->copy()->subWeek();
        $previousWeekEnd = $weekStart->copy()->subSecond();
        $currentWeek = $this->completedSessionTotals($userId, $weekStart, Carbon::now());
        $previousWeek = $this->completedSessionTotals($userId, $previousWeekStart, $previousWeekEnd);
        $lifetimeQuestions = (int) ($lifetime?->getAttribute('questions') ?? 0);
        $lifetimeCorrect = (int) ($lifetime?->getAttribute('correct') ?? 0);
        $lifetimeRate = $lifetimeQuestions > 0
            ? (int) round($lifetimeCorrect / $lifetimeQuestions * 100)
            : 0;
        $accuracyDelta = $previousWeek['questions'] > 0
            ? $currentWeek['rate'] - $previousWeek['rate']
            : null;
        $weeklySeconds = (int) QuestionAttempt::query()
            ->where('user_id', $userId)
            ->whereBetween('answered_at', [$weekStart, Carbon::now()])
            ->sum('time_spent_seconds');

        return [
            'questions' => [
                'value' => number_format($lifetimeQuestions, 0, ',', '.'),
                'delta' => $currentWeek['questions'] > 0 ? '+'.$currentWeek['questions'].' tuần này' : null,
            ],
            'accuracy' => [
                'value' => $lifetimeRate.'%',
                'delta' => $accuracyDelta !== null
                    ? ($accuracyDelta > 0 ? '+' : '').$accuracyDelta.'%'
                    : null,
            ],
            'weekly_time' => [
                'value' => $this->formatDuration($weeklySeconds),
                'delta' => null,
            ],
            'streak' => [
                'value' => (string) $streakDays,
                'delta' => null,
            ],
        ];
    }

    /** @return array{questions: int, correct: int, rate: int} */
    private function completedSessionTotals(int $userId, Carbon $from, Carbon $to): array
    {
        $totals = QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->whereBetween('updated_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(answered_count), 0) as questions')
            ->selectRaw('COALESCE(SUM(correct_count), 0) as correct')
            ->first();
        $questions = (int) ($totals?->getAttribute('questions') ?? 0);
        $correct = (int) ($totals?->getAttribute('correct') ?? 0);

        return [
            'questions' => $questions,
            'correct' => $correct,
            'rate' => $questions > 0 ? (int) round($correct / $questions * 100) : 0,
        ];
    }

    private function formatDuration(int $seconds): string
    {
        $minutes = (int) floor($seconds / 60);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return $minutes.' phút';
        }

        return $remainingMinutes > 0
            ? $hours.'h '.$remainingMinutes.'m'
            : $hours.'h';
    }

    /**
     * @return array{
     *     days: list<array{date: string, label: string, display_date: string, rate: int, questions: int, correct: int}>,
     *     summary: array{rate: int, questions: int, correct: int, active_days: int}
     * }
     */
    private function learningProgress(int $userId, string $range): array
    {
        $lastDate = Carbon::today();
        $firstDate = match ($range) {
            '7d' => $lastDate->copy()->subDays(6),
            'all' => $this->firstCompletedSessionDate($userId) ?? $lastDate->copy()->subDays(29),
            default => $lastDate->copy()->subDays(29),
        };

        if ($firstDate->diffInDays($lastDate) > 364) {
            $firstDate = $lastDate->copy()->subDays(364);
        }

        /** @var Collection<string, array{questions: int, correct: int}> $stats */
        $stats = QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->whereBetween('updated_at', [$firstDate->copy()->startOfDay(), $lastDate->copy()->endOfDay()])
            ->get(['updated_at', 'answered_count', 'correct_count'])
            ->groupBy(fn (QuestionSession $session): string => $session->updated_at?->toDateString() ?? '')
            ->map(fn (Collection $sessions): array => [
                'questions' => (int) $sessions->sum('answered_count'),
                'correct' => (int) $sessions->sum('correct_count'),
            ]);

        $days = [];

        for ($date = $firstDate->copy(); $date->lte($lastDate); $date->addDay()) {
            $key = $date->toDateString();
            $day = $stats->get($key, ['questions' => 0, 'correct' => 0]);
            $questions = (int) $day['questions'];
            $correct = (int) $day['correct'];
            $rate = $questions > 0 ? (int) round($correct / $questions * 100) : 0;
            $days[] = [
                'date' => $key,
                'label' => $date->isToday() ? 'Hôm nay' : $date->format('d/m'),
                'display_date' => $date->format('d/m/Y'),
                'rate' => $rate,
                'questions' => $questions,
                'correct' => $correct,
            ];
        }

        $totalQuestions = (int) $stats->sum('questions');
        $totalCorrect = (int) $stats->sum('correct');

        return [
            'days' => $days,
            'summary' => [
                'rate' => $totalQuestions > 0 ? (int) round($totalCorrect / $totalQuestions * 100) : 0,
                'questions' => $totalQuestions,
                'correct' => $totalCorrect,
                'active_days' => $stats->filter(
                    static fn (array $day): bool => $day['questions'] > 0,
                )->count(),
            ],
        ];
    }

    private function firstCompletedSessionDate(int $userId): ?Carbon
    {
        $date = QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->min('updated_at');

        return $date !== null ? Carbon::parse($date)->startOfDay() : null;
    }

    private function streakDays(int $userId): int
    {
        $days = QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->whereNotNull('updated_at')
            ->orderByDesc('updated_at')
            ->pluck('updated_at')
            ->map(fn (mixed $date): string => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $expected = Carbon::today();
        $latest = Carbon::parse($days->first())->startOfDay();

        if (! $latest->isSameDay($expected)) {
            if (! $latest->isSameDay($expected->copy()->subDay())) {
                return 0;
            }

            $expected = $latest;
        }

        $streak = 0;

        foreach ($days as $day) {
            if ($day !== $expected->toDateString()) {
                break;
            }

            $streak++;
            $expected->subDay();
        }

        return $streak;
    }
}
