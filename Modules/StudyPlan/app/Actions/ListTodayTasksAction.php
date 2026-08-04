<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Use case: today's work for a plan, with anything missed pulled in front.
 *
 * Backs the overview panel and the dashboard widget (srs/modules/03).
 */
final class ListTodayTasksAction
{
    use AsAction;

    /**
     * @return Collection<int, StudyPlanTask>
     */
    public function handle(StudyPlan $plan, int $limit = 5): Collection
    {
        $today = $plan->todayTasks()->get();

        $missed = $plan->tasks()
            ->whereDate('date', '<', Carbon::today())
            ->where('status', TaskStatus::Pending)
            ->orderByDesc('date')
            ->limit(max(0, $limit - $today->count()))
            ->get();

        return $today->concat($missed)->take($limit)->values();
    }
}
