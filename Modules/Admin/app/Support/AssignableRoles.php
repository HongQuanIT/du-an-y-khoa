<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Auth\PortalAccess;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role as SystemRole;
use App\Support\Rbac\PermissionRegistry;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Resolves assignable Spatie roles, including safe custom roles.
 */
final class AssignableRoles
{
    /** @return Collection<int, Role> */
    public static function for(User $actor): Collection
    {
        if (! PortalAccess::allows($actor, PortalGroup::Admin)
            || ! $actor->can('user.role_assign')) {
            return collect();
        }

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        if ($actor->hasRole(SystemRole::SuperAdmin->value)) {
            return $roles;
        }

        $systemAllowed = $actor->hasRole(SystemRole::Admin->value)
            ? collect(SystemRole::assignableBy($actor))
                ->map(fn (SystemRole $role): string => $role->value)
                ->all()
            : [
                SystemRole::Student->value,
                SystemRole::Instructor->value,
                SystemRole::Partner->value,
                SystemRole::ContentEditor->value,
            ];

        return $roles
            ->filter(function (Role $role) use ($systemAllowed): bool {
                $systemRole = SystemRole::tryFrom($role->name);
                if ($systemRole !== null) {
                    return in_array($systemRole->value, $systemAllowed, true);
                }

                return ! self::containsCriticalPermission($role);
            })
            ->values();
    }

    public static function containsCriticalPermission(Role $role): bool
    {
        $registry = app(PermissionRegistry::class);

        return $role->permissions->contains(function ($permission) use ($registry): bool {
            return $registry->find($permission->name)?->riskLevel === 'critical';
        });
    }
}
