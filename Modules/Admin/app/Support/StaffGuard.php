<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Role;

/**
 * Guards sensitive admin mutations against self-harm and privilege escalation.
 */
final class StaffGuard
{
    public static function primaryRole(User $user): ?Role
    {
        return Role::tryFromName($user->primaryRoleName());
    }

    public static function assertCanManage(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            abort(403, 'Không thể thao tác trên chính tài khoản của bạn.');
        }

        $actorRole = self::primaryRole($actor);
        $targetRole = self::primaryRole($target);

        if ($actorRole === null) {
            abort(403);
        }

        if ($actorRole === Role::SuperAdmin) {
            return;
        }

        if ($targetRole === Role::SuperAdmin) {
            abort(403, 'Không thể quản lý Super Admin.');
        }

        if ($actorRole === Role::Admin && $targetRole === Role::Admin) {
            abort(403, 'Admin không thể quản lý Admin khác.');
        }

        if ($actorRole->rank() <= ($targetRole?->rank() ?? 0)) {
            abort(403, 'Không đủ quyền quản lý người dùng này.');
        }
    }

    public static function assertCanAssignRole(User $actor, Role $role): void
    {
        $allowed = Role::assignableBy($actor);

        if (! in_array($role, $allowed, true)) {
            abort(403, 'Không được gán vai trò này.');
        }
    }
}
