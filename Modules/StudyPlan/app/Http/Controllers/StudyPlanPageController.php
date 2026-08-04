<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\StudyPlan\Actions\ListTodayTasksAction;
use Modules\StudyPlan\Actions\MarkOverdueTasksSkippedAction;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Repositories\StudyPlanRepository;

/**
 * Study-plan overview: every plan the learner owns, with today's work for
 * the active one (srs/modules/04 §2).
 */
final class StudyPlanPageController extends Controller
{
    public function __construct(
        private readonly StudyPlanRepository $plans,
        private readonly ListTodayTasksAction $todayTasks,
        private readonly MarkOverdueTasksSkippedAction $markOverdue,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $allPlans = $this->plans->paginateFor($user);
        $plan = $this->plans->currentFor($user);

        if ($plan !== null) {
            $plan = $this->markOverdue->handle($plan);
            event(StudyPlanActivity::viewed($plan));
        }

        return view('studyplan::index', [
            'plans' => $allPlans,
            'plan' => $plan,
            'todayTasks' => $plan !== null && $plan->isActive()
                ? $this->todayTasks->handle($plan)
                : collect(),
        ]);
    }
}
