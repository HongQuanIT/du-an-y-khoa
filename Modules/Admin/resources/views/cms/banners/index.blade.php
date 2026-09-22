<x-layouts.admin title="CMS — Banner">
    <div x-data="adminBannerFilter()" class="space-y-6">
    @include('admin::cms._sub-nav')

    <x-admin.page-header title="Banner / Thông báo"
        description="Quản lý banner hiển thị trên landing và dashboard học viên (lịch, đối tượng, bật/tắt).">
        <x-slot:actions>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.banners.create'))
<a href="{{ route('admin.cms.banners.create') }}"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">
                + Thêm banner
            </a>
@endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng banner" :value="number_format($stats['total'])" hint="Tất cả bản ghi" icon="campaign" />
        <x-admin.kpi-card label="Đang bật" :value="number_format($stats['enabled'])" hint="Có thể hiển thị" icon="visibility" />
        <x-admin.kpi-card label="Đang tắt" :value="number_format($stats['disabled'])" hint="Ẩn khỏi web" icon="visibility_off" />
    </div>

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.banners.index'))
<form id="banner-filter-form" method="get" action="{{ route('admin.cms.banners.index') }}"
        role="search" aria-label="Tìm kiếm banner"
        @submit.prevent="applyFilters()"
        class="grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <div class="md:col-span-4">
            <label for="q" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</label>
            <div class="relative">
                <input id="q" name="q" value="{{ $filters['q'] }}" type="search"
                    placeholder="Tiêu đề hoặc nội dung" autocomplete="off"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                <span class="material-symbols-outlined pointer-events-none absolute top-2.5 left-2.5 text-[20px] text-on-surface-variant/70" aria-hidden="true">search</span>
            </div>
        </div>

        <div class="min-w-0 md:col-span-3">
            <x-admin.multi-select-filter
                name="placement"
                label="Vị trí"
                placeholder="Tất cả"
                :options="collect($placements)->map(fn ($placement) => ['id' => $placement->value, 'label' => $placement->label()])->all()"
                :selected="$filters['placement']"
            />
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="status"
                label="Trạng thái"
                placeholder="Tất cả"
                :options="[
                    ['id' => 'enabled', 'label' => 'Đang bật', 'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                    ['id' => 'disabled', 'label' => 'Đang tắt', 'tone' => 'bg-surface-container-high text-on-surface-variant border-outline-variant'],
                ]"
                :selected="$filters['status']"
            />
        </div>

        <div class="md:col-span-3">
            <span class="mb-1.5 block text-sm font-medium text-transparent select-none" aria-hidden="true">&nbsp;</span>
            <x-admin.filter-action-buttons
                fill
                :reset-url="route('admin.cms.banners.index')"
                search-aria-label="Tìm kiếm banner"
                reset-aria-label="Xoá bộ lọc banner"
            />
        </div>
    </form>
@endif

    <div id="banner-results-region" class="space-y-4">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <caption class="sr-only">Danh sách banner thông báo</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th scope="col" class="px-4 py-3">Banner</th>
                    <th scope="col" class="px-4 py-3">Vị trí</th>
                    <th scope="col" class="px-4 py-3">Đối tượng</th>
                    <th scope="col" class="px-4 py-3">Lịch</th>
                    <th scope="col" class="px-4 py-3">Trạng thái</th>
                    <th scope="col" class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($banners as $banner)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-on-surface">{{ $banner->title }}</div>
                            <div class="mt-0.5 line-clamp-1 text-on-surface-variant">{{ $banner->body }}</div>
                            <div class="mt-1 font-label-sm text-on-surface-variant">{{ $banner->variant->label() }} · TT {{ $banner->sort_order }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $banner->placement->label() }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $banner->audience->label() }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-on-surface-variant">
                            @if ($banner->starts_at || $banner->ends_at)
                                {{ $banner->starts_at?->format('d/m/Y') ?? '…' }}
                                →
                                {{ $banner->ends_at?->format('d/m/Y') ?? '…' }}
                            @else
                                Không giới hạn
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($banner->is_enabled)
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-800 border-emerald-200">Bật</span>
                            @else
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium bg-surface-container-high text-on-surface-variant border-outline-variant">Tắt</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.banners.toggle'))
<form method="post" action="{{ route('admin.cms.banners.toggle', $banner) }}" class="inline">
                                @csrf
                                <button type="submit" class="font-label-md text-on-surface-variant hover:underline">
                                    {{ $banner->is_enabled ? 'Tắt' : 'Bật' }}
                                </button>
                            </form>
@endif
                            <span class="mx-1 text-outline-variant">·</span>
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.banners.edit'))
<a href="{{ route('admin.cms.banners.edit', $banner) }}"
                                class="font-label-md text-primary hover:underline">Sửa</a>
@endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">
                            Chưa có banner. @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.banners.create'))
<a href="{{ route('admin.cms.banners.create') }}" class="text-primary hover:underline">Tạo banner đầu tiên</a>
@endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($banners->hasPages())
        <div id="banner-pagination">
            {{ $banners->links() }}
        </div>
    @endif
    </div>

    <script>
        function adminBannerFilter() {
            return {
                loading: false,
                filterForm() { return document.getElementById('banner-filter-form'); },
                async applyFilters() {
                    const form = this.filterForm();
                    if (!form) return;
                    const url = new URL(form.action, window.location.origin);
                    const params = new URLSearchParams(new FormData(form));
                    params.delete('page');
                    url.search = params.toString();
                    await this.fetchResults(url.toString());
                },
                async resetFilters(url) {
                    const form = this.filterForm();
                    form?.reset();
                    const query = form?.querySelector('[name="q"]');
                    if (query) query.value = '';
                    window.dispatchEvent(new CustomEvent('banner-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải danh sách banner');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('banner-results-region');
                        const current = document.getElementById('banner-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả banner');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách banner. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#banner-pagination a').forEach((link) => {
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            if (link.href) this.fetchResults(link.href);
                        });
                    });
                },
                init() { this.bindPagination(); },
            };
        }
    </script>
    </div>
</x-layouts.admin>
