<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final class SyncRolePermissionsAction
{
    use AsAction;

    /**
     * @param  list<string|int>  $permissionIds
     */
    public function handle(User $actor, RoleModel $role, array $permissionIds): RoleModel
    {
        if (! $actor->hasAnyRole([Role::SuperAdmin->value, Role::Admin->value])) {
            abort(403, 'Chỉ Super Admin hoặc Admin được cập nhật ma trận quyền.');
        }

        if ($role->name === Role::SuperAdmin->value) {
            abort(403, 'Không chỉnh sửa trực tiếp quyền Super Admin (luôn full).');
        }

        if ($actor->hasRole(Role::Admin->value) && ! $actor->hasRole(Role::SuperAdmin->value) && $role->name === Role::Admin->value) {
            abort(403, 'Admin không thể chỉnh sửa quyền của chính role Admin.');
        }

        $permissionModels = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $permissionIds)
            ->get();

        $portal = Role::tryFrom($role->name)?->portal()
            ?? PortalGroup::tryFrom((string) $role->portal)
            ?? PortalGroup::Admin;

        if ($permissionModels->contains(
            fn (Permission $permission): bool => ! PermissionCatalog::belongsToPortal($permission, $portal),
        )) {
            abort(422, 'Vai trò chỉ được nhận permission thuộc đúng portal.');
        }

        $permissions = $permissionModels->pluck('name')->all();

        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($role, $permissions): void {
            $role->syncPermissions($permissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $after = $role->fresh()->permissions()->pluck('name')->sort()->values()->all();

        Auditor::record(
            'admin.role.permission_change',
            $actor,
            $role,
            ['permissions' => $before],
            ['permissions' => $after],
        );

        return $role->fresh(['permissions']);
    }
}
