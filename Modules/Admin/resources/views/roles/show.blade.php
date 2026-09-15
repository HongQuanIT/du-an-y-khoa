@php
    $enum = \App\Support\Enums\Role::tryFrom($role->name);
    $roleLabel = \Modules\Admin\Support\PermissionCatalog::roleLabel($role);
    $rolePortal = $enum?->portal()
        ?? \App\Support\Enums\PortalGroup::tryFrom((string) $role->portal);
@endphp

<x-layouts.admin title="Vai trò {{ $roleLabel }}">
    <x-admin.page-header :title="$roleLabel" :description="'Mã định danh: '.$role->name.' · Cổng truy cập: '.($rolePortal?->label() ?? '—')">
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
<form method="post" action="{{ route('admin.roles.permissions', $role) }}">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            @foreach ($permissionGroups as $group)
                @php
                    $portal = $group['portal'];
                @endphp
                @if ($portal === $focusPortal)
                    <section class="rounded-xl border border-outline-variant bg-surface overflow-hidden">
                        <div class="border-b border-outline-variant bg-surface-container-low px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-headline-sm text-headline-sm text-on-surface">
                                    {{ $portal->label() }}
                                    <span class="ms-2 font-label-sm text-label-sm text-on-surface-variant">
                                        {{ $group['permissions']->count() }} quyền
                                    </span>
                                </h3>
                            </div>
                            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant font-normal">{{ $portal->description() }}</p>
                        </div>
                        <div class="space-y-4 p-5">
                            @forelse ($group['modules'] as $module)
                                <details open class="overflow-hidden rounded-xl border border-outline-variant/70">
                                    <summary class="flex cursor-pointer list-none items-center justify-between bg-surface-container-low px-4 py-3">
                                        <span class="font-label-lg font-semibold text-on-surface">{{ $module['label'] }}</span>
                                        <span class="font-label-sm text-on-surface-variant">{{ $module['count'] }} quyền</span>
                                    </summary>
                                    <div class="space-y-5 p-5">
                                        @foreach ($module['resources'] as $resource)
                                            <div class="rounded-xl border border-outline-variant/60 bg-surface-container-lowest p-4">
                                                <h4 class="mb-3 flex items-center justify-between font-label-md font-semibold text-primary">
                                                    <span>{{ $resource['label'] }}</span>
                                                    <span class="text-xs font-normal text-on-surface-variant">{{ $resource['permissions']->count() }} quyền</span>
                                                </h4>
                                                <div class="space-y-1">
                                                    @foreach ($resource['permissions'] as $permission)
                                                        <label class="flex items-start gap-2.5 rounded-lg px-2.5 py-2 hover:bg-surface-container-low transition-colors {{ $canEdit ? 'cursor-pointer' : 'cursor-default opacity-80' }}">
                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                                                @checked(in_array($permission->id, $assignedIds, true)) @disabled(! $canEdit)
                                                                class="mt-0.5 size-4 rounded border-outline-variant text-primary focus:ring-primary">
                                                            <span class="min-w-0">
                                                                <span class="block font-label-md text-on-surface">{{ \Modules\Admin\Support\PermissionCatalog::actionLabel($permission->name) }}</span>
                                                                <code class="block text-xs text-on-surface-variant">{{ $permission->name }}</code>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @empty
                                <p class="text-sm text-on-surface-variant">Chưa có permission nào cho cổng truy cập này.</p>
                            @endforelse
                        </div>
                    </section>
                @endif
            @endforeach
        </div>

        @if ($canEdit)
            <div class="mt-6">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary"
                    onclick="return confirm('Lưu thay đổi quyền cho role này?')">Lưu permissions</button>
            </div>
        @endif
    </form>
@endif
</x-layouts.admin>
