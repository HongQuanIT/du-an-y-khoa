<x-layouts.admin :title="$config['title']">
    <div x-data="learnerCatalogPage({
        storeUrl: @js(route($config['route'].'.store')),
        updateUrl: @js(route($config['route'].'.update', ['item' => '__item__'])),
        initialEditing: @js($editing ? collect($editing->getAttributes())->only(['id', 'name', 'code', 'country_id', 'type', 'requires_education_stage', 'defaults_to_graduated', 'sort_order', 'is_active'])->all() : null),
        reopen: @js($editing !== null || $errors->any() || request()->boolean('create')),
    })">
    <x-admin.page-header :title="'Quản lý '.$config['title']" description="Danh mục chuẩn được dùng trong hồ sơ và autocomplete của học viên.">
        @if ($canCreate)
            <x-slot:actions>
                <button type="button" @click="openCreate()" class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90">Thêm {{ $config['singular'] }}</button>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @include('admin::learner-data._tabs')
    <x-admin.flash />
    <x-auth.errors />

    <div>
        <section>
            <form id="learner-catalog-filter-form" method="get" action="{{ route($config['route'].'.index') }}"
                role="search" aria-label="Tìm kiếm {{ strtolower($config['title']) }}"
                x-data="adminLearnerCatalogFilter(@js(filled($filters['q']) || $filters['status'] !== [] || $filters['country_id'] !== []))" @submit.prevent="applyFilters()"
                class="mb-4 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
                <div class="{{ $catalog === 'administrative-units' ? 'md:col-span-3' : 'md:col-span-5' }}">
                    <label for="catalog-search-q" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</label>
                    <div class="relative">
                        <input id="catalog-search-q" name="q" value="{{ $filters['q'] }}" type="search"
                            placeholder="Tên hoặc mã danh mục" autocomplete="off"
                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                        <span class="material-symbols-outlined pointer-events-none absolute top-2.5 left-2.5 text-[20px] text-on-surface-variant/70" aria-hidden="true">search</span>
                    </div>
                </div>

                @if ($catalog === 'administrative-units')
                    <div class="min-w-0 md:col-span-3">
                        <x-admin.multi-select-filter
                            name="country_id"
                            label="Quốc gia"
                            placeholder="Tất cả"
                            :options="$countries->map(fn ($country) => ['id' => $country->id, 'label' => $country->name])->all()"
                            :selected="$filters['country_id']"
                        />
                    </div>
                @endif

                <div class="min-w-0 md:col-span-2">
                    <x-admin.multi-select-filter
                        name="status"
                        label="Trạng thái"
                        placeholder="Tất cả"
                        :options="[
                            ['id' => 'active', 'label' => 'Đang dùng', 'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                            ['id' => 'inactive', 'label' => 'Ngừng dùng', 'tone' => 'bg-surface-container-high text-on-surface-variant border-outline-variant'],
                        ]"
                        :selected="$filters['status']"
                    />
                </div>

                <div class="md:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-transparent select-none" aria-hidden="true">&nbsp;</span>
                    <x-admin.filter-action-buttons
                        reset-method="resetFilters"
                        :reset-url="route($config['route'].'.index')"
                        fill
                        :search-aria-label="'Tìm kiếm '.strtolower($config['title'])"
                        :reset-aria-label="'Xoá bộ lọc '.strtolower($config['title'])"
                        reset-label="Xoá bộ lọc"
                        reset-icon="restart_alt"
                        reset-title="Xoá bộ lọc"
                        show-reset-expression="hasAppliedFilters"
                        reset-variant="text-danger"
                    />
                </div>
            </form>

            <div id="learner-catalog-results-region" aria-live="polite">
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                    <caption class="sr-only">Danh sách {{ strtolower($config['title']) }} của học viên</caption>
                    <thead class="border-b border-outline-variant bg-surface-container-low text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">
                        <tr>
                            <th class="px-5 py-3">Tên</th>
                            <th class="px-4 py-3">Thông tin</th>
                            <th class="w-[110px] px-4 py-3 text-right">Học viên</th>
                            <th class="w-[140px] px-4 py-3">Trạng thái</th>
                            <th class="w-[160px] px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($items as $item)
                            <tr class="transition-colors hover:bg-surface-container-low">
                                <td class="px-5 py-3.5 align-middle">
                                    <p class="font-medium text-on-surface">{{ $item->name }}</p>
                                    @if ($catalog !== 'administrative-units')
                                        <p class="font-mono text-label-sm text-on-surface-variant">{{ $item->code }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 align-middle text-on-surface-variant">
                                    @if ($catalog === 'countries')
                                        {{ number_format($item->administrative_units_count) }} địa phương · {{ number_format($item->institutions_count) }} trường
                                    @elseif ($catalog === 'administrative-units')
                                        {{ $item->country?->name }} · {{ $item->type === 'city' ? 'Thành phố' : 'Tỉnh' }} · {{ number_format($item->institutions_count) }} trường
                                    @elseif ($catalog === 'professions')
                                        {{ $item->defaults_to_graduated ? 'Đã tốt nghiệp' : ($item->requires_education_stage ? 'Yêu cầu năm học' : 'Không yêu cầu năm học') }}
                                    @else
                                        {{ $item->is_graduated ? 'Đã tốt nghiệp' : 'Đang học' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right align-middle tabular-nums">{{ number_format($item->learner_profiles_count) }}</td>
                                <td class="px-4 py-3.5 align-middle">
                                    <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold', 'bg-emerald-50 text-emerald-800' => $item->is_active, 'bg-slate-100 text-slate-600' => ! $item->is_active])>
                                        {{ $item->is_active ? 'Đang dùng' : 'Ngừng dùng' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right align-middle">
                                    @if ($canUpdate)
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button" @click="openEdit(@js(collect($item->getAttributes())->only(['id', 'name', 'code', 'country_id', 'type', 'requires_education_stage', 'defaults_to_graduated', 'sort_order', 'is_active'])->all()))" class="inline-flex h-8 items-center rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface hover:bg-surface-container-low">Sửa</button>
                                            <button type="button" @click="confirming = { id: {{ $item->id }}, name: @js($item->name), url: @js(route($config['route'].'.destroy', $item->id)) }" class="inline-flex h-8 items-center rounded-lg px-2.5 text-xs font-medium text-error hover:bg-error/10">Xoá</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">Chưa có dữ liệu phù hợp.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <div class="border-t border-outline-variant px-5 py-3 text-xs text-on-surface-variant">
                    {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} / {{ $items->total() }} mục · {{ $items->lastPage() }} trang
                </div>
            </div>
            <div class="mt-4" id="learner-catalog-pagination">{{ $items->links() }}</div>
            </div>
        </section>

    </div>

    @if ($canCreate || $canUpdate)
        <div x-cloak x-show="panel !== null" x-transition.opacity @keydown.escape.window="closePanel()"
            class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-labelledby="catalog-form-title">
            <button type="button" class="absolute inset-0 bg-on-surface/40" aria-label="Đóng" @click="closePanel()"></button>
            <section x-show="panel !== null" x-transition class="relative z-10 flex h-full w-full max-w-md flex-col overflow-hidden border-l border-outline-variant bg-surface shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-outline-variant px-5 py-4">
                    <div><h2 id="catalog-form-title" class="text-base font-semibold text-on-surface" x-text="panel === 'edit' ? 'Sửa {{ $config['singular'] }}' : 'Thêm {{ $config['singular'] }}'"></h2><p class="mt-0.5 text-xs text-on-surface-variant">Danh mục dùng trong hồ sơ và bộ lọc học viên.</p></div>
                    <button type="button" @click="closePanel()" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></button>
                </div>
                @include('admin::learner-data.catalogs._form')
            </section>
        </div>
    @endif

    <template x-teleport="body">
        <div x-show="confirming" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-on-surface/40" @click="confirming = null"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl">
                <h3 class="text-base font-semibold text-on-surface">Xoá {{ $config['singular'] }}?</h3>
                <p class="mt-2 text-sm text-on-surface-variant">«<span x-text="confirming?.name"></span>» sẽ bị xoá khỏi danh mục. Hành động này không thể hoàn tác.</p>
                <form :action="confirming?.url" method="post" class="mt-5 flex justify-end gap-2">
                    @csrf @method('DELETE')
                    <button type="button" @click="confirming = null" class="h-10 rounded-lg px-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
                    <button type="submit" class="h-10 rounded-lg bg-error px-4 text-sm font-semibold text-white hover:opacity-90">Xoá</button>
                </form>
            </div>
        </div>
    </template>
    <script>
        function adminLearnerCatalogFilter(initialHasFilters = false) {
            return {
                loading: false,
                hasAppliedFilters: initialHasFilters,
                filterForm() { return document.getElementById('learner-catalog-filter-form'); },
                hasCurrentFilters() { const form = this.filterForm(); return form ? [...new FormData(form).entries()].some(([key, value]) => key !== 'page' && String(value).trim() !== '') : false; },
                async applyFilters() { const form = this.filterForm(); if (!form) return; const url = new URL(form.getAttribute('action'), window.location.origin); const params = new URLSearchParams(new FormData(form)); params.delete('page'); url.search = params.toString(); await this.fetchResults(url.toString()); this.hasAppliedFilters = this.hasCurrentFilters(); },
                async resetFilters(url) { const form = this.filterForm(); form?.reset(); window.dispatchEvent(new CustomEvent('learner-catalog-filters-reset')); this.hasAppliedFilters = false; await this.fetchResults(url); },
                async fetchResults(url) { this.loading = true; try { const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } }); if (!response.ok) throw new Error('Không thể tải danh sách'); const parsed = new DOMParser().parseFromString(await response.text(), 'text/html'); const next = parsed.getElementById('learner-catalog-results-region'); const current = document.getElementById('learner-catalog-results-region'); if (!next || !current) throw new Error('Không tìm thấy vùng kết quả'); current.replaceWith(next); window.history.pushState({}, '', url); this.bindPagination(); } catch (error) { console.error(error); alert('Có lỗi xảy ra khi tải danh sách. Vui lòng thử lại.'); } finally { this.loading = false; } },
                bindPagination() { document.querySelectorAll('#learner-catalog-pagination a').forEach((link) => link.addEventListener('click', (event) => { event.preventDefault(); if (link.href) this.fetchResults(link.href); })); },
                init() { this.bindPagination(); },
            };
        }

        function learnerCatalogPage(config) {
            return {
                panel: null,
                confirming: null,
                form: {},
                blankForm() { return { id: null, name: '', code: '', codeTouched: false, country_id: '', type: 'province', requires_education_stage: true, defaults_to_graduated: false, profile_status: 'requires_stage', sort_order: 0, is_active: 1 }; },
                openCreate() { this.form = this.blankForm(); this.panel = 'create'; },
                openEdit(item) {
                    this.form = {
                        ...this.blankForm(),
                        ...item,
                        codeTouched: true,
                        country_id: String(item.country_id || ''),
                        profile_status: item.defaults_to_graduated ? 'graduated' : 'requires_stage',
                        is_active: item.is_active ? 1 : 0,
                    };
                    this.panel = 'edit';
                },
                closePanel() { this.panel = null; },
                generateCode() {
                    if (this.form.codeTouched || !this.form.name.trim()) return;
                    const words = this.form.name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toUpperCase().match(/[A-Z]+/g) || [];
                    this.form.code = this.form.country_id !== undefined && @js($catalog === 'countries')
                        ? (words.length > 1 ? words.map((word) => word[0]).join('').slice(0, 2) : (words[0] || '').slice(0, 2))
                        : words.map((word) => word[0]).join('').slice(0, 20);
                },
                get formAction() { return this.panel === 'edit' ? config.updateUrl.replace('__item__', this.form.id) : config.storeUrl; },
                init() { if (config.reopen) { config.initialEditing ? this.openEdit(config.initialEditing) : this.openCreate(); } },
            };
        }
    </script>
    </div>
</x-layouts.admin>
