<x-layouts.admin title="Permissions">
    <x-admin.page-header title="Danh mục permission"
        description="Catalog ability dạng resource.action (SRS RBAC).">
        <x-slot:actions>
            <a href="{{ route('admin.roles.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Vai trò</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="space-y-6">
        @foreach ($permissionGroups as $group => $permissions)
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="mb-3 font-headline-sm text-headline-sm text-on-surface capitalize">{{ $group }}</h3>
                <ul class="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissions as $permission)
                        <li class="font-label-md text-label-md text-on-surface-variant">{{ $permission->name }}</li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
