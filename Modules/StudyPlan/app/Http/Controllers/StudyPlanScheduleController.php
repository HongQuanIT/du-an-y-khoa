<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\StudyPlan\Actions\MarkOverdueTasksSkippedAction;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Support\PlanCalendar;

/**
 * Month calendar of plan tasks with a per-day panel (srs/modules/04 §3
 * PlanCalendar). One month at a time keeps the query bounded (§14).
 */
final class StudyPlanScheduleController extends Controller
{
    public function __construct(
        private readonly PlanCalendar $calendar,
        private readonly MarkOverdueTasksSkippedAction $markOverdue,
    ) {}

    public function __invoke(Request $request, StudyPlan $plan): View
    {
        $this->authorize('view', $plan);

        $plan = $this->markOverdue->handle($plan);
        $month = $this->parseDate($request->query('month'), Carbon::today())->startOfMonth();
        $selected = $this->parseDate($request->query('date'), $this->defaultSelectedDay($month));

        return view('studyplan::schedule', [
            'plan' => $plan,
            'month' => $month,
            'selectedDate' => $selected,
            'cells' => $this->calendar->month($plan, $month, $selected),
            'dayTasks' => $this->calendar->tasksOn($plan, $selected),
        ]);
    }

    private function defaultSelectedDay(Carbon $month): Carbon
    {
        return $month->isSameMonth(Carbon::today()) ? Carbon::today() : $month->copy();
    }

    private function parseDate(mixed $value, Carbon $fallback): Carbon
    {
        if (! is_string($value) || $value === '') {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }
}
