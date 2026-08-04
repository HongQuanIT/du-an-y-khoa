<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Analytics\Actions\ListWeakTopicsAction;
use Modules\Analytics\Actions\ResolveContinueLearningAction;
use Modules\StudyPlan\Actions\ListTodayTasksAction;
use Modules\StudyPlan\Repositories\StudyPlanRepository;

/**
 * Student dashboard — the landing page after login (srs/modules/03).
 *
 * Study-plan tasks, weak topics and "continue learning" are live; the streak,
 * chart and recommendation widgets still show placeholders until Analytics
 * ships its daily rollups.
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

        return view('analytics::dashboard', [
            'plan' => $plan,
            'planTasks' => $plan !== null ? $this->todayTasks->handle($plan, 3) : collect(),
            'weakTopics' => $this->weakTopics->handle($user),
            'continueCard' => $this->continueLearning->handle($user),
        ]);
    }
}
