<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission as PermissionEnum;
use App\Support\Enums\Role as RoleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\SyncRolePermissionsAction;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorizePermission(PermissionEnum::RoleManage);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount('users')
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        return view('admin::roles.index', [
            'roles' => $roles,
        ]);
    }

    public function show(Role $role): View
    {
        $this->authorizePermission(PermissionEnum::RoleManage);

        $role->load('permissions');

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0] ?? 'other');

        $assigned = $role->permissions->pluck('id')->all();

        return view('admin::roles.show', [
            'role' => $role,
            'permissionGroups' => $permissions,
            'assignedIds' => $assigned,
            'systemRole' => RoleEnum::tryFrom($role->name) !== null,
            'canEdit' => auth()->user()?->hasRole(RoleEnum::SuperAdmin->value)
                && $role->name !== RoleEnum::SuperAdmin->value,
        ]);
    }

    public function syncPermissions(Request $request, Role $role, SyncRolePermissionsAction $action): RedirectResponse
    {
        $this->authorizePermission(PermissionEnum::RoleManage);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $action->handle($this->actor(), $role, $data['permissions'] ?? []);

        return back()->with('status', 'Đã cập nhật quyền cho vai trò.');
    }

    public function permissionsCatalog(): View
    {
        $this->authorizePermission(PermissionEnum::RoleManage);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0] ?? 'other');

        return view('admin::permissions.index', [
            'permissionGroups' => $permissions,
        ]);
    }

    private function authorizePermission(PermissionEnum $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
