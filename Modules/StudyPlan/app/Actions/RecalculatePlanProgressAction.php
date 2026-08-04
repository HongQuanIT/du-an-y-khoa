<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Use case: refresh the denormalised `progress_cache` a plan renders from.
 *
 * Called after anything that changes tasks so overview/detail/dashboard never
 * have to aggregate on read.
 */
final class RecalculatePlanProgressAction
{
    use AsAction;

    public function handle(StudyPlan $plan): StudyPlan
    {
        $tasks = $plan->tasks()->get();

        $target = (int) $tasks->sum('target');
        $done = (int) $tasks->sum('done');
        $tasksDone = $tasks->where('status', TaskStatus::Done)->count();

        $plan->forceFill([
            'progress_cache' => [
                'percent' => $target > 0 ? (int) min(100, round($done / $target * 100)) : 0,
                'questions_done' => $done,
                'questions_target' => $target,
                'tasks_done' => $tasksDone,
                'tasks_total' => $tasks->count(),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);

        if ($this->isFinished($plan, $tasks->where('status', TaskStatus::Pending)->count())) {
            $plan->status = PlanStatus::Completed;
        }

        $plan->save();

        return $plan;
    }

    private function isFinished(StudyPlan $plan, int $pending): bool
    {
        return $plan->status === PlanStatus::Active
            && $pending === 0
            && $plan->exam_target_date->lessThanOrEqualTo(Carbon::today());
    }
}
