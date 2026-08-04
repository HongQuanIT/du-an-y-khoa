<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Policies;

use App\Models\User;
use App\Support\Enums\Role;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Plans are private to their owner (srs/modules/04 §9, §13 — IDOR).
 * Deleting a plan is an admin-only operation.
 */
final class StudyPlanPolicy
{
    public function view(User $user, StudyPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function update(User $user, StudyPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function delete(User $user, StudyPlan $plan): bool
    {
        return $user->hasAnyRole([
            Role::Admin->value,
            Role::SuperAdmin->value,
        ]);
    }

    private function owns(User $user, StudyPlan $plan): bool
    {
        return $user->getKey() === $plan->user_id;
    }
}
