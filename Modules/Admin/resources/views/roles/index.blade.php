<x-layouts.admin title="Vai trò">
    <x-admin.page-header title="Vai trò & quyền"
        description="Vai trò được nhóm theo 4 cổng truy cập. Quản trị viên cấp cao cập nhật ma trận quyền.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.roles.create') }}"
                    class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90">Tạo vai trò</a>
            @endif
            <a href="{{ route('admin.permissions.index') }}"
                class="rounded-lg border border-outline-variant px-3 py-2 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">Danh mục permission</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="space-y-6">
        @foreach ($roleGroups as $group)
            @php $portal = $group['portal']; @endphp
            <section class="rounded-xl border border-outline-variant bg-surface overflow-hidden">
                <div class="border-b border-outline-variant bg-surface-container-low px-4 py-3">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">{{ $portal->label() }}</h3>
                    <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $portal->description() }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left font-body-sm text-body-sm">
                        <thead class="border-b border-outline-variant font-label-md text-label-md text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3">Vai trò</th>
                                <th class="px-4 py-3">Người dùng</th>
                                <th class="px-4 py-3">Permissions</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($group['roles'] as $role)
                                @php $roleLabel = \Modules\Admin\Support\PermissionCatalog::roleLabel($role); @endphp
                                <tr class="border-b border-outline-variant/60 last:border-0">
                                    <td class="px-4 py-3">
                                        <div class="font-label-md text-label-md text-on-surface">{{ $roleLabel }}</div>
                                        <div class="font-label-sm text-label-sm text-on-surface-variant">{{ $role->name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-on-surface-variant">{{ $role->users_count }}</td>
                                    <td class="px-4 py-3 text-on-surface-variant">{{ $role->permissions_count }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <a href="{{ route('admin.roles.show', $role) }}" class="font-label-md text-primary hover:underline">Chi tiết</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-on-surface-variant">Chưa có vai trò trong cổng truy cập này.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
