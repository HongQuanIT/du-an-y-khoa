<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
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

        $actor = $task->plan->user()->first();
        Auditor::record(
            AuditAction::LearningTaskSkipped,
            $actor instanceof User ? $actor : null,
            $task,
            ['status' => TaskStatus::Pending->value],
            ['status' => TaskStatus::Skipped->value],
            metadata: ['study_plan_id' => $task->study_plan_id],
        );

        return $task;
    }
}
