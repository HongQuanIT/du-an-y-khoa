<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Analytics\Actions\EnsureDailyLearningStatsAction;
use Modules\Analytics\Actions\GetDashboardStatsAction;
use Modules\Analytics\Actions\GetLearningProgressAction;
use Modules\Analytics\Actions\ListDashboardRecommendationsAction;
use Modules\Analytics\Actions\ListRecentLearningActivitiesAction;
use Modules\Analytics\Actions\ListWeakTopicsAction;
use Modules\Analytics\Actions\ResolveContinueLearningAction;
use Modules\Analytics\Actions\ResolveDashboardSubscriptionAction;
use Modules\Analytics\Support\DashboardCache;
use Modules\StudyPlan\Actions\ListTodayTasksAction;
use Modules\StudyPlan\Repositories\StudyPlanRepository;

/**
 * Student dashboard — the landing page after login (srs/modules/03).
 *
 * Composes read-optimized learner rollups and next-action widgets.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly StudyPlanRepository $plans,
        private readonly ListTodayTasksAction $todayTasks,
        private readonly ListWeakTopicsAction $weakTopics,
        private readonly ResolveContinueLearningAction $continueLearning,
        private readonly EnsureDailyLearningStatsAction $ensureDailyStats,
        private readonly GetDashboardStatsAction $stats,
        private readonly GetLearningProgressAction $progress,
        private readonly ListDashboardRecommendationsAction $recommendations,
        private readonly ListRecentLearningActivitiesAction $recentActivities,
        private readonly ResolveDashboardSubscriptionAction $subscription,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $plan = $this->plans->currentFor($user);
        $this->ensureDailyStats->handle($user);
        $range = in_array($request->query('range'), ['7d', '30d', 'all'], true)
            ? (string) $request->query('range')
            : '30d';
        $analytics = Cache::remember(
            DashboardCache::key((int) $user->getKey(), $range),
            DashboardCache::TTL_SECONDS,
            fn (): array => [
                'stats' => $this->stats->handle($user),
                'progress' => $this->progress->handle($user, $range),
                'recommendations' => $this->recommendations->handle($user),
                'recentActivities' => $this->recentActivities->handle($user),
            ],
        );
        $points = $analytics['progress']['points'];
        $totalQuestions = (int) array_sum(array_column($points, 'questions'));
        $totalCorrect = (int) array_sum(array_column($points, 'correct'));
        $stats = $analytics['stats'];

        return view('analytics::dashboard', [
            'plan' => $plan,
            'planTasks' => $plan !== null ? $this->todayTasks->handle($plan, 3) : collect(),
            'weakTopics' => $this->weakTopics->handle($user),
            'continueCard' => $this->continueLearning->handle($user),
            ...$analytics,
            'progressRange' => $analytics['progress']['range'],
            'chartBars' => array_map(static fn (array $point): array => [
                'date' => $point['date'],
                'label' => $point['label'],
                'display_date' => date('d/m/Y', strtotime($point['date'])),
                'questions' => $point['questions'],
                'correct' => $point['correct'],
                'rate' => $point['accuracy'],
            ], $points),
            'progressSummary' => [
                'rate' => $totalQuestions > 0 ? (int) round($totalCorrect / $totalQuestions * 100) : 0,
                'questions' => $totalQuestions,
                'correct' => $totalCorrect,
                'active_days' => count(array_filter(
                    $points,
                    static fn (array $point): bool => $point['questions'] > 0,
                )),
            ],
            // Compatibility contract for existing dashboard consumers while the
            // UI reads the normalized `stats` payload.
            'headlineStats' => [
                'questions' => [
                    'value' => number_format($stats['questions_answered'], 0, ',', '.'),
                    'delta' => sprintf('%+d tuần này', $stats['questions_this_week']),
                ],
                'accuracy' => [
                    'value' => $stats['correct_rate'].'%',
                    'delta' => $stats['correct_rate_delta'] !== null
                        ? sprintf('%+d%%', $stats['correct_rate_delta'])
                        : null,
                ],
                'weekly_time' => [
                    'value' => $this->formatMinutes($stats['study_minutes_this_week']),
                    'delta' => null,
                ],
                'streak' => ['value' => (string) $stats['streak_days'], 'delta' => null],
            ],
            'dashboardSubscription' => $this->subscription->handle($user),
        ]);
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' phút';
        }

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }
}
