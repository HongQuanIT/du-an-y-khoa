<x-layouts.admin :title="$config['title']">
    <div x-data="{ formModalOpen: @js($editing !== null || $errors->any() || request()->boolean('create')) }">
    <x-admin.page-header :title="'Quản lý '.$config['title']" description="Danh mục chuẩn được dùng trong hồ sơ và autocomplete của học viên.">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route($config['route'].'.index', ['create' => 1]) }}" class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90">Thêm {{ $config['singular'] }}</a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @include('admin::learner-data._tabs')
    <x-admin.flash />
    <x-auth.errors />

    <div>
        <section>
            <form id="learner-catalog-filter-form" method="get" action="{{ route($config['route'].'.index') }}" role="search" x-data="adminLearnerCatalogFilter()" @submit.prevent="applyFilters()"
                class="mb-4 space-y-4 rounded-xl border border-outline-variant bg-surface p-4" aria-labelledby="learner-catalog-filter-heading">
                <div>
                    <h2 id="learner-catalog-filter-heading" class="font-label-lg font-semibold text-on-surface">Tìm kiếm {{ strtolower($config['title']) }}</h2>
                    <p class="mt-1 font-body-sm text-on-surface-variant">Tìm theo tên hoặc mã, sau đó thu hẹp danh sách theo các tiêu chí bên dưới.</p>
                </div>
                <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="sm:col-span-2 xl:col-auto">
                        <label for="catalog-search-q" class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant">Tìm kiếm</label>
                        <div class="relative">
                            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant" aria-hidden="true">search</span>
                            <input id="catalog-search-q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên hoặc mã danh mục" autocomplete="off" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        </div>
                    </div>
                    @if ($catalog === 'administrative-units')
                        <div class="min-w-0">
                            <x-admin.multi-select-filter name="country_id" label="Quốc gia" placeholder="Tất cả"
                                :options="$countries->map(fn ($country) => ['id' => $country->id, 'label' => $country->name])->all()" :selected="$filters['country_id']" />
                        </div>
                    @endif
                    <div class="min-w-0">
                        <x-admin.multi-select-filter name="status" label="Trạng thái" placeholder="Tất cả"
                            :options="[['id' => 'active', 'label' => 'Đang hiển thị'], ['id' => 'inactive', 'label' => 'Đã ẩn']]" :selected="$filters['status']" />
                    </div>
                    <div class="flex self-end gap-2 sm:col-span-2 xl:col-auto">
                        <button type="submit" :disabled="loading" class="inline-flex h-11 w-36 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 font-label-md font-medium text-on-primary transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-50" aria-label="Tìm kiếm {{ strtolower($config['title']) }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="loading ? 'progress_activity' : 'search'">search</span><span x-text="loading ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
                        </button>
                        <button type="button" @click="resetFilters(@js(route($config['route'].'.index')))" :disabled="loading" class="inline-flex h-11 w-28 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50" aria-label="Xoá bộ lọc {{ strtolower($config['title']) }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span><span>Xoá</span>
                        </button>
                    </div>
                </div>
            </form>

            <div id="learner-catalog-results-region" aria-live="polite">
            <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
                <table class="min-w-full text-left text-body-sm">
                    <caption class="sr-only">Danh sách {{ strtolower($config['title']) }} của học viên</caption>
                    <thead class="border-b border-outline-variant bg-surface-container-low text-label-md text-on-surface-variant">
                        <tr>
                            <th class="px-4 py-3">Tên danh mục</th>
                            <th class="px-4 py-3">Thông tin</th>
                            <th class="px-4 py-3">Học viên</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr class="border-b border-outline-variant/60 last:border-0">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-on-surface">{{ $item->name }}</p>
                                    <p class="font-mono text-label-sm text-on-surface-variant">{{ $item->code }} · TT {{ $item->sort_order }}</p>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant">
                                    @if ($catalog === 'countries')
                                        {{ number_format($item->administrative_units_count) }} địa phương · {{ number_format($item->institutions_count) }} trường
                                    @elseif ($catalog === 'administrative-units')
                                        {{ $item->country?->name }} · {{ $item->type === 'city' ? 'Thành phố' : 'Tỉnh' }} · {{ number_format($item->institutions_count) }} trường
                                    @elseif ($catalog === 'professions')
                                        {{ $item->defaults_to_graduated ? 'Mặc định đã tốt nghiệp' : ($item->requires_education_stage ? 'Yêu cầu năm học' : 'Không yêu cầu năm học') }}
                                    @else
                                        {{ $item->is_graduated ? 'Đã tốt nghiệp' : 'Đang học' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format($item->learner_profiles_count) }}</td>
                                <td class="px-4 py-3">
                                    <span @class(['rounded-full px-2.5 py-1 text-label-sm', 'bg-primary-fixed/40 text-primary' => $item->is_active, 'bg-surface-container text-on-surface-variant' => ! $item->is_active])>
                                        {{ $item->is_active ? 'Đang hiển thị' : 'Đã ẩn' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($canUpdate)
                                        <a href="{{ route($config['route'].'.index', ['edit' => $item->id] + $filters) }}" class="font-label-sm font-semibold text-primary hover:underline">Sửa</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">Chưa có dữ liệu phù hợp.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4" id="learner-catalog-pagination">{{ $items->links() }}</div>
            </div>
        </section>

    </div>

    @if ($editing || $canCreate)
        <div x-cloak x-show="formModalOpen" x-transition.opacity @keydown.escape.window="@if($editing) window.location.href = '{{ route($config['route'].'.index') }}' @else formModalOpen = false @endif"
            class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="catalog-form-title">
            @if ($editing)
                <a href="{{ route($config['route'].'.index') }}" class="absolute inset-0 bg-scrim/50" aria-label="Đóng"></a>
            @else
                <button type="button" class="absolute inset-0 bg-scrim/50" aria-label="Đóng" @click="formModalOpen = false"></button>
            @endif
            <section x-show="formModalOpen" x-transition class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-outline-variant bg-surface p-5 shadow-xl md:p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 id="catalog-form-title" class="font-title-lg font-semibold text-on-surface">{{ $editing ? 'Chỉnh sửa '.$config['singular'] : 'Thêm '.$config['singular'] }}</h2>
                    @if ($editing)
                        <a href="{{ route($config['route'].'.index') }}" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></a>
                    @else
                        <button type="button" @click="formModalOpen = false" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></button>
                    @endif
                </div>
                @include('admin::learner-data.catalogs._form')
            </section>
        </div>
    @endif
    <script>
        function adminLearnerCatalogFilter() {
            return {
                loading: false,
                filterForm() { return document.getElementById('learner-catalog-filter-form'); },
                async applyFilters() { const form = this.filterForm(); if (!form) return; const url = new URL(form.getAttribute('action'), window.location.origin); const params = new URLSearchParams(new FormData(form)); params.delete('page'); url.search = params.toString(); await this.fetchResults(url.toString()); },
                async resetFilters(url) { const form = this.filterForm(); form?.reset(); window.dispatchEvent(new CustomEvent('learner-catalog-filters-reset')); await this.fetchResults(url); },
                async fetchResults(url) { this.loading = true; try { const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } }); if (!response.ok) throw new Error('Không thể tải danh sách'); const parsed = new DOMParser().parseFromString(await response.text(), 'text/html'); const next = parsed.getElementById('learner-catalog-results-region'); const current = document.getElementById('learner-catalog-results-region'); if (!next || !current) throw new Error('Không tìm thấy vùng kết quả'); current.replaceWith(next); window.history.pushState({}, '', url); this.bindPagination(); } catch (error) { console.error(error); alert('Có lỗi xảy ra khi tải danh sách. Vui lòng thử lại.'); } finally { this.loading = false; } },
                bindPagination() { document.querySelectorAll('#learner-catalog-pagination a').forEach((link) => link.addEventListener('click', (event) => { event.preventDefault(); if (link.href) this.fetchResults(link.href); })); },
                init() { this.bindPagination(); },
            };
        }
    </script>
    </div>
</x-layouts.admin>
