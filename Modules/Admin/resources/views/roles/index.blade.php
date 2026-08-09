<x-layouts.admin title="Vai trò">
    <x-admin.page-header title="Vai trò & quyền"
        description="Danh sách role hệ thống. Super Admin cập nhật ma trận permission.">
        <x-slot:actions>
            <a href="{{ route('admin.permissions.index') }}"
                class="rounded-lg border border-outline-variant px-3 py-2 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">Danh mục permission</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Người dùng</th>
                    <th class="px-4 py-3">Permissions</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    @php $enum = \App\Support\Enums\Role::tryFrom($role->name); @endphp
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface">{{ $enum?->label() ?? $role->name }}</div>
                            <div class="font-label-sm text-label-sm text-on-surface-variant">{{ $role->name }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $role->users_count }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $role->permissions_count }}</td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.roles.show', $role) }}" class="font-label-md text-primary hover:underline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
