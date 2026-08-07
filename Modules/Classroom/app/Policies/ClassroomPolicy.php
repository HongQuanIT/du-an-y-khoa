<?php

declare(strict_types=1);

namespace Modules\Classroom\Policies;

use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Role;
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
        if ($classroom->visibility === ClassroomVisibility::Public) {
            return true;
        }

        if ($classroom->isActiveMember($user)) {
            return true;
        }

        return $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
    }

    public function create(User $user): bool
    {
        return $user->hasEntitlement(Entitlement::ClassroomHost->value);
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $classroom->isHostOrCohost($user)
            || $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
    }

    public function join(User $user, Classroom $classroom): bool
    {
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
        return $this->update($user, $classroom)
            && $user->hasEntitlement(Entitlement::ClassroomHost->value);
    }
}
