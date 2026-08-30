<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission as PermissionEnum;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role as RoleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\SyncRolePermissionsAction;
use Modules\Admin\Support\PermissionCatalog;
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
            'roleGroups' => PermissionCatalog::rolesGroupedByPortal($roles),
        ]);
    }

    public function show(Role $role): View
    {
        $this->authorizePermission(PermissionEnum::RoleManage);

        $role->load('permissions');

        $systemRole = RoleEnum::tryFrom($role->name);
        $focusPortal = $systemRole?->portal() ?? PortalGroup::Admin;

        return view('admin::roles.show', [
            'role' => $role,
            'permissionGroups' => PermissionCatalog::groupedByPortal(),
            'assignedIds' => $role->permissions->pluck('id')->all(),
            'systemRole' => $systemRole !== null,
            'focusPortal' => $focusPortal,
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

        $roleModels = Role::query()
            ->where('guard_name', 'web')
            ->get()
            ->keyBy('name');

        $rolesByPortal = [];
        foreach (PortalGroup::cases() as $portal) {
            $rolesByPortal[$portal->value] = collect(RoleEnum::rolesIn($portal))
                ->map(function (RoleEnum $enum) use ($roleModels) {
                    $model = $roleModels->get($enum->value);

                    return $model === null ? null : [
                        'id' => $model->id,
                        'name' => $enum->value,
                        'label' => $enum->label(),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        return view('admin::permissions.index', [
            'permissionGroups' => PermissionCatalog::groupedByPortal(),
            'roleLabelsByPermission' => PermissionCatalog::roleLabelsByPermission(),
            'rolesByPortal' => $rolesByPortal,
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
