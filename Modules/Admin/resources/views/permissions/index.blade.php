<x-layouts.admin title="Permissions">
    <x-admin.page-header title="Danh mục permission"
        description="Nhóm theo 4 portal sản phẩm. Permission gắn qua ma trận role — không gán trực tiếp trên user.">
        <x-slot:actions>
            <a href="{{ route('admin.roles.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Vai trò</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="space-y-6" x-data="{ tab: '{{ \App\Support\Enums\PortalGroup::Learner->value }}' }">
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
                                <a href="{{ route('admin.roles.show', $roleMeta['id']) }}"
                                    class="rounded-lg border border-outline-variant px-2.5 py-1 font-label-sm text-label-sm text-primary hover:bg-surface-container-low">
                                    Sửa ma trận: {{ $roleMeta['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($group['permissions'] as $permission)
                        @php
                            $holders = $roleLabelsByPermission[$permission->name] ?? [];
                            $primaryLabels = collect($portalRoles)->pluck('label')->all();
                            $alsoBy = array_values(array_filter(
                                $holders,
                                fn (string $label) => ! in_array($label, $primaryLabels, true),
                            ));
                        @endphp
                        <li class="rounded-lg border border-outline-variant/60 bg-surface px-3 py-2.5">
                            <div class="font-label-md text-label-md text-on-surface">{{ $permission->name }}</div>
                            @if (count($alsoBy) > 0)
                                <div class="mt-1 font-label-sm text-label-sm text-on-surface-variant">
                                    Cũng dùng bởi: {{ implode(', ', $alsoBy) }}
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="font-body-sm text-body-sm text-on-surface-variant sm:col-span-2 lg:col-span-3">
                            Chưa có permission trong nhóm này.
                        </li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
