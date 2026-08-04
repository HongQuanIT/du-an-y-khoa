<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\StudyPlan\Actions\MarkOverdueTasksSkippedAction;
use Modules\StudyPlan\Actions\RecalculatePlanProgressAction;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Support\PlanTimeline;

/**
 * Plan detail: week-by-week timeline plus progress side panel
 * (srs/modules/04 §2).
 */
final class StudyPlanDetailController extends Controller
{
    public function __construct(
        private readonly PlanTimeline $timeline,
        private readonly MarkOverdueTasksSkippedAction $markOverdue,
        private readonly RecalculatePlanProgressAction $recalculateProgress,
    ) {}

    public function __invoke(StudyPlan $plan): View
    {
        $this->authorize('view', $plan);

        $plan = $this->markOverdue->handle($plan);
        $plan = $this->recalculateProgress->handle($plan);
        $weeks = $this->timeline->weeks($plan);

        event(StudyPlanActivity::viewed($plan));

        return view('studyplan::detail', [
            'plan' => $plan,
            'weeks' => $weeks,
            'openWeek' => $this->timeline->currentWeekIndex($weeks),
            'topicProgress' => $this->timeline->topicProgress($plan),
        ]);
    }
}
