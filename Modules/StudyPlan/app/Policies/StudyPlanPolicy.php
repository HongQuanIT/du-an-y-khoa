<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Policies;

use App\Models\User;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Plans are private to their owner (srs/modules/04 §9, §13 — IDOR).
 */
final class StudyPlanPolicy
{
    public function view(User $user, StudyPlan $plan): bool
    {
        return ($user->can('study_plan.view') || $user->can('study_plan.view_any'))
            && $this->owns($user, $plan);
    }

    private function owns(User $user, StudyPlan $plan): bool
    {
        return $user->getKey() === $plan->user_id;
    }
}
