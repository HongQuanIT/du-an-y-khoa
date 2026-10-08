@php
    $editingId = (int) old('_editing_id', 0);
    $reopenPanel = old('_catalog') === 'exam-catalogs' && $errors->isNotEmpty()
        ? ($editingId > 0 ? 'edit' : 'create')
        : null;
    $reopenUpdateUrl = $editingId > 0
        ? route(\App\Support\Auth\PortalRoute::content('exam-catalogs.update'), $editingId)
        : '';
@endphp

<x-layouts.admin title="Kỳ thi">
    <x-admin.page-header title="Kỳ thi"
        description="Danh mục kỳ thi gắn với đối tượng. Ma trận đề là tùy chọn và chỉ dùng khi học viên tạo phiên đề thi.">
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'exam-catalogs'])

    <x-admin.flash :except="['name', 'code', 'blueprint_id', 'profession_ids', 'description', 'status']" />

    <div class="space-y-4"
         x-data="examCatalogIndex({
            indexUrl: @js(route(\App\Support\Auth\PortalRoute::content('exam-catalogs.index'))),
            items: @js($catalogItems),
            meta: @js($catalogMeta),
            query: @js($filters['q'] ?? ''),
            status: @js($filters['status'] ?? 'all'),
            dir: @js($filters['dir'] ?? 'asc'),
            focusId: @js($focusId),
            storeUrl: @js(route(\App\Support\Auth\PortalRoute::content('exam-catalogs.store'))),
            canCreate: @js($canCreate),
            canUpdate: @js($canUpdate),
            canDelete: @js($canDelete),
            professions: @js($professions->map(fn ($profession) => ['id' => (int) $profession->id, 'name' => $profession->name, 'code' => $profession->code])->values()),
            openCreatePanel: @js($openCreate),
            reopenPanel: @js($reopenPanel),
            fieldErrors: @js([
                'name' => $reopenPanel ? $errors->first('name') : '',
                'code' => $reopenPanel ? $errors->first('code') : '',
                'blueprint_id' => $reopenPanel ? $errors->first('blueprint_id') : '',
            ]),
            oldForm: @js([
                'id' => $editingId ?: null,
                'update_url' => $reopenUpdateUrl,
                'name' => old('name', ''),
                'code' => old('code', ''),
                'description' => old('description', ''),
                'status' => old('status', 'active'),
                'sort_order' => (int) old('sort_order', 0),
                'blueprint_id' => old('blueprint_id') ? (int) old('blueprint_id') : '',
                'profession_ids' => collect(old('profession_ids', []))->map(fn ($id) => (int) $id)->values(),
            ]),
         })">
        <div class="flex flex-col gap-3 rounded-xl border border-outline-variant bg-surface p-4 lg:flex-row lg:items-center">
            <div class="relative min-w-0 flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                <input type="search" x-model="query" @input="applySearch()"
                    placeholder="Tìm kỳ thi theo tên hoặc mã…"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>
            <select x-model="statusFilter" @change="changeFilter()"
                class="h-11 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="all">Mọi trạng thái</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $statusLabels[$status->value] ?? $status->value }}</option>
                @endforeach
            </select>
            <p class="text-xs tabular-nums text-on-surface-variant lg:min-w-[7rem]">
                <span x-text="total"></span> mục
            </p>
            @if ($canCreate)
                <button type="button" @click="openCreate()"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Thêm kỳ thi
                </button>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface" :class="loading && 'opacity-70'">
            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[1460px] table-fixed border-collapse text-left text-sm">
                    <caption class="sr-only">Danh sách kỳ thi</caption>
                    <thead class="border-b border-outline-variant bg-surface-container-low text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">
                        <tr>
                            <th scope="col" class="w-[250px] px-5 py-3">
                                <button type="button" @click="toggleNameSort()"
                                    class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-on-surface">
                                    Tên
                                    <span class="material-symbols-outlined text-[16px]"
                                        x-text="nameSort === 'asc' ? 'arrow_upward' : 'arrow_downward'"></span>
                                </button>
                            </th>
                            <th scope="col" class="w-[190px] px-4 py-3">Đối tượng</th>
                            <th scope="col" class="w-[300px] px-4 py-3">Ma trận</th>
                            <th scope="col" class="w-[150px] px-4 py-3 text-right" title="Số câu của mỗi đề theo ma trận">Câu / đề</th>
                            <th scope="col" class="w-[120px] px-4 py-3">Trạng thái</th>
                            <th scope="col" class="w-[450px] px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        <template x-for="item in items" :key="'exam-catalog-'+item.id">
                            <tr class="transition-colors hover:bg-surface-container-low"
                                :id="'node-exam-catalog-'+item.id"
                                :class="focusId === item.id ? 'bg-primary/5' : ''">
                                <td class="px-5 py-3.5 align-middle">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-on-surface" x-text="item.name" :title="item.name"></p>
                                        <p x-show="item.code" class="truncate font-mono text-[11px] text-on-surface-variant" x-text="item.code"></p>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 align-middle text-on-surface-variant">
                                    <span class="line-clamp-2 break-words" :title="(item.profession_names || []).join(', ')" x-text="(item.profession_names || []).join(', ') || '—'"></span>
                                </td>
                                <td class="px-4 py-3.5 align-middle text-on-surface">
                                    <span class="line-clamp-2 break-words" :title="item.blueprint_name || 'Chưa gắn'" x-text="item.blueprint_name || 'Chưa gắn'"></span>
                                </td>
                                <td class="px-4 py-3.5 text-right align-middle tabular-nums text-on-surface">
                                    <span x-text="item.questions_count"></span>
                                    <span x-show="item.blueprint_id" class="block whitespace-nowrap text-[10px] text-on-surface-variant" title="Tổng số câu đã gắn với kỳ thi, gồm cả câu chưa thể đưa vào đề">Kho đã gắn: <span x-text="item.attached_questions_count"></span></span>
                                </td>
                                <td class="px-4 py-3.5 align-middle">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="item.status === 'active'
                                            ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                        x-text="item.status === 'active' ? 'Đang dùng' : 'Ngừng dùng'"></span>
                                </td>
                                <td class="px-5 py-3.5 text-right align-middle">
                                    <div class="flex flex-nowrap items-center justify-end gap-2 whitespace-nowrap">
                                        @if (request()->routeIs('admin.*'))
                                        <a x-show="item.sample_exam_id" x-cloak
                                            :href="@js(url('/admin/exams')) + '/' + item.sample_exam_id"
                                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/5 px-3 text-xs font-semibold text-primary transition-colors hover:border-primary/40 hover:bg-primary/10"
                                            title="Xem bài thi mẫu hiện tại">
                                            <span class="material-symbols-outlined text-[17px]">visibility</span>
                                            Xem mẫu
                                        </a>
                                        @can('blueprint.update')
                                        <form x-show="canUpdate && item.blueprint_id" x-cloak method="POST"
                                            :action="@js(url('/admin/exam-catalogs')) + '/' + item.id + '/sample'"
                                            class="inline-flex shrink-0">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-primary/30 bg-surface px-3 text-xs font-semibold text-primary transition-colors hover:bg-primary/5"
                                                :title="item.sample_exam_id ? 'Tạo phiên bản bài thi mẫu mới' : 'Tạo bài thi mẫu'">
                                                <span class="material-symbols-outlined text-[17px]" x-text="item.sample_exam_id ? 'refresh' : 'add_circle'"></span>
                                                <span x-text="item.sample_exam_id ? 'Tạo bản mới' : 'Tạo bài mẫu'"></span>
                                            </button>
                                        </form>
                                        @endcan
                                        @endif
                                        <button type="button" x-show="canUpdate" @click="openEdit(item)"
                                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 text-xs font-medium text-on-surface transition-colors hover:bg-surface-container-low"
                                            title="Sửa kỳ thi">
                                            <span class="material-symbols-outlined text-[17px]">edit</span>
                                            Sửa
                                        </button>
                                        <button type="button" x-show="canDelete" @click="confirming = item"
                                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-error transition-colors hover:bg-error/10"
                                            title="Xoá kỳ thi">
                                            <span class="material-symbols-outlined text-[17px]">delete</span>
                                            Xoá
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="items.length === 0" class="px-5 py-14 text-center">
                <span class="material-symbols-outlined mb-2 text-[32px] text-on-surface-variant/50">folder_off</span>
                <p class="text-sm font-medium text-on-surface">Không có kỳ thi phù hợp.</p>
                <p class="mt-1 text-xs text-on-surface-variant">Thêm kỳ thi, rồi gắn đối tượng và ma trận nếu học viên cần tạo phiên đề thi.</p>
            </div>

            @include('admin::curriculum._catalog-pager')
        </div>

        <template x-teleport="body">
            <div x-show="panel !== null" x-cloak class="fixed inset-0 z-50 flex justify-end">
                <div class="absolute inset-0 bg-on-surface/40" @click="closePanel()"></div>
                <aside class="relative flex h-full w-full max-w-md flex-col bg-surface shadow-2xl" @keydown.escape.window="panel !== null && closePanel()">
                    <div class="flex items-center justify-between border-b border-outline-variant px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-on-surface" x-text="panel === 'create' ? 'Thêm kỳ thi' : 'Sửa kỳ thi'"></h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Gắn đối tượng của kỳ thi. Ma trận chỉ dùng khi học viên tạo phiên đề thi.</p>
                        </div>
                        <button type="button" @click="closePanel()" class="rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-container-low">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>

                    <form :action="formAction" method="post" class="flex min-h-0 flex-1 flex-col">
                        @csrf
                        <input type="hidden" name="_method" :value="panel === 'edit' ? 'PUT' : 'POST'">
                        <input type="hidden" name="_catalog" value="exam-catalogs">
                        <input type="hidden" name="_editing_id" :value="panel === 'edit' && form.id ? form.id : ''">
                        <input type="hidden" name="sort_order" :value="form.sort_order || 0">
                        <template x-for="id in form.profession_ids" :key="'profession-input-'+id">
                            <input type="hidden" name="profession_ids[]" :value="id">
                        </template>
                        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Tên *</label>
                                <input name="name" x-model="form.name" required maxlength="255" placeholder="Ví dụ: Kỳ thi tốt nghiệp"
                                    class="h-11 w-full rounded-lg border bg-surface-container-lowest px-3 text-sm outline-none focus:ring-2"
                                    :class="fieldErrors.name ? 'border-error focus:border-error focus:ring-error/20' : 'border-outline-variant focus:border-primary focus:ring-primary/20'">
                                <p x-show="fieldErrors.name" x-text="fieldErrors.name" class="mt-1.5 text-xs text-error"></p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Mã</label>
                                <input name="code" x-model="form.code" maxlength="100" placeholder="Tùy chọn"
                                    class="h-11 w-full rounded-lg border bg-surface-container-lowest px-3 font-mono text-sm outline-none focus:ring-2"
                                    :class="fieldErrors.code ? 'border-error focus:border-error focus:ring-error/20' : 'border-outline-variant focus:border-primary focus:ring-primary/20'">
                                <p x-show="fieldErrors.code" x-text="fieldErrors.code" class="mt-1.5 text-xs text-error"></p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Trạng thái</label>
                                <select name="status" x-model="form.status"
                                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}">{{ $statusLabels[$status->value] ?? $status->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Ma trận đề thi</label>
                                <select name="blueprint_id" x-model="form.blueprint_id"
                                    class="h-11 w-full rounded-lg border bg-surface-container-lowest px-3 text-sm outline-none focus:ring-2"
                                    :class="fieldErrors.blueprint_id ? 'border-error focus:border-error focus:ring-error/20' : 'border-outline-variant focus:border-primary focus:ring-primary/20'">
                                    <option value="">Chưa gắn ma trận</option>
                                    @foreach ($blueprints as $blueprint)
                                        <option value="{{ $blueprint->id }}">{{ $blueprint->name }}</option>
                                    @endforeach
                                </select>
                                <p x-show="fieldErrors.blueprint_id" x-text="fieldErrors.blueprint_id" class="mt-1.5 text-xs text-error"></p>
                            </div>
                            <div>
                                <p class="mb-1.5 text-xs font-semibold text-on-surface-variant">Đối tượng</p>
                                <div class="mb-2 flex flex-wrap gap-1.5" x-show="form.profession_ids.length">
                                    <template x-for="id in form.profession_ids" :key="'profession-chip-'+id">
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                            <span class="min-w-0 truncate" x-text="professionLabel(id)"></span>
                                            <button type="button" @click="removeProfession(id)"
                                                class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-primary/70 hover:bg-primary/15 hover:text-primary"
                                                :aria-label="'Bỏ ' + professionLabel(id)">
                                                <span class="material-symbols-outlined text-[14px]">close</span>
                                            </button>
                                        </span>
                                    </template>
                                </div>
                                <div class="relative" @click.outside="professionOpen = false">
                                    <div class="relative">
                                        <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                                        <input type="search" x-model="professionQuery"
                                            @focus="professionOpen = true"
                                            @click="professionOpen = true"
                                            @keydown.escape.prevent="professionOpen = false"
                                            @keydown.enter.prevent="addFirstProfession()"
                                            autocomplete="off" placeholder="Tìm và chọn đối tượng…"
                                            role="combobox" :aria-expanded="professionOpen"
                                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-lowest py-2 pl-10 pr-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                    </div>
                                    <div x-show="professionOpen" x-cloak
                                        class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl"
                                        role="listbox">
                                        <template x-for="item in professionSuggestions" :key="'sug-profession-'+item.id">
                                            <button type="button" role="option" @mousedown.prevent="addProfession(item)"
                                                class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-surface-container-low">
                                                <span class="min-w-0 truncate font-medium" x-text="item.name"></span>
                                                <span class="shrink-0 font-mono text-[11px] text-on-surface-variant" x-show="item.code" x-text="item.code"></span>
                                            </button>
                                        </template>
                                        <p x-show="professionSuggestions.length === 0 && professions.length > 0" class="px-3 py-2 text-xs text-on-surface-variant">
                                            Không còn đối tượng phù hợp.
                                        </p>
                                        <p x-show="professions.length === 0" class="px-3 py-2 text-xs text-error">
                                            Chưa có đối tượng — hãy thêm chức danh trước.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Mô tả</label>
                                <textarea name="description" x-model="form.description" rows="3" maxlength="2000" placeholder="Tùy chọn"
                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-2 border-t border-outline-variant px-5 py-4">
                            <button type="button" @click="closePanel()"
                                class="h-10 rounded-lg px-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
                            <button type="submit" class="h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary">
                                <span x-text="panel === 'create' ? 'Thêm kỳ thi' : 'Lưu thay đổi'"></span>
                            </button>
                        </div>
                    </form>
                </aside>
            </div>
        </template>

        <template x-teleport="body">
            <div x-show="confirming" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-on-surface/40" @click="confirming = null"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl">
                    <h3 class="text-base font-semibold text-on-surface">Xoá kỳ thi?</h3>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        «<span x-text="confirming?.name"></span>» sẽ bị gỡ khỏi danh mục.
                        Câu hỏi đã gắn kỳ thi này sẽ bỏ liên kết kỳ thi và vẫn nằm trong ngân hàng.
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

    <script>
        function examCatalogIndex(config) {
            return {
                indexUrl: config.indexUrl,
                items: config.items || [],
                focusId: config.focusId,
                storeUrl: config.storeUrl,
                canCreate: config.canCreate,
                canUpdate: config.canUpdate,
                canDelete: config.canDelete,
                professions: config.professions || [],
                professionQuery: '',
                professionOpen: false,
                query: config.query || '',
                statusFilter: config.status || 'all',
                nameSort: config.dir === 'desc' ? 'desc' : 'asc',
                page: Number(config.meta?.page || 1),
                lastPage: Number(config.meta?.last_page || 1),
                total: Number(config.meta?.total || 0),
                from: config.meta?.from || 0,
                to: config.meta?.to || 0,
                loading: false,
                abort: null,
                searchTimer: null,
                panel: null,
                confirming: null,
                form: {
                    name: '', code: '', description: '', status: 'active',
                    sort_order: 0, blueprint_id: '', profession_ids: [],
                },
                fieldErrors: {
                    name: config.fieldErrors?.name || '',
                    code: config.fieldErrors?.code || '',
                    blueprint_id: config.fieldErrors?.blueprint_id || '',
                },
                get formAction() {
                    return this.panel === 'edit' && this.form.update_url
                        ? this.form.update_url
                        : this.storeUrl;
                },
                get pageNumbers() {
                    const total = Math.max(1, this.lastPage);
                    const current = this.page;
                    let start = Math.max(1, current - 2);
                    let end = Math.min(total, start + 4);
                    start = Math.max(1, end - 4);
                    const pages = [];
                    for (let n = start; n <= end; n++) pages.push(n);
                    return pages;
                },
                get pageRangeLabel() {
                    if (this.total === 0) return '0 mục';
                    return (this.from || 0) + '–' + (this.to || 0) + ' / ' + this.total + ' mục';
                },
                catalogUrl(params) {
                    const search = new URLSearchParams();
                    if (params.q) search.set('q', params.q);
                    if (params.status && params.status !== 'all') search.set('status', params.status);
                    if (params.dir === 'desc') search.set('dir', 'desc');
                    if (params.page > 1) search.set('page', String(params.page));
                    const qs = search.toString();
                    return qs === '' ? this.indexUrl : this.indexUrl + '?' + qs;
                },
                currentParams() {
                    return {
                        q: this.query.trim(),
                        status: this.statusFilter,
                        dir: this.nameSort,
                        page: this.page,
                    };
                },
                applySearch() {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => this.fetchPage({ resetPage: true }), 400);
                },
                changeFilter() {
                    this.fetchPage({ resetPage: true });
                },
                toggleNameSort() {
                    this.nameSort = this.nameSort === 'asc' ? 'desc' : 'asc';
                    this.fetchPage({ resetPage: true, history: 'push' });
                },
                goToPage(page) {
                    this.page = page;
                    this.fetchPage({ history: 'push' });
                },
                async fetchPage(options = {}) {
                    if (options.resetPage) this.page = 1;
                    const url = this.catalogUrl(this.currentParams());
                    if (options.history === 'push') {
                        history.pushState({}, '', url);
                    } else if (options.history !== false) {
                        history.replaceState({}, '', url);
                    }
                    this.loading = true;
                    this.abort?.abort();
                    this.abort = new AbortController();
                    try {
                        const response = await fetch(url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            signal: this.abort.signal,
                        });
                        if (! response.ok) return;
                        const json = await response.json();
                        this.items = json.data || [];
                        this.total = Number(json.meta?.total || 0);
                        this.page = Number(json.meta?.page || 1);
                        this.lastPage = Number(json.meta?.last_page || 1);
                        this.from = json.meta?.from || 0;
                        this.to = json.meta?.to || 0;
                    } catch (error) {
                        if (error?.name !== 'AbortError') console.error(error);
                    } finally {
                        this.loading = false;
                    }
                },
                blankForm() {
                    return {
                        id: null,
                        update_url: '',
                        name: '',
                        code: '',
                        description: '',
                        status: 'active',
                        sort_order: 0,
                        blueprint_id: '',
                        profession_ids: [],
                    };
                },
                clearFieldErrors() {
                    this.fieldErrors = { name: '', code: '', blueprint_id: '' };
                },
                resetProfessionPicker() {
                    this.professionQuery = '';
                    this.professionOpen = false;
                },
                professionLabel(id) {
                    const needle = Number(id);
                    return this.professions.find((item) => Number(item.id) === needle)?.name || ('#' + id);
                },
                get professionSuggestions() {
                    const q = this.professionQuery.trim().toLowerCase();
                    const selected = this.form.profession_ids.map(Number);

                    return this.professions
                        .filter((item) => ! selected.includes(Number(item.id)))
                        .filter((item) => {
                            if (q === '') {
                                return true;
                            }

                            return [item.name, item.code]
                                .filter(Boolean)
                                .some((value) => String(value).toLowerCase().includes(q));
                        })
                        .slice()
                        .sort((a, b) => String(a.name).localeCompare(String(b.name), 'vi', { sensitivity: 'base' }))
                        .slice(0, 10);
                },
                addProfession(item) {
                    const id = Number(item.id);
                    if (! this.form.profession_ids.map(Number).includes(id)) {
                        this.form.profession_ids = [...this.form.profession_ids.map(Number), id];
                    }
                    this.professionQuery = '';
                },
                addFirstProfession() {
                    const first = this.professionSuggestions[0];
                    if (first) {
                        this.addProfession(first);
                    }
                },
                removeProfession(id) {
                    const target = Number(id);
                    this.form.profession_ids = this.form.profession_ids.map(Number).filter((value) => value !== target);
                },
                openCreate() {
                    this.clearFieldErrors();
                    this.resetProfessionPicker();
                    this.form = this.blankForm();
                    this.panel = 'create';
                },
                openEdit(item) {
                    this.clearFieldErrors();
                    this.resetProfessionPicker();
                    this.form = {
                        ...this.blankForm(),
                        ...item,
                        code: item.code || '',
                        description: item.description || '',
                        blueprint_id: item.blueprint_id ? String(item.blueprint_id) : '',
                        profession_ids: [...(item.profession_ids || [])].map(Number),
                    };
                    this.panel = 'edit';
                },
                closePanel() {
                    this.clearFieldErrors();
                    this.resetProfessionPicker();
                    this.panel = null;
                },
                init() {
                    window.addEventListener('popstate', () => {
                        const params = new URLSearchParams(window.location.search);
                        this.query = params.get('q') || '';
                        this.statusFilter = params.get('status') || 'all';
                        this.nameSort = params.get('dir') === 'desc' ? 'desc' : 'asc';
                        this.page = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);
                        this.fetchPage({ history: false });
                    });
                    if (config.reopenPanel) {
                        this.form = { ...this.blankForm(), ...(config.oldForm || {}) };
                        this.form.profession_ids = [...(this.form.profession_ids || [])].map(Number);
                        this.form.blueprint_id = this.form.blueprint_id ? String(this.form.blueprint_id) : '';
                        this.panel = config.reopenPanel;
                    } else if (config.openCreatePanel && this.canCreate) {
                        this.openCreate();
                    }
                    if (! this.focusId) return;
                    this.$nextTick(() => {
                        document.getElementById('node-exam-catalog-' + this.focusId)
                            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                },
            };
        }
    </script>
</x-layouts.admin>
