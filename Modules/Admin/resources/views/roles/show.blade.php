@php
    $enum = \App\Support\Enums\Role::tryFrom($role->name);
    $roleLabel = \Modules\Admin\Support\PermissionCatalog::roleLabel($role);
    $rolePortal = $enum?->portal()
        ?? \App\Support\Enums\PortalGroup::tryFrom((string) $role->portal);
    $focusedGroup = collect($permissionGroups)->first(
        fn (array $group): bool => $group['portal'] === $focusPortal,
    );
    $learnerToolGroup = $role->name === \App\Support\Enums\Role::SuperAdmin->value
        ? collect($permissionGroups[\App\Support\Enums\PortalGroup::Learner->value]['modules'] ?? [])
            ->first(fn (array $module): bool => $module['key'] === 'learning')
        : null;
    $matrixModules = collect($focusedGroup['modules'] ?? [])
        ->when($learnerToolGroup !== null, fn ($modules) => $modules->push([
            ...$learnerToolGroup,
            'resources' => collect($learnerToolGroup['resources'])
                ->filter(fn (array $resource): bool => $resource['key'] === 'learning_tool')
                ->values()
                ->all(),
        ]));
    $matrixPermissions = $matrixModules
        ->flatMap(fn (array $module) => collect($module['resources'])->flatMap(
            fn (array $resource) => $resource['permissions']->map(fn ($permission) => [
                'id' => (int) $permission->id,
                'name' => $permission->name,
                'action' => \Modules\Admin\Support\PermissionCatalog::actionLabel($permission->name),
                'module' => $module['key'],
                'moduleLabel' => $module['label'],
                'resource' => $resource['key'],
                'resourceLabel' => $resource['label'],
            ]),
        ))
        ->values()
        ->all();
@endphp

