@php
    $enum = \App\Support\Enums\Role::tryFrom($role->name);
    $roleLabel = \Modules\Admin\Support\PermissionCatalog::roleLabel($role);
    $rolePortal = $enum?->portal()
        ?? \App\Support\Enums\PortalGroup::tryFrom((string) $role->portal);
@endphp

<x-layouts.admin title="Vai trò {{ $roleLabel }}">
    <x-admin.page-header :title="$roleLabel" :description="'Mã định danh: '.$role->name.' · Cổng truy cập: '.($rolePortal?->label() ?? '—')">
        <x-slot:actions>
            <a href="{{ route('admin.roles.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @unless ($canEdit)
        <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant">
            @if ($role->name === 'super_admin')
                Super Admin luôn có toàn bộ quyền — không chỉnh sửa qua UI.
            @else
                Chỉ Super Admin được lưu thay đổi ma trận quyền.
            @endif
        </p>
    @endunless

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
                        <div class="p-5">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @forelse ($group['permissions'] as $permission)
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-surface-container-low {{ $canEdit ? 'cursor-pointer' : 'cursor-default opacity-80' }}">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                            @checked(in_array($permission->id, $assignedIds, true))
                                            @disabled(! $canEdit)
                                            class="rounded border-outline-variant text-primary focus:ring-primary">
                                        <span class="font-label-md text-label-md text-on-surface">{{ $permission->name }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-on-surface-variant">Chưa có permission nào cho cổng truy cập này.</p>
                                @endforelse
                            </div>
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
</x-layouts.admin>
