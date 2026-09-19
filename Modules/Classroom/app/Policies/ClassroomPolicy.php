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
        return $user->can('classroom.view')
            || $user->can('classroom_oversight.view');
    }

    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->can('classroom_oversight.view')) {
            return true;
        }

        if (! $user->canAny(['classroom.view', 'classroom.join', 'classroom.leave'])) {
            return false;
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
        if ($user->can('classroom_oversight.view')) {
            return true;
        }

        return $classroom->isHostOrCohost($user)
            && ($user->canAny([
                'classroom_settings.update',
                Permission::ClassroomManage->value,
            ]) || $user->hasEntitlement(Entitlement::ClassroomHost->value));
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
            || $user->can('classroom_oversight.view');
    }

    public function manageLive(User $user, Classroom $classroom): bool
    {
        if ($user->canAny(['classroom_oversight.schedule', 'classroom_oversight.view'])) {
            return true;
        }

        return $classroom->isHostOrCohost($user)
            && $user->canAny([
                'classroom_session.schedule',
                'classroom_session.start',
                'classroom_session.end',
                Permission::ClassroomManage->value,
            ]);
    }

    public function scheduleLive(User $user, Classroom $classroom): bool
    {
        if ($user->canAny(['classroom_oversight.schedule', 'classroom_oversight.view'])) {
            return true;
        }

        return $classroom->isHostOrCohost($user) && $user->can('classroom_session.schedule');
    }

    public function startLive(User $user, Classroom $classroom): bool
    {
        if ($user->canAny(['classroom_oversight.schedule', 'classroom_oversight.view'])) {
            return true;
        }

        return $classroom->isHostOrCohost($user) && $user->can('classroom_session.start');
    }

    public function endLive(User $user, Classroom $classroom): bool
    {
        if ($user->canAny(['classroom_oversight.schedule', 'classroom_oversight.view'])) {
            return true;
        }

        return $classroom->isHostOrCohost($user) && $user->can('classroom_session.end');
    }
}
