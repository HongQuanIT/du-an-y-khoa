<x-layouts.admin title="CMS — FAQ">
    <div x-data="adminFaqFilter()" class="space-y-6">
    @include('admin::cms._sub-nav')

    <x-admin.page-header title="FAQ"
        description="Quản lý câu hỏi thường gặp hiển thị trên trang /faq.">
        <x-slot:actions>
            <a href="{{ route('landing.faq') }}" target="_blank" rel="noopener noreferrer"
                class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface hover:bg-surface-container-low">
                Xem trang FAQ ↗
            </a>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.faq.create'))
<a href="{{ route('admin.cms.faq.create') }}"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">
                + Thêm FAQ
            </a>
@endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng FAQ" :value="number_format($stats['total'])" hint="Tất cả bản ghi" icon="help" />
        <x-admin.kpi-card label="Đã xuất bản" :value="number_format($stats['published'])" hint="Hiển thị trên web" icon="visibility" />
        <x-admin.kpi-card label="Nháp" :value="number_format($stats['draft'])" hint="Chưa hiển thị công khai" icon="draft" />
    </div>

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.faq.index'))
<form id="faq-filter-form" method="get" action="{{ route('admin.cms.faq.index') }}"
        role="search" aria-label="Tìm kiếm FAQ"
        @submit.prevent="applyFilters()"
        class="grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <div class="md:col-span-4">
            <label for="q" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</label>
            <div class="relative">
                <input id="q" name="q" value="{{ $filters['q'] }}" type="search"
                    placeholder="Câu hỏi hoặc nội dung trả lời" autocomplete="off"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                <span class="material-symbols-outlined pointer-events-none absolute top-2.5 left-2.5 text-[20px] text-on-surface-variant/70" aria-hidden="true">search</span>
            </div>
        </div>

        <div class="min-w-0 md:col-span-3">
            <x-admin.multi-select-filter
                name="category"
                label="Danh mục"
                placeholder="Tất cả"
                :options="collect($categories)->map(fn ($cat) => ['id' => $cat->value, 'label' => $cat->label()])->all()"
                :selected="$filters['category']"
            />
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="status"
                label="Trạng thái"
                placeholder="Tất cả"
                :options="[
                    ['id' => 'published', 'label' => 'Đã xuất bản', 'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                    ['id' => 'draft', 'label' => 'Nháp', 'tone' => 'bg-surface-container-high text-on-surface-variant border-outline-variant'],
                ]"
                :selected="$filters['status']"
            />
        </div>

        <div class="md:col-span-3">
            <span class="mb-1.5 block text-sm font-medium text-transparent select-none" aria-hidden="true">&nbsp;</span>
            <x-admin.filter-action-buttons
                fill
                :reset-url="route('admin.cms.faq.index')"
                search-aria-label="Tìm kiếm FAQ"
                reset-aria-label="Xoá bộ lọc FAQ"
            />
        </div>
    </form>
@endif

    <div id="faq-results-region" class="space-y-4">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <caption class="sr-only">Danh sách câu hỏi thường gặp</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th scope="col" class="px-4 py-3 w-16">TT</th>
                    <th scope="col" class="px-4 py-3">Câu hỏi</th>
                    <th scope="col" class="px-4 py-3">Danh mục</th>
                    <th scope="col" class="px-4 py-3">Trạng thái</th>
                    <th scope="col" class="px-4 py-3">Cập nhật</th>
                    <th scope="col" class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($faqs as $faq)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3 text-on-surface-variant">
                            <div class="flex flex-col gap-1">
                                <span class="font-mono text-xs">{{ $faq->sort_order }}</span>
                                <div class="flex gap-0.5">
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.faq.move-up'))
<form method="post" action="{{ route('admin.cms.faq.move-up', $faq) }}">
                                        @csrf
                                        <button type="submit" class="rounded p-0.5 text-on-surface-variant hover:bg-surface-container-low" title="Lên">
                                            <span class="material-symbols-outlined text-[18px]">arrow_upward</span>
                                        </button>
                                    </form>
@endif
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.faq.move-down'))
<form method="post" action="{{ route('admin.cms.faq.move-down', $faq) }}">
                                        @csrf
                                        <button type="submit" class="rounded p-0.5 text-on-surface-variant hover:bg-surface-container-low" title="Xuống">
                                            <span class="material-symbols-outlined text-[18px]">arrow_downward</span>
                                        </button>
                                    </form>
@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface max-w-md">{{ $faq->question }}</div>
                            <div class="font-label-sm text-label-sm text-on-surface-variant">#{{ $faq->id }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $faq->category->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($faq->is_published)
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-800 border-emerald-200">Đã xuất bản</span>
                            @else
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium bg-surface-container-high text-on-surface-variant border-outline-variant">Nháp</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">
                            {{ $faq->updated_at?->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.faq.edit'))
<a href="{{ route('admin.cms.faq.edit', $faq) }}"
                                class="font-label-md text-primary hover:underline">Sửa</a>
@endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-on-surface-variant">
                            Chưa có FAQ nào.
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.cms.faq.create'))
<a href="{{ route('admin.cms.faq.create') }}" class="text-primary hover:underline">Thêm FAQ đầu tiên</a>
@endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($faqs->hasPages())
        <div id="faq-pagination">
            {{ $faqs->links() }}
        </div>
    @endif
    </div>

    <script>
        function adminFaqFilter() {
            return {
                loading: false,
                filterForm() { return document.getElementById('faq-filter-form'); },
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
                    window.dispatchEvent(new CustomEvent('faq-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải danh sách FAQ');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('faq-results-region');
                        const current = document.getElementById('faq-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả FAQ');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách FAQ. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#faq-pagination a').forEach((link) => {
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
