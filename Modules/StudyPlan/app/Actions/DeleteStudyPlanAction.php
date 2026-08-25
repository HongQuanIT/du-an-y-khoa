<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
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

        $actor = $plan->user()->first();
        Auditor::record(
            AuditAction::LearningPlanDeleted,
            $actor instanceof User ? $actor : null,
            $plan,
            before: ['status' => $plan->status->value, 'strategy' => $plan->strategy->value],
        );

        return (bool) $plan->delete();
    }
}
