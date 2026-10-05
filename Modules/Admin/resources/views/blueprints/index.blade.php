<x-layouts.admin title="Ma trận đề thi">
    <x-admin.page-header title="Ma trận đề thi" description="Ma trận → Phần → Chủ đề lâm sàng. Dùng khi kỳ thi gắn ma trận để tạo phiên đề thi. Ngân hàng câu hỏi không đọc ma trận.">
        <x-slot:actions>
            @if ($canCreate)
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.create'))
                    <a href="{{ route(\App\Support\Auth\PortalRoute::content('blueprints.create')) }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-label-md font-semibold text-on-primary">
                        <span class="material-symbols-outlined text-[18px]">add</span>
                        Tạo ma trận
                    </a>
                @endif
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'blueprints'])

    <x-admin.flash />

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface"
         x-data="{ confirming: null }">
        <table class="min-w-full text-sm">
            <thead class="bg-surface-container-low text-left font-label-sm text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Tên</th>
                    <th class="px-4 py-3">Mã</th>
                    <th class="px-4 py-3 text-center">Phần</th>
                    <th class="px-4 py-3 text-center">Chủ đề lâm sàng</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($blueprints as $blueprint)
                    <tr class="border-t border-outline-variant hover:bg-surface-container-lowest/60">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-on-surface">{{ $blueprint->name }}</p>
                            @if ($blueprint->description)
                                <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ $blueprint->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $blueprint->code ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">{{ $blueprint->sections_count }}</td>
                        <td class="px-4 py-3 text-center font-semibold">{{ $coreTopicCounts[$blueprint->id] ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $blueprint->status->value === 'active' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                                {{ $blueprint->status->value === 'active' ? 'Đang dùng' : 'Ngừng dùng' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                @if ($canUpdate && \Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.edit'))
                                    <a href="{{ route(\App\Support\Auth\PortalRoute::content('blueprints.edit'), $blueprint) }}"
                                        class="inline-flex h-8 items-center rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface hover:bg-surface-container-low">
                                        Sửa
                                    </a>
                                @endif
                                @if ($canDelete && \Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.destroy'))
                                    <button type="button"
                                        @click="confirming = {
                                            name: @js($blueprint->name),
                                            destroy_url: @js(route(\App\Support\Auth\PortalRoute::content('blueprints.destroy'), $blueprint)),
                                        }"
                                        class="inline-flex h-8 items-center rounded-lg px-2.5 text-xs font-medium text-error hover:bg-error/10">
                                        Xoá
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-on-surface-variant">
                            Chưa có ma trận đề thi.
                            @if ($canCreate)
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.create'))
                                    <a href="{{ route(\App\Support\Auth\PortalRoute::content('blueprints.create')) }}" class="ml-1 font-semibold text-primary hover:underline">Tạo ma trận đầu tiên</a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <template x-teleport="body">
            <div x-show="confirming" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-on-surface/40" @click="confirming = null"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl">
                    <h3 class="text-base font-semibold text-on-surface">Xoá ma trận?</h3>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        «<span x-text="confirming?.name"></span>» và toàn bộ phần / chủ đề lâm sàng bên trong sẽ bị gỡ.
                        Kỳ thi đã gắn ma trận này sẽ bỏ liên kết ma trận.
                    </p>
                    <form x-show="confirming" :action="confirming?.destroy_url" method="post" class="mt-5 flex justify-end gap-2">
                        @csrf @method('DELETE')
                        <button type="button" @click="confirming = null"
                            class="h-10 rounded-lg px-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
                        <button type="submit" class="h-10 rounded-lg bg-error px-4 text-sm font-semibold text-white hover:opacity-90">Xoá</button>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
