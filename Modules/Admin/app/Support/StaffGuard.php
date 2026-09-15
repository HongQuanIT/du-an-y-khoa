<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Auth\PortalAccess;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role;
use Spatie\Permission\Models\Role as RoleModel;

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

        abort_unless(PortalAccess::allows($actor, PortalGroup::Admin), 403);

        $actorRole = self::primaryRole($actor);
        $targetRole = self::primaryRole($target);

        if ($actorRole === Role::SuperAdmin) {
            return;
        }

        $targetRoleModel = $target->roles()->with('permissions:id,name')->first();
        if ($targetRoleModel !== null
            && Role::tryFrom($targetRoleModel->name) === null
            && AssignableRoles::containsCriticalPermission($targetRoleModel)) {
            abort(403, 'Admin không thể quản lý tài khoản giữ vai trò có quyền đặc biệt.');
        }

        if ($targetRole === Role::SuperAdmin) {
            abort(403, 'Không thể quản lý Super Admin.');
        }

        if ($targetRole === Role::Admin) {
            abort(403, 'Admin không thể quản lý Admin khác.');
        }

        if ($actorRole === null) {
            return;
        }

        if ($actorRole->rank() <= ($targetRole?->rank() ?? 0)) {
            abort(403, 'Không đủ quyền quản lý người dùng này.');
        }
    }

    public static function assertCanAssignRole(User $actor, RoleModel $role): void
    {
        $allowed = AssignableRoles::for($actor)->modelKeys();

        if (! in_array($role->getKey(), $allowed, true)) {
            abort(403, 'Không được gán vai trò này.');
        }
    }
}