<x-layouts.admin title="Vai trò: {{ $roleLabel }}">
    <x-admin.page-header :title="'Vai trò: '.$roleLabel" :description="'Cổng truy cập: '.($rolePortal?->label() ?? '—').' · Mã định danh: '.$role->name">
        <x-slot:actions>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.roles.index'))
                <a href="{{ route('admin.roles.index') }}"
                    class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @unless ($canEdit)
        <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant">
            @if ($role->name === 'super_admin')
                Super Admin luôn có toàn bộ quyền — không chỉnh sửa qua UI.
            @elseif ($role->name === 'admin')
                Chỉ Super Admin được chỉnh sửa quyền của vai trò Quản trị viên.
            @else
                Chỉ Super Admin hoặc Quản trị viên được lưu thay đổi ma trận quyền.
            @endif
        </p>
    @endunless

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.roles.permissions'))
        <form method="post" action="{{ route('admin.roles.permissions', $role) }}"
            x-data="{
                selectedPermissions: @js(array_map('intval', $assignedIds)),
                permissions: @js($matrixPermissions),
                collapsedModules: {},
                collapsedModulesStorageKey: @js('admin.roles.'.$role->getKey().'.'.$focusPortal->value.'.matrix-collapsed.v1'),
                init() {
                    try {
                        const saved = JSON.parse(localStorage.getItem(this.collapsedModulesStorageKey) || '{}');
                        this.collapsedModules = saved && typeof saved === 'object' && !Array.isArray(saved) ? saved : {};
                    } catch (error) {
                        this.collapsedModules = {};
                    }
                },
                isModuleCollapsed(module) {
                    return this.collapsedModules[module] === true;
                },
                toggleModule(module) {
                    this.collapsedModules[module] = !this.isModuleCollapsed(module);
                    try {
                        localStorage.setItem(this.collapsedModulesStorageKey, JSON.stringify(this.collapsedModules));
                    } catch (error) {
                        // Thu gọn vẫn hoạt động trong phiên hiện tại khi localStorage không khả dụng.
                    }
                },
                toggleAll() {
                    const ids = this.permissions.map(permission => permission.id);
                    this.selectedPermissions = this.isAllSelected()
                        ? this.selectedPermissions.filter(id => !ids.includes(id))
                        : Array.from(new Set([...this.selectedPermissions, ...ids]));
                },
                isAllSelected() {
                    return this.permissions.length > 0
                        && this.permissions.every(permission => this.selectedPermissions.includes(permission.id));
                },
            }"
            x-init="init()">
            @csrf
            @method('PUT')

            <section aria-labelledby="permission-matrix-heading" class="rounded-2xl border border-outline-variant bg-surface p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="permission-matrix-heading" class="font-headline-sm font-semibold text-on-surface">Ma trận quyền</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">
                            @if ($role->name === \App\Support\Enums\Role::SuperAdmin->value)
                                Quyền cổng {{ $focusPortal->label() }} và công cụ học tập của Học viên.
                            @else
                                Quyền thuộc cổng {{ $focusPortal->label() }} của vai trò này.
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary"
                            x-text="selectedPermissions.filter(id => permissions.some(permission => permission.id === id)).length + '/' + permissions.length + ' đã chọn'"></span>
                        @if ($canEdit)
                            <button type="button" @click="toggleAll()"
                                class="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-semibold text-on-surface hover:bg-surface-container-low">
                                <span x-text="isAllSelected() ? 'Bỏ chọn tất cả' : 'Chọn tất cả'"></span>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="grid gap-2 sm:gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <template x-for="(permission, index) in permissions" :key="permission.id">
                        <div class="contents">
                            <div x-show="index === 0 || permissions[index - 1].module !== permission.module"
                                class="col-span-full border-b border-outline-variant pb-2"
                                :class="{ 'mt-4': index > 0 }">
                                <button type="button" @click="toggleModule(permission.module)"
                                    class="-m-2 flex w-full items-center gap-2 rounded-lg p-2 text-start hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    :aria-expanded="(!isModuleCollapsed(permission.module)).toString()">
                                    <span class="material-symbols-outlined text-[20px] text-on-surface-variant"
                                        x-text="isModuleCollapsed(permission.module) ? 'chevron_right' : 'expand_more'"
                                        aria-hidden="true"></span>
                                    <span role="heading" aria-level="3" class="font-headline-sm font-semibold text-on-surface" x-text="permission.moduleLabel"></span>
                                </button>
                            </div>
                            <div x-show="(index === 0 || permissions[index - 1].resource !== permission.resource) && !isModuleCollapsed(permission.module)" x-cloak
                                class="col-span-full mt-2 font-label-md font-semibold text-primary" role="heading" aria-level="4" x-text="permission.resourceLabel"></div>
                            <label x-show="!isModuleCollapsed(permission.module)" x-cloak @class([
                                'flex items-start gap-2.5 rounded-xl border border-outline-variant/70 p-2.5 sm:p-3 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5',
                                'cursor-pointer hover:bg-surface-container-low' => $canEdit,
                                'cursor-default opacity-80' => ! $canEdit,
                            ])>
                                <input type="checkbox" name="permissions[]" :value="permission.id"
                                    x-model.number="selectedPermissions" @disabled(! $canEdit)
                                    class="mt-0.5 size-4 rounded border-outline text-primary focus:ring-primary">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-on-surface leading-snug" x-text="permission.action"></span>
                                    <code class="mt-0.5 block text-xs text-on-surface-variant break-words font-mono" x-text="permission.name"></code>
                                </span>
                            </label>
                        </div>
                    </template>
                    <template x-if="permissions.length === 0">
                        <p class="col-span-full py-4 text-center text-sm text-on-surface-variant">Cổng truy cập này chưa có quyền nào trong hệ thống.</p>
                    </template>
                </div>
                @error('permissions')<p class="mt-2 text-xs text-error">{{ $message }}</p>@enderror
            </section>

            @if ($canEdit)
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-on-primary hover:bg-primary/90 transition-colors"
                        onclick="return confirm('Lưu thay đổi quyền cho role này?')">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        Lưu Permission
                    </button>
                </div>
            @endif
        </form>
    @endif
</x-layouts.admin>
