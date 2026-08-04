<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Past pending tasks are treated as skipped automatically — learners should
 * not have to press a skip button when the day has already passed.
 */
final class MarkOverdueTasksSkippedAction
{
    use AsAction;

    public function __construct(private readonly RecalculatePlanProgressAction $recalculateProgress) {}

    public function handle(StudyPlan $plan): StudyPlan
    {
        $updated = $plan->tasks()
            ->where('status', TaskStatus::Pending->value)
            ->whereDate('date', '<', Carbon::today()->toDateString())
            ->update(['status' => TaskStatus::Skipped->value]);

        if ($updated > 0) {
            $this->recalculateProgress->handle($plan->refresh());
        }

        return $plan->refresh();
    }
}
