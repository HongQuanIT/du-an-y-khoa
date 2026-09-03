<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Support\Auditor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final class CreateRoleAction
{
    use AsAction;

    /** @param list<int|string> $permissionIds */
    public function handle(User $actor, string $name, array $permissionIds): RoleModel
    {
        abort_unless($actor->hasRole(Role::SuperAdmin->value), 403, 'Chỉ Super Admin được tạo role.');

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $permissionIds)
            ->get();

        $role = DB::transaction(function () use ($name, $permissions): RoleModel {
            $role = RoleModel::query()->create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Auditor::record('admin.role.created', $actor, $role, null, [
            'name' => $role->name,
            'permissions' => $permissions->pluck('name')->sort()->values()->all(),
        ]);

        return $role->fresh(['permissions']);
    }
}
