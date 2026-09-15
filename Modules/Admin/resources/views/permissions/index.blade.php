<x-layouts.admin title="Permissions">
    <x-admin.page-header title="Danh mục permission"
        description="Nhóm theo 4 portal sản phẩm. Permission gắn qua ma trận role — không gán trực tiếp trên user.">
        <x-slot:actions>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.roles.index'))
<a href="{{ route('admin.roles.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Vai trò</a>
@endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="space-y-6" x-data="{ tab: '{{ \App\Support\Enums\PortalGroup::Learner->value }}', query: '' }">
        <div class="flex flex-wrap gap-2 border-b border-outline-variant pb-3">
            @foreach ($permissionGroups as $group)
                @php $portal = $group['portal']; @endphp
                <button type="button"
                    @click="tab = '{{ $portal->value }}'"
                    :class="tab === '{{ $portal->value }}'
                        ? 'bg-primary text-on-primary'
                        : 'bg-surface-container-low text-on-surface hover:bg-surface-container'"
                    class="rounded-lg px-3 py-2 font-label-md text-label-md transition">
                    {{ $portal->label() }}
                    <span class="opacity-80">({{ $group['permissions']->count() }})</span>
                </button>
            @endforeach
        </div>

        <label class="relative block max-w-xl">
            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
            <input type="search" x-model.debounce.200ms="query" placeholder="Tìm theo tên quyền hoặc mã permission..."
                class="h-11 w-full rounded-xl border border-outline-variant bg-surface pl-10 pr-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
        </label>

        @foreach ($permissionGroups as $group)
            @php
                $portal = $group['portal'];
                $portalRoles = $rolesByPortal[$portal->value] ?? [];
            @endphp
            <section x-show="tab === '{{ $portal->value }}'" x-cloak class="space-y-4">
                <div class="rounded-xl border border-outline-variant bg-surface p-5">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">{{ $portal->label() }}</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $portal->description() }}</p>
                    <p class="mt-2 font-label-sm text-label-sm text-on-surface-variant">
                        Đăng nhập: <code class="rounded bg-surface-container-low px-1.5 py-0.5">{{ $portal->loginPath() }}</code>
                    </p>
                    @if (count($portalRoles) > 0)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($portalRoles as $roleMeta)
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.roles.show'))
<a href="{{ route('admin.roles.show', $roleMeta['id']) }}"
                                    class="rounded-lg border border-outline-variant px-2.5 py-1 font-label-sm text-label-sm text-primary hover:bg-surface-container-low">
                                    Sửa ma trận: {{ $roleMeta['label'] }}
                                </a>
@endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="space-y-4">
                    @forelse ($group['modules'] as $module)
                        <details open class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-surface-container-low px-5 py-3.5">
                                <span class="font-headline-sm text-on-surface">{{ $module['label'] }}</span>
                                <span class="rounded-full bg-surface px-2.5 py-1 font-label-sm text-on-surface-variant">{{ $module['count'] }} quyền</span>
                            </summary>
                            <div class="space-y-5 p-5">
                                @foreach ($module['resources'] as $resource)
                                    <section class="rounded-xl border border-outline-variant/60 bg-surface-container-lowest p-4">
                                        <h4 class="mb-3 flex items-center justify-between font-label-md font-semibold text-primary">
                                            <span>{{ $resource['label'] }}</span>
                                            <span class="text-xs font-normal text-on-surface-variant">{{ $resource['permissions']->count() }} quyền</span>
                                        </h4>
                                        <ul class="space-y-2">
                                            @foreach ($resource['permissions'] as $permission)
                                                <li x-show="query === '' || @js(mb_strtolower($permission->name.' '.\Modules\Admin\Support\PermissionCatalog::actionLabel($permission->name))).includes(query.toLowerCase())"
                                                    class="rounded-lg border border-outline-variant/60 bg-surface px-3.5 py-2.5">
                                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                                        <span class="font-label-md font-medium text-on-surface">{{ \Modules\Admin\Support\PermissionCatalog::actionLabel($permission->name) }}</span>
                                                        <code class="text-xs text-on-surface-variant">{{ $permission->name }}</code>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </section>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <p class="font-body-sm text-on-surface-variant">Chưa có permission trong nhóm này.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
