<x-layouts.admin title="Danh mục quyền">
    <x-admin.page-header title="Danh mục quyền"
        description="Quyền được phân theo cổng truy cập và chỉ gán thông qua ma trận vai trò.">
        <x-slot:actions>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.roles.index'))
                <a href="{{ route('admin.roles.index') }}"
                    class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Vai trò</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="space-y-6" x-data="{
        tab: '{{ \App\Support\Enums\PortalGroup::Learner->value }}',
        query: '',
        collapsedModules: {},
        storageKey: 'admin.permissions.catalog.collapsed-modules.v1',
        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
                this.collapsedModules = saved && typeof saved === 'object' && !Array.isArray(saved) ? saved : {};
            } catch (error) {
                this.collapsedModules = {};
            }
        },
        moduleKey(portal, module) {
            return portal + '.' + module;
        },
        isModuleCollapsed(portal, module) {
            return this.collapsedModules[this.moduleKey(portal, module)] === true;
        },
        toggleModule(portal, module) {
            const key = this.moduleKey(portal, module);
            this.collapsedModules[key] = !this.isModuleCollapsed(portal, module);
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(this.collapsedModules));
            } catch (error) {
                // Thu gọn vẫn hoạt động trong phiên hiện tại khi localStorage không khả dụng.
            }
        },
    }" x-init="init()">
        <nav class="flex flex-wrap gap-2 border-b border-outline-variant pb-3" aria-label="Chọn cổng truy cập">
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
        </nav>

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
            <section x-show="tab === '{{ $portal->value }}'" x-cloak class="space-y-5" aria-labelledby="portal-{{ $portal->value }}-heading">
                <div class="rounded-2xl border border-outline-variant bg-surface p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="portal-{{ $portal->value }}-heading" class="font-headline-sm text-headline-sm text-on-surface">{{ $portal->label() }}</h2>
                            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $portal->description() }}</p>
                        </div>
                        <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ $group['permissions']->count() }} quyền</span>
                    </div>
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

                <section aria-labelledby="permission-list-{{ $portal->value }}-heading">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 id="permission-list-{{ $portal->value }}-heading" class="font-headline-sm font-semibold text-on-surface">Danh sách quyền</h3>
                        <span class="text-xs text-on-surface-variant">Bấm vào nhóm để thu gọn</span>
                    </div>
                    <div class="grid gap-2 sm:gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @forelse ($group['modules'] as $module)
                        <div class="col-span-full border-b border-outline-variant pb-2">
                            <button type="button" @click="toggleModule('{{ $portal->value }}', '{{ $module['key'] }}')"
                                class="-m-2 flex w-full items-center justify-between gap-3 rounded-lg p-2 text-start hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                :aria-expanded="(!isModuleCollapsed('{{ $portal->value }}', '{{ $module['key'] }}')).toString()">
                                <span class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-on-surface-variant"
                                        x-text="isModuleCollapsed('{{ $portal->value }}', '{{ $module['key'] }}') ? 'chevron_right' : 'expand_more'"
                                        aria-hidden="true"></span>
                                    <span role="heading" aria-level="4" class="font-headline-sm font-semibold text-on-surface">{{ $module['label'] }}</span>
                                </span>
                                <span class="rounded-full bg-surface-container-low px-2.5 py-1 text-xs text-on-surface-variant">{{ $module['count'] }} quyền</span>
                            </button>
                        </div>
                        @foreach ($module['resources'] as $resource)
                            <div x-show="!isModuleCollapsed('{{ $portal->value }}', '{{ $module['key'] }}')" x-cloak
                                class="col-span-full mt-1 font-label-md font-semibold text-primary" role="heading" aria-level="5">
                                {{ $resource['label'] }}
                            </div>
                            @foreach ($resource['permissions'] as $permission)
                                <article x-show="!isModuleCollapsed('{{ $portal->value }}', '{{ $module['key'] }}') && (query === '' || @js(mb_strtolower($permission->name.' '.\Modules\Admin\Support\PermissionCatalog::actionLabel($permission->name))).includes(query.toLowerCase()))" x-cloak
                                    class="rounded-xl border border-outline-variant/70 bg-surface p-2.5 sm:p-3">
                                    <h5 class="text-sm font-medium text-on-surface leading-snug">{{ \Modules\Admin\Support\PermissionCatalog::actionLabel($permission->name) }}</h5>
                                    <code class="mt-0.5 block text-xs text-on-surface-variant break-words font-mono">{{ $permission->name }}</code>
                                </article>
                            @endforeach
                        @endforeach
                    @empty
                        <p class="col-span-full py-4 text-center font-body-sm text-on-surface-variant">Chưa có quyền trong nhóm này.</p>
                    @endforelse
                    </div>
                </section>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
