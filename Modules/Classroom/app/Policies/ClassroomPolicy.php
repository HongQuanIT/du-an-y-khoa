<?php

declare(strict_types=1);

namespace Modules\Classroom\Policies;

use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Permission;
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
        if ($user->can(Permission::ClassroomOversee->value)) {
            return true;
        }

        if ($classroom->isHostOrCohost($user) || $classroom->isActiveMember($user)) {
            return true;
        }

        if (! $classroom->isVisibleToLearners()) {
            return false;
        }

        if ($classroom->visibility === ClassroomVisibility::Public) {
            return true;
        }

        return false;
    }

    /** Instructors create their own; staff create on behalf of an instructor host. */
    public function create(User $user): bool
    {
        return $user->can(Permission::ClassroomCreate->value)
            || $user->can(Permission::ClassroomCreateOnBehalf->value);
    }

    public function update(User $user, Classroom $classroom): bool
    {
        if ($user->can(Permission::ClassroomOversee->value)) {
            return true;
        }

        return $classroom->isHostOrCohost($user)
            && (
                $user->can(Permission::ClassroomManage->value)
                || $user->hasEntitlement(Entitlement::ClassroomHost->value)
            );
    }

    public function join(User $user, Classroom $classroom): bool
    {
        if (! $classroom->isVisibleToLearners()) {
            return false;
        }

        $member = $classroom->memberFor($user);

        if ($member?->status === MemberStatus::Banned) {
            return false;
        }

        if ($classroom->visibility === ClassroomVisibility::InviteOnly
            && $member?->status !== MemberStatus::Invited) {
            return false;
        }

        return $user->can(Permission::ClassroomJoin->value)
            || $user->can(Permission::ClassroomOversee->value);
    }

    public function manageLive(User $user, Classroom $classroom): bool
    {
        if (! $this->update($user, $classroom)) {
            return false;
        }

        return $user->can(Permission::ClassroomOversee->value)
            || $user->can(Permission::LiveStart->value)
            || $user->can(Permission::ClassroomManage->value)
            || $user->hasEntitlement(Entitlement::ClassroomHost->value);
    }

    public function startLive(User $user, Classroom $classroom): bool
    {
        return $this->manageLive($user, $classroom)
            && $classroom->isVisibleToLearners();
    }
}
