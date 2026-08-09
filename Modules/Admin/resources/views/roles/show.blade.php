@php
    $enum = \App\Support\Enums\Role::tryFrom($role->name);
@endphp

<x-layouts.admin title="Role {{ $role->name }}">
    <x-admin.page-header :title="$enum?->label() ?? $role->name" :description="'Slug: '.$role->name">
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

        <div class="space-y-6">
            @foreach ($permissionGroups as $group => $permissions)
                <section class="rounded-xl border border-outline-variant bg-surface p-5">
                    <h3 class="mb-3 font-headline-sm text-headline-sm text-on-surface capitalize">{{ $group }}</h3>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($permissions as $permission)
                            <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-surface-container-low {{ $canEdit ? 'cursor-pointer' : 'cursor-default opacity-80' }}">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                    @checked(in_array($permission->id, $assignedIds, true))
                                    @disabled(! $canEdit)
                                    class="rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="font-label-md text-label-md text-on-surface">{{ $permission->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>
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
