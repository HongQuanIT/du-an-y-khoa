<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Support\Enums\Permission as PermissionEnum;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role as RoleEnum;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Groups Spatie permissions by product portal for admin catalog / role matrix UI.
 */
final class PermissionCatalog
{
    /**
     * @return array<string, array{
     *     portal: PortalGroup,
     *     permissions: Collection<int, Permission>,
     * }>
     */
    public static function groupedByPortal(): array
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $grouped = [];

        foreach (PortalGroup::cases() as $portal) {
            $grouped[$portal->value] = [
                'portal' => $portal,
                'permissions' => collect(),
            ];
        }

        foreach ($permissions as $permission) {
            $enum = PermissionEnum::tryFrom($permission->name);
            $portal = $enum?->portal() ?? PortalGroup::Admin;
            $grouped[$portal->value]['permissions']->push($permission);
        }

        return $grouped;
    }

    /**
     * Role labels (excluding the permission's primary portal roles when only one)
     * that currently hold each permission — for “cũng dùng bởi” badges.
     *
     * @return array<string, list<string>> permission name => role labels
     */
    public static function roleLabelsByPermission(): array
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->get();

        $map = [];

        foreach ($roles as $role) {
            $enum = RoleEnum::tryFrom($role->name);
            $label = $enum?->label() ?? $role->name;

            foreach ($role->permissions as $permission) {
                $map[$permission->name][] = $label;
            }
        }

        foreach ($map as $name => $labels) {
            $map[$name] = array_values(array_unique($labels));
        }

        return $map;
    }

    /**
     * Roles keyed by portal for index UI.
     *
     * @param  Collection<int, Role>  $roles
     * @return array<string, array{portal: PortalGroup, roles: list<Role>}>
     */
    public static function rolesGroupedByPortal(Collection $roles): array
    {
        $byName = $roles->keyBy('name');
        $grouped = [];

        foreach (PortalGroup::cases() as $portal) {
            $portalRoles = [];

            foreach (RoleEnum::rolesIn($portal) as $enum) {
                $model = $byName->get($enum->value);
                if ($model !== null) {
                    $portalRoles[] = $model;
                    $byName->forget($enum->value);
                }
            }

            $grouped[$portal->value] = [
                'portal' => $portal,
                'roles' => $portalRoles,
            ];
        }

        // Custom roles persist their selected portal. Legacy records default to Admin.
        foreach ($byName->values() as $customRole) {
            $portal = PortalGroup::tryFrom((string) $customRole->portal) ?? PortalGroup::Admin;
            $grouped[$portal->value]['roles'][] = $customRole;
        }

        return $grouped;
    }
}
