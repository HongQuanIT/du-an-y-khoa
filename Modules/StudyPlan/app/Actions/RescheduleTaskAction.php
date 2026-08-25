<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
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

        $actor = $task->plan->user()->first();
        Auditor::record(
            AuditAction::LearningTaskRescheduled,
            $actor instanceof User ? $actor : null,
            $task,
            ['date' => $from],
            ['date' => $date->toDateString()],
            metadata: ['study_plan_id' => $task->study_plan_id],
        );

        return $task;
    }
}
