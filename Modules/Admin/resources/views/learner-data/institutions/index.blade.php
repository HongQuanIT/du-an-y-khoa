<x-layouts.admin title="Trường và cơ sở đào tạo">
    <div x-data="{ formModalOpen: @js($editing !== null || $errors->any() || request()->boolean('create')) }">
    <x-admin.page-header title="Trường và cơ sở đào tạo" description="Danh mục chuẩn dùng khi học viên hoàn thiện hồ sơ.">
        <x-slot:actions>
            <div class="flex gap-2">
                @if ($canCreate)
                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.institutions.create'))
<a href="{{ route('admin.institutions.index', ['create' => 1]) }}" class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90">Thêm trường</a>
@endif
                @endif
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::learner-data._tabs')

    <x-admin.flash />

    <form id="institution-filter-form" method="get" action="{{ route('admin.institutions.index') }}"
        role="search" aria-label="Tìm kiếm trường học"
        x-data="adminInstitutionFilter()" @submit.prevent="applyFilters()"
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
            />
        </div>
    </form>

    <div id="institution-results-region" aria-live="polite">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left text-body-sm">
            <caption class="sr-only">Danh sách trường học và cơ sở đào tạo</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low text-label-md text-on-surface-variant">
                <tr><th class="px-4 py-3">Trường</th><th class="px-4 py-3">Địa phương</th><th class="px-4 py-3">Học viên</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody>
                @forelse ($institutions as $institution)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3"><p class="font-medium text-on-surface">{{ $institution->name }}</p><p class="text-label-sm text-on-surface-variant">{{ $institution->short_name ?: '—' }}</p></td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $institution->administrativeUnit?->name ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ number_format($institution->learner_profiles_count) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full bg-surface-container px-2.5 py-1 text-label-sm">{{ $institution->is_active ? 'Đang hiển thị' : 'Đã ẩn' }}</span></td>
                        <td class="px-4 py-3 text-right">
                            @if ($canUpdate)@if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.institutions.edit'))
<a href="{{ route('admin.institutions.index', ['edit' => $institution->id] + $filters) }}" class="font-label-sm font-semibold text-primary hover:underline">Chỉnh sửa</a>
@endif@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">Chưa có trường phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4" id="institution-pagination">{{ $institutions->links() }}</div>
    </div>

    @if ($editing || $canCreate)
        <div x-cloak x-show="formModalOpen" x-transition.opacity @keydown.escape.window="@if($editing) window.location.href = '{{ route('admin.institutions.index') }}' @else formModalOpen = false @endif"
            class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="institution-form-title">
            @if ($editing)
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.institutions.index'))
<a href="{{ route('admin.institutions.index') }}" class="absolute inset-0 bg-scrim/50" aria-label="Đóng"></a>
@endif
            @else
                <button type="button" class="absolute inset-0 bg-scrim/50" aria-label="Đóng" @click="formModalOpen = false"></button>
            @endif
            <section x-show="formModalOpen" x-transition
                class="relative z-10 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-outline-variant bg-surface p-5 shadow-xl md:p-6"
                x-data="{ country: @js((string) old('country_id', $editing?->country_id ?? $countries->firstWhere('code', 'VN')?->id)), units: @js($units->map(fn ($unit) => ['id' => (string) $unit->id, 'country_id' => (string) $unit->country_id, 'name' => $unit->name])) }">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 id="institution-form-title" class="font-title-lg font-semibold text-on-surface">{{ $editing ? 'Chỉnh sửa trường/cơ sở đào tạo' : 'Thêm trường/cơ sở đào tạo' }}</h2>
                    @if ($editing)
                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.institutions.index'))
