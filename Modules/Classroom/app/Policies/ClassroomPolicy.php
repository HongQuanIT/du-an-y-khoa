<?php

declare(strict_types=1);

namespace Modules\Classroom\Policies;

use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Role;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;

final class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value])) {
            return true;
        }

        if ($classroom->isHostOrCohost($user) || $classroom->isActiveMember($user)) {
            return true;
        }

        if (! $classroom->status->isVisibleToLearners()) {
            return false;
        }

        if ($classroom->visibility === ClassroomVisibility::Public) {
            return true;
        }

        return false;
    }

    /** Only instructors create classrooms (via `/teach`). Learner hosting is disabled. */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Instructor->value);
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $classroom->isHostOrCohost($user)
            || $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
    }

    public function join(User $user, Classroom $classroom): bool
    {
        if (! $classroom->status->isVisibleToLearners()) {
            return false;
        }

        $member = $classroom->memberFor($user);

        if ($member?->status === MemberStatus::Banned) {
            return false;
        }

        if ($classroom->visibility === ClassroomVisibility::InviteOnly) {
            return $member?->status === MemberStatus::Invited;
        }

        return true;
    }

    public function manageLive(User $user, Classroom $classroom): bool
    {
        if (! $this->update($user, $classroom)) {
            return false;
        }

        return $user->hasRole(Role::Instructor->value)
            || $user->hasEntitlement(Entitlement::ClassroomHost->value);
    }

    public function startLive(User $user, Classroom $classroom): bool
    {
        return $this->manageLive($user, $classroom)
            && $classroom->status->isVisibleToLearners();
    }
}
