<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Use case: move a task to another day.
 */
final class RescheduleTaskAction
{
    use AsAction;

    public function __construct(private readonly RecalculatePlanProgressAction $recalculateProgress) {}

    public function handle(StudyPlanTask $task, Carbon $date): StudyPlanTask
    {
        $from = $task->date->toDateString();

        $task->forceFill(['date' => $date->toDateString()])->save();

        $this->recalculateProgress->handle($task->plan);

        event(StudyPlanActivity::rescheduled($task, $from, $date->toDateString()));

        return $task;
    }
}
