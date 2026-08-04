<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Use case: drop a task the learner does not want to catch up on.
 *
 * Skipped tasks stay visible in the timeline.
 */
final class SkipPlanTaskAction
{
    use AsAction;

    public function __construct(private readonly RecalculatePlanProgressAction $recalculateProgress) {}

    public function handle(StudyPlanTask $task): StudyPlanTask
    {
        if ($task->isDone()) {
            return $task;
        }

        $task->forceFill(['status' => TaskStatus::Skipped])->save();

        $this->recalculateProgress->handle($task->plan);

        return $task;
    }
}
