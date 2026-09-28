<x-layouts.admin title="Trường và cơ sở đào tạo">
    <div x-data="adminInstitutionPage({
        storeUrl: @js(route('admin.institutions.store')),
        updateUrl: @js(route('admin.institutions.update', ['institution' => '__item__'])),
        defaultCountryId: @js((string) $countries->firstWhere('code', 'VN')?->id),
        initialEditing: @js($editing ? $editing->only(['id', 'country_id', 'administrative_unit_id', 'name', 'short_name', 'type', 'search_aliases', 'sort_order', 'is_active']) : null),
        reopen: @js($editing !== null || $errors->any() || request()->boolean('create')),
    })">
    <x-admin.page-header title="Trường và cơ sở đào tạo" description="Danh mục chuẩn dùng khi học viên hoàn thiện hồ sơ.">
        <x-slot:actions>
            <div class="flex gap-2">
                @if ($canCreate)
                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.institutions.create'))
<button type="button" @click="openCreate()" class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90">Thêm trường</button>
@endif
                @endif
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::learner-data._tabs')

    <x-admin.flash />

    <form id="institution-filter-form" method="get" action="{{ route('admin.institutions.index') }}"
        role="search" aria-label="Tìm kiếm trường học"
        x-data="adminInstitutionFilter(@js(filled($filters['q']) || $filters['country_id'] !== [] || $filters['administrative_unit_id'] !== [] || $filters['status'] !== []))" @submit.prevent="applyFilters()"
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <div class="md:col-span-3">
            <label for="institution-search-q" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</label>
            <div class="relative">
                <input id="institution-search-q" name="q" value="{{ $filters['q'] }}" type="search"
                    autocomplete="off" placeholder="Tên hoặc tên viết tắt"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                <span class="material-symbols-outlined pointer-events-none absolute top-2.5 left-2.5 text-[20px] text-on-surface-variant/70" aria-hidden="true">search</span>
            </div>
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="country_id"
                label="Quốc gia"
                placeholder="Tất cả"
                :options="$countries->map(fn ($country) => ['id' => $country->id, 'label' => $country->name])->all()"
                :selected="$filters['country_id']"
            />
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="administrative_unit_id"
                label="Tỉnh/Thành phố"
                placeholder="Tất cả"
                :options="$units->map(fn ($unit) => ['id' => $unit->id, 'label' => $unit->name])->all()"
                :selected="$filters['administrative_unit_id']"
            />
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="status"
                label="Trạng thái"
                placeholder="Tất cả"
                :options="[
                    ['id' => 'active', 'label' => 'Đang hiển thị', 'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                    ['id' => 'inactive', 'label' => 'Đã ẩn', 'tone' => 'bg-surface-container-high text-on-surface-variant border-outline-variant'],
                ]"
                :selected="$filters['status']"
            />
        </div>

        <div class="md:col-span-3">
            <span class="mb-1.5 block text-sm font-medium text-transparent select-none" aria-hidden="true">&nbsp;</span>
            <x-admin.filter-action-buttons
                reset-method="resetFilters"
                :reset-url="route('admin.institutions.index')"
                fill
                search-aria-label="Tìm kiếm trường học"
                reset-aria-label="Xoá bộ lọc trường học"
                reset-label="Xoá bộ lọc"
                reset-icon="restart_alt"
                reset-title="Xoá bộ lọc"
                show-reset-expression="hasAppliedFilters"
                reset-variant="text-danger"
            />
        </div>
    </form>

    <div id="institution-results-region" aria-live="polite">
    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <div class="w-full overflow-x-auto">
        <table class="w-full min-w-[760px] border-collapse text-left text-sm">
            <caption class="sr-only">Danh sách trường học và cơ sở đào tạo</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">
                <tr><th class="px-5 py-3">Tên</th><th class="px-4 py-3">Địa phương</th><th class="w-[110px] px-4 py-3 text-right">Học viên</th><th class="w-[140px] px-4 py-3">Trạng thái</th><th class="w-[160px] px-5 py-3 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/60">
                @forelse ($institutions as $institution)
                    <tr class="transition-colors hover:bg-surface-container-low">
                        <td class="px-5 py-3.5 align-middle"><p class="font-medium text-on-surface">{{ $institution->name }}</p><p class="text-label-sm text-on-surface-variant">{{ $institution->short_name ?: '—' }}</p></td>
                        <td class="px-4 py-3.5 align-middle text-on-surface-variant">{{ $institution->administrativeUnit?->name ?? '—' }}</td>
                        <td class="px-4 py-3.5 text-right align-middle tabular-nums">{{ number_format($institution->learner_profiles_count) }}</td>
                        <td class="px-4 py-3.5 align-middle"><span @class(['inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold', 'bg-emerald-50 text-emerald-800' => $institution->is_active, 'bg-slate-100 text-slate-600' => ! $institution->is_active])>{{ $institution->is_active ? 'Đang dùng' : 'Ngừng dùng' }}</span></td>
                        <td class="px-5 py-3.5 text-right align-middle">
                            @if ($canUpdate)
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.institutions.edit'))
                                        <button type="button" @click="openEdit(@js($institution->only(['id', 'country_id', 'administrative_unit_id', 'name', 'short_name', 'type', 'search_aliases', 'sort_order', 'is_active'])))" class="inline-flex h-8 items-center rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface hover:bg-surface-container-low">Sửa</button>
                                    @endif
                                    <button type="button" @click="confirming = { name: @js($institution->name), url: @js(route('admin.institutions.destroy', $institution)) }" class="inline-flex h-8 items-center rounded-lg px-2.5 text-xs font-medium text-error hover:bg-error/10">Xoá</button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">Chưa có trường phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="border-t border-outline-variant px-5 py-3 text-xs text-on-surface-variant">
            {{ $institutions->firstItem() ?? 0 }}–{{ $institutions->lastItem() ?? 0 }} / {{ $institutions->total() }} mục · {{ $institutions->lastPage() }} trang
        </div>
    </div>
    <div class="mt-4" id="institution-pagination">{{ $institutions->links() }}</div>
    </div>

    @if ($canCreate || $canUpdate)
        <div x-cloak x-show="panel !== null" x-transition.opacity @keydown.escape.window="closePanel()"
            class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-labelledby="institution-form-title">
            <button type="button" class="absolute inset-0 bg-on-surface/40" aria-label="Đóng" @click="closePanel()"></button>
            <section x-show="panel !== null" x-transition
                class="relative z-10 flex h-full w-full max-w-lg flex-col overflow-hidden border-l border-outline-variant bg-surface shadow-2xl"
                x-data="{ units: @js($units->map(fn ($unit) => ['id' => (string) $unit->id, 'country_id' => (string) $unit->country_id, 'name' => $unit->name])) }">
                <div class="flex items-start justify-between gap-3 border-b border-outline-variant px-5 py-4">
                    <div><h2 id="institution-form-title" class="text-base font-semibold text-on-surface" x-text="panel === 'edit' ? 'Sửa trường học' : 'Thêm trường học'"></h2><p class="mt-0.5 text-xs text-on-surface-variant">Danh mục dùng trong hồ sơ học viên.</p></div>
                    <button type="button" @click="closePanel()" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="px-5 pt-4"><x-auth.errors /></div>
                <form method="post" :action="formAction" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <input type="hidden" name="_method" :value="panel === 'edit' ? 'PUT' : 'POST'">
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><label for="new_institution_country" class="mb-1.5 block text-label-sm text-on-surface-variant">Quốc gia</label><select id="new_institution_country" name="country_id" x-model="form.country_id" required class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name }}</option>@endforeach</select></div>
                        <div><label for="new_institution_unit" class="mb-1.5 block text-label-sm text-on-surface-variant">Tỉnh/Thành phố</label><select id="new_institution_unit" name="administrative_unit_id" x-model="form.administrative_unit_id" required class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Chọn địa phương</option><template x-for="unit in units.filter(item => item.country_id === form.country_id)" :key="unit.id"><option :value="unit.id" x-text="unit.name"></option></template></select></div>
                    </div>
                    <div><label for="new_institution_name" class="mb-1.5 block text-label-sm text-on-surface-variant">Tên đầy đủ</label><input id="new_institution_name" name="name" x-model="form.name" required maxlength="180" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><label for="new_institution_short_name" class="mb-1.5 block text-label-sm text-on-surface-variant">Tên viết tắt</label><input id="new_institution_short_name" name="short_name" x-model="form.short_name" maxlength="80" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
                        <div><label for="new_institution_type" class="mb-1.5 block text-label-sm text-on-surface-variant">Loại cơ sở</label><select id="new_institution_type" name="type" x-model="form.type" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">@foreach(['university' => 'Đại học', 'college' => 'Cao đẳng', 'hospital' => 'Bệnh viện', 'training_center' => 'Trung tâm đào tạo', 'other' => 'Khác'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    </div>
                    <div><label for="new_institution_aliases" class="mb-1.5 block text-label-sm text-on-surface-variant">Tên khác/từ khóa tìm kiếm</label><textarea id="new_institution_aliases" name="search_aliases" x-model="form.search_aliases" rows="3" maxlength="1000" class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2" placeholder="Phân cách bằng dấu phẩy"></textarea></div>
                    <div>
                        <label for="new_institution_sort_order" class="mb-1.5 block text-label-sm text-on-surface-variant">Thứ tự hiển thị</label>
                        <input id="new_institution_sort_order" type="number" name="sort_order" min="0" max="65535" x-model="form.sort_order" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                    </div>
                    <label class="flex items-center gap-3 text-base text-on-surface"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="size-5 rounded text-primary">Đang hiển thị cho học viên</label>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-outline-variant px-5 py-4">
                        <button type="button" @click="closePanel()" class="h-11 rounded-lg px-4 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
                        <button type="submit" class="h-11 rounded-lg bg-primary px-5 text-sm font-semibold text-on-primary" x-text="panel === 'edit' ? 'Lưu thay đổi' : 'Thêm trường'"></button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    <template x-teleport="body">
        <div x-show="confirming" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-on-surface/40" @click="confirming = null"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl">
                <h3 class="text-base font-semibold text-on-surface">Xoá trường học?</h3>
                <p class="mt-2 text-sm text-on-surface-variant">«<span x-text="confirming?.name"></span>» sẽ bị xoá khỏi danh mục. Hành động này không thể hoàn tác.</p>
                <form :action="confirming?.url" method="post" class="mt-5 flex justify-end gap-2">@csrf @method('DELETE')<button type="button" @click="confirming = null" class="h-10 rounded-lg px-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button><button type="submit" class="h-10 rounded-lg bg-error px-4 text-sm font-semibold text-white hover:opacity-90">Xoá</button></form>
            </div>
        </div>
    </template>
    <script>
        function adminInstitutionFilter(initialHasFilters = false) { return { loading: false, hasAppliedFilters: initialHasFilters, filterForm() { return document.getElementById('institution-filter-form'); }, hasCurrentFilters() { const form = this.filterForm(); return form ? [...new FormData(form).entries()].some(([key, value]) => key !== 'page' && String(value).trim() !== '') : false; }, async applyFilters() { const form = this.filterForm(); if (!form) return; const url = new URL(form.getAttribute('action'), window.location.origin); const params = new URLSearchParams(new FormData(form)); params.delete('page'); url.search = params.toString(); await this.fetchResults(url.toString()); this.hasAppliedFilters = this.hasCurrentFilters(); }, async resetFilters(url) { this.filterForm()?.reset(); window.dispatchEvent(new CustomEvent('learner-catalog-filters-reset')); this.hasAppliedFilters = false; await this.fetchResults(url); }, async fetchResults(url) { this.loading = true; try { const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } }); if (!response.ok) throw new Error('Không thể tải danh sách'); const parsed = new DOMParser().parseFromString(await response.text(), 'text/html'); const next = parsed.getElementById('institution-results-region'); const current = document.getElementById('institution-results-region'); if (!next || !current) throw new Error('Không tìm thấy vùng kết quả'); current.replaceWith(next); window.history.pushState({}, '', url); this.bindPagination(); } catch (error) { console.error(error); alert('Có lỗi xảy ra khi tải danh sách. Vui lòng thử lại.'); } finally { this.loading = false; } }, bindPagination() { document.querySelectorAll('#institution-pagination a').forEach((link) => link.addEventListener('click', (event) => { event.preventDefault(); if (link.href) this.fetchResults(link.href); })); }, init() { this.bindPagination(); } }; }
        function adminInstitutionPage(config) {
            return {
                panel: null,
                confirming: null,
                form: {},
                blankForm() { return { id: null, country_id: config.defaultCountryId, administrative_unit_id: '', name: '', short_name: '', type: 'university', search_aliases: '', sort_order: 0, is_active: true }; },
                openCreate() { this.form = this.blankForm(); this.panel = 'create'; },
                openEdit(item) { this.form = { ...this.blankForm(), ...item, country_id: String(item.country_id || ''), administrative_unit_id: String(item.administrative_unit_id || ''), search_aliases: item.search_aliases || '' }; this.panel = 'edit'; },
                closePanel() { this.panel = null; },
                get formAction() { return this.panel === 'edit' ? config.updateUrl.replace('__item__', this.form.id) : config.storeUrl; },
                init() { if (config.reopen) { config.initialEditing ? this.openEdit(config.initialEditing) : this.openCreate(); } },
            };
        }
    </script>
    </div>
</x-layouts.admin>
