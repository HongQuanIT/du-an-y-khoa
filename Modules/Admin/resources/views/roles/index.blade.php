<x-layouts.admin title="Vai trò & quyền">
    <x-admin.page-header title="Vai trò & quyền" description="Quản lý vai trò theo từng cổng truy cập và mở ma trận Permission tương ứng.">
        <x-slot:actions>
            @if ($canCreate && \Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.roles.create'))
                <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary transition hover:opacity-90">Tạo vai trò</a>
            @endif
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.permissions.index'))
                <a href="{{ route('admin.permissions.index') }}" class="inline-flex items-center rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface transition hover:bg-surface-container-low">Danh mục quyền</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @php
        $totalRoles = collect($roleGroups)->sum(fn (array $group): int => count($group['roles']));
        $totalPortals = collect($roleGroups)->filter(fn (array $group): bool => count($group['roles']) > 0)->count();
    @endphp

    <div x-data="{ query: '' }" class="space-y-6">
        <section aria-label="Tổng quan vai trò" class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-outline-variant bg-surface px-5 py-4"><p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Tổng số vai trò</p><p class="mt-1 text-2xl font-bold text-on-surface">{{ $totalRoles }}</p></div>
            <div class="rounded-2xl border border-outline-variant bg-surface px-5 py-4"><p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Cổng đang sử dụng</p><p class="mt-1 text-2xl font-bold text-on-surface">{{ $totalPortals }}/{{ count($roleGroups) }}</p></div>
        </section>

        <label class="relative block max-w-xl"><span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant" aria-hidden="true">search</span><span class="sr-only">Tìm vai trò</span><input type="search" x-model.debounce.200ms="query" placeholder="Tìm theo tên hoặc mã định danh vai trò..." class="h-11 w-full rounded-xl border border-outline-variant bg-surface pl-10 pr-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"></label>

        @foreach ($roleGroups as $group)
            @php $portal = $group['portal']; @endphp
            <section aria-labelledby="portal-{{ $portal->value }}-heading" class="overflow-hidden rounded-2xl border border-outline-variant bg-surface">
                <header class="border-b border-outline-variant bg-primary/5 px-5 py-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="portal-{{ $portal->value }}-heading" class="font-headline-sm text-headline-sm text-on-surface">{{ $portal->label() }}</h2><p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $portal->description() }}</p></div><span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ count($group['roles']) }} vai trò</span></div></header>
                <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm"><caption class="sr-only">Danh sách vai trò thuộc cổng {{ $portal->label() }}</caption><thead class="border-b border-outline-variant bg-surface-container-low text-xs font-bold uppercase tracking-wide text-on-surface-variant"><tr><th scope="col" class="px-5 py-3">Vai trò</th><th scope="col" class="px-5 py-3">Người dùng</th><th scope="col" class="px-5 py-3">Permission</th><th scope="col" class="px-5 py-3 text-right">Thao tác</th></tr></thead><tbody class="divide-y divide-outline-variant/60">
                    @forelse ($group['roles'] as $role)
                        @php $roleLabel = \Modules\Admin\Support\PermissionCatalog::roleLabel($role); @endphp
                        <tr x-show="query === '' || @js(mb_strtolower($roleLabel.' '.$role->name)).includes(query.toLowerCase())" x-cloak class="transition-colors hover:bg-surface-container-low"><th scope="row" class="px-5 py-4 font-normal"><span class="block font-label-md font-semibold text-on-surface">{{ $roleLabel }}</span><code class="mt-0.5 block text-xs text-on-surface-variant">{{ $role->name }}</code></th><td class="whitespace-nowrap px-5 py-4 text-on-surface-variant">{{ $role->users_count }}</td><td class="px-5 py-4"><span class="rounded-full bg-surface-container-low px-2.5 py-1 text-xs font-semibold text-on-surface-variant">{{ $role->permissions_count }}</span></td><td class="px-5 py-4 text-right">@if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.roles.show'))<a href="{{ route('admin.roles.show', $role) }}" aria-label="Xem chi tiết vai trò {{ $roleLabel }}" class="inline-flex rounded-lg px-2 py-1 font-label-md text-primary transition hover:bg-primary/10 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">Chi tiết</a>@endif</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-on-surface-variant">Chưa có vai trò trong cổng truy cập này.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
