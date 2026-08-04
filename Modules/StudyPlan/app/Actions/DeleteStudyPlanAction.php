<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Use case: remove a plan and its tasks (sessions already run stay in Q-Bank).
 */
final class DeleteStudyPlanAction
{
    use AsAction;

    public function handle(StudyPlan $plan): bool
    {
        event(StudyPlanActivity::deleted($plan));

        return (bool) $plan->delete();
    }
}