<a href="{{ route('admin.institutions.index') }}" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></a>
@endif
                    @else
                        <button type="button" @click="formModalOpen = false" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></button>
                    @endif
                </div>
                <x-auth.errors />
                <form method="post" action="{{ $editing ? route('admin.institutions.update', $editing) : route('admin.institutions.store') }}" class="space-y-5">
                    @csrf
                    @if ($editing) @method('PUT') @endif
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><label for="new_institution_country" class="mb-1.5 block text-label-sm text-on-surface-variant">Quốc gia</label><select id="new_institution_country" name="country_id" x-model="country" required class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name }}</option>@endforeach</select></div>
                        <div><label for="new_institution_unit" class="mb-1.5 block text-label-sm text-on-surface-variant">Tỉnh/Thành phố</label><select id="new_institution_unit" name="administrative_unit_id" required class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Chọn địa phương</option><template x-for="unit in units.filter(item => item.country_id === country)" :key="unit.id"><option :value="unit.id" x-text="unit.name" :selected="unit.id === @js((string) old('administrative_unit_id', $editing?->administrative_unit_id))"></option></template></select></div>
                    </div>
                    <div><label for="new_institution_name" class="mb-1.5 block text-label-sm text-on-surface-variant">Tên đầy đủ</label><input id="new_institution_name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="180" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><label for="new_institution_short_name" class="mb-1.5 block text-label-sm text-on-surface-variant">Tên viết tắt</label><input id="new_institution_short_name" name="short_name" value="{{ old('short_name', $editing?->short_name) }}" maxlength="80" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
                        <div><label for="new_institution_type" class="mb-1.5 block text-label-sm text-on-surface-variant">Loại cơ sở</label><select id="new_institution_type" name="type" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">@foreach(['university' => 'Đại học', 'college' => 'Cao đẳng', 'hospital' => 'Bệnh viện', 'training_center' => 'Trung tâm đào tạo', 'other' => 'Khác'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $editing?->type ?: 'university') === $value)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                    <div><label for="new_institution_aliases" class="mb-1.5 block text-label-sm text-on-surface-variant">Tên khác/từ khóa tìm kiếm</label><textarea id="new_institution_aliases" name="search_aliases" rows="3" maxlength="1000" class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2" placeholder="Phân cách bằng dấu phẩy">{{ old('search_aliases', $editing?->search_aliases) }}</textarea></div>
                    <div>
                        <label for="new_institution_sort_order" class="mb-1.5 block text-label-sm text-on-surface-variant">Thứ tự hiển thị</label>
                        <input id="new_institution_sort_order" type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $editing?->sort_order ?? 0) }}" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <label class="flex items-center gap-2 text-body-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $editing->is_active : true)) class="size-4 rounded text-primary">Đang hiển thị cho học viên</label>
                        <button class="rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary">{{ $editing ? 'Lưu thay đổi' : 'Thêm trường' }}</button>
                    </div>
                </form>
            </section>
        </div>
    @endif
    <script>
        function adminInstitutionFilter() { return { loading: false, filterForm() { return document.getElementById('institution-filter-form'); }, async applyFilters() { const form = this.filterForm(); if (!form) return; const url = new URL(form.getAttribute('action'), window.location.origin); const params = new URLSearchParams(new FormData(form)); params.delete('page'); url.search = params.toString(); await this.fetchResults(url.toString()); }, async resetFilters(url) { this.filterForm()?.reset(); window.dispatchEvent(new CustomEvent('learner-catalog-filters-reset')); await this.fetchResults(url); }, async fetchResults(url) { this.loading = true; try { const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } }); if (!response.ok) throw new Error('Không thể tải danh sách'); const parsed = new DOMParser().parseFromString(await response.text(), 'text/html'); const next = parsed.getElementById('institution-results-region'); const current = document.getElementById('institution-results-region'); if (!next || !current) throw new Error('Không tìm thấy vùng kết quả'); current.replaceWith(next); window.history.pushState({}, '', url); this.bindPagination(); } catch (error) { console.error(error); alert('Có lỗi xảy ra khi tải danh sách. Vui lòng thử lại.'); } finally { this.loading = false; } }, bindPagination() { document.querySelectorAll('#institution-pagination a').forEach((link) => link.addEventListener('click', (event) => { event.preventDefault(); if (link.href) this.fetchResults(link.href); })); }, init() { this.bindPagination(); } }; }
    </script>
    </div>
</x-layouts.admin>
