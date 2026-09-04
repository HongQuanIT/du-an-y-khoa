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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\CreateRoleAction;
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
            'canCreate' => $this->actor()->hasRole(RoleEnum::SuperAdmin->value),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(PermissionEnum::RoleManage);
        abort_unless($this->actor()->hasRole(RoleEnum::SuperAdmin->value), 403);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();
        $permissionGroups = PermissionCatalog::groupedByPortal();
        $permissionIdsByPortal = collect($permissionGroups)->map(
            fn (array $group) => $group['permissions']->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        );
        $roleGroups = PermissionCatalog::rolesGroupedByPortal($roles);
        $roleTemplatesByPortal = collect($roleGroups)->mapWithKeys(function (array $group, string $portal) use ($permissionIdsByPortal): array {
            $allowedPermissionIds = $permissionIdsByPortal->get($portal, []);

            return [$portal => collect($group['roles'])->map(function (Role $role) use ($allowedPermissionIds): array {
                return [
                    'id' => (int) $role->getKey(),
                    'name' => $role->name,
                    'label' => PermissionCatalog::roleLabel($role),
                    'permissions' => $role->permissions->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->intersect($allowedPermissionIds)
                        ->values()
                        ->all(),
                ];
            })->values()->all()];
        })->all();

        return view('admin::roles.create', [
            'permissionGroups' => $permissionGroups,
            'portals' => PortalGroup::cases(),
            'roleTemplatesByPortal' => $roleTemplatesByPortal,
        ]);
    }

    public function store(Request $request, CreateRoleAction $action): RedirectResponse
    {
        $this->authorizePermission(PermissionEnum::RoleManage);
        abort_unless($this->actor()->hasRole(RoleEnum::SuperAdmin->value), 403);

        // Accept human-friendly or pasted names and normalize them to the
        // stable slug format used by Spatie roles.
        $displayName = trim((string) $request->input('display_name', $request->input('name')));
        $normalizedName = Str::of((string) $request->input('name'))
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9._-]+/', '_')
            ->trim('._-')
            ->toString();
        $request->merge(['name' => $normalizedName, 'display_name' => $displayName]);

        $data = $request->validate([
            'portal' => ['required', Rule::enum(PortalGroup::class)],
            'name' => [
                'required', 'string', 'min:2', 'max:80', 'regex:/^[a-z][a-z0-9._-]*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:120'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
            'template_role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ], [
            'name.regex' => 'Tên role chỉ dùng chữ thường, số, dấu chấm, gạch ngang hoặc gạch dưới.',
        ]);

        $portal = PortalGroup::from($data['portal']);
        $allowedPermissionIds = PermissionCatalog::groupedByPortal()[$portal->value]['permissions']
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $selectedPermissionIds = array_map('intval', $data['permissions'] ?? []);

        if (array_diff($selectedPermissionIds, $allowedPermissionIds) !== []) {
            return back()
                ->withErrors(['permissions' => 'Chỉ được chọn permission thuộc portal đã chọn.'])
                ->withInput();
        }

        if (! empty($data['template_role_id'])) {
            $template = Role::query()->findOrFail($data['template_role_id']);
            $templatePortal = RoleEnum::tryFrom($template->name)?->portal()
                ?? PortalGroup::tryFrom((string) $template->portal)
                ?? PortalGroup::Admin;

            if ($templatePortal !== $portal) {
                return back()
                    ->withErrors(['template_role_id' => 'Role mẫu không thuộc portal đã chọn.'])
                    ->withInput();
            }
        }

        $role = $action->handle($this->actor(), $data['name'], $data['display_name'], $portal, $selectedPermissionIds);

        return redirect()->route('admin.roles.show', $role)->with('status', 'Đã tạo role mới.');
    }

    public function show(Role $role): View
    {
        $this->authorizePermission(PermissionEnum::RoleManage);

        $role->load('permissions');

        $systemRole = RoleEnum::tryFrom($role->name);
        $focusPortal = $systemRole?->portal()
            ?? PortalGroup::tryFrom((string) $role->portal)
            ?? PortalGroup::Admin;

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

        foreach ($roleModels->values() as $role) {
            if (RoleEnum::tryFrom($role->name) !== null) {
                continue;
            }

            $portal = PortalGroup::tryFrom((string) $role->portal) ?? PortalGroup::Admin;
            $rolesByPortal[$portal->value][] = [
                'id' => $role->id,
                'name' => $role->name,
                'label' => PermissionCatalog::roleLabel($role),
            ];
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
