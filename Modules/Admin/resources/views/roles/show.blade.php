@php
    $enum = \App\Support\Enums\Role::tryFrom($role->name);
@endphp

<x-layouts.admin title="Role {{ $role->name }}">
    <x-admin.page-header :title="$enum?->label() ?? $role->name" :description="'Slug: '.$role->name.' · Portal: '.($enum?->portal()->label() ?? '—')">
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
                    $isFocus = $portal === $focusPortal;
                @endphp
                <details class="rounded-xl border border-outline-variant bg-surface" @if ($isFocus) open @endif>
                    <summary class="cursor-pointer list-none px-5 py-4 font-headline-sm text-headline-sm text-on-surface [&::-webkit-details-marker]:hidden">
                        <div class="flex items-center justify-between gap-3">
                            <span>
                                {{ $portal->label() }}
                                <span class="ms-2 font-label-sm text-label-sm text-on-surface-variant">
                                    {{ $group['permissions']->count() }} quyền
                                    @if ($isFocus)
                                        · nhóm chính
                                    @endif
                                </span>
                            </span>
                            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ $isFocus ? 'Mở' : 'Thu gọn' }}</span>
                        </div>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant font-normal">{{ $portal->description() }}</p>
                    </summary>
                    <div class="border-t border-outline-variant px-5 py-4">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($group['permissions'] as $permission)
                                <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-surface-container-low {{ $canEdit ? 'cursor-pointer' : 'cursor-default opacity-80' }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                        @checked(in_array($permission->id, $assignedIds, true))
                                        @disabled(! $canEdit)
                                        class="rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="font-label-md text-label-md text-on-surface">{{ $permission->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>
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
