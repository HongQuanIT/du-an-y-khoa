@php
    $tabs = [
        ['key' => 'organ-systems', 'label' => 'Hệ cơ quan', 'icon' => 'account_tree', 'count' => $stats['organ_systems']],
        ['key' => 'subjects', 'label' => 'Môn học', 'icon' => 'folder', 'count' => $stats['subjects']],
        ['key' => 'lessons', 'label' => 'Bài học', 'icon' => 'menu_book', 'count' => $stats['lessons']],
    ];
    $requestedTab = old('_catalog', $filters['tab'] ?? request('tab'));
    $activeTab = in_array($requestedTab, ['organ-systems', 'subjects', 'lessons'], true)
        ? $requestedTab
        : 'organ-systems';
    $focusId = request()->filled('focus') ? (int) request('focus') : null;
@endphp

<x-layouts.admin title="Danh mục kiến thức">
    <x-admin.page-header title="Danh mục kiến thức"
        description="Chuẩn hóa hệ cơ quan, môn học và bài học để soạn câu hỏi, lọc ngân hàng và định hướng nội dung học.">
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'curriculum'])

    <x-admin.flash :except="['name', 'slug']" />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Hệ cơ quan" :value="number_format($stats['organ_systems'])" hint="Gắn trực tiếp vào bài học" icon="account_tree" />
        <x-admin.kpi-card label="Môn học" :value="number_format($stats['subjects'])" hint="Gắn trực tiếp vào bài học" icon="folder" />
        <x-admin.kpi-card label="Bài học" :value="number_format($stats['lessons'])" hint="Bài học thuộc môn học, hệ cơ quan" icon="menu_book" />
    </div>

    <div>
        <div class="mb-5 inline-flex rounded-xl border border-outline-variant bg-surface p-1">
            @foreach ($tabs as $tab)
                <a href="{{ route('admin.curriculum.index', ['tab' => $tab['key']]) }}"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 font-label-sm transition-colors {{ $activeTab === $tab['key']
                        ? 'bg-primary-container text-on-primary-container font-semibold'
                        : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
                    {{ $tab['label'] }}
                    <span class="rounded-md bg-surface-container px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-on-surface-variant">{{ number_format($tab['count']) }}</span>
                </a>
            @endforeach
        </div>

        @if ($activeTab === 'organ-systems')
            @include('admin::curriculum._organ-systems')
        @elseif ($activeTab === 'subjects')
            @include('admin::curriculum._subjects')
        @else
            @include('admin::curriculum._lessons')
        @endif
    </div>

    <script>
        function taxonomyCatalog(config) {
            return {
                catalogKey: config.key,
                indexUrl: config.indexUrl,
                items: config.items,
                focusId: config.focusId,
                storeUrl: config.storeUrl,
                canCreate: config.canCreate,
                canUpdate: config.canUpdate,
                canDelete: config.canDelete,
                query: config.query || '',
                statusFilter: config.status || 'all',
                nameSort: config.dir === 'desc' ? 'desc' : 'asc',
                filterSubjectIds: (config.subjectIds || []).map(Number).filter((id) => id > 0),
                filterOrganSystemIds: (config.organSystemIds || []).map(Number).filter((id) => id > 0),
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
                subjects: config.subjects || [],
                organSystems: config.organSystems || [],
                lessons: config.lessons || [],
                manageLessons: Boolean(config.manageLessons),
                canCreateLesson: Boolean(config.canCreateLesson),
                subjectLessonQuery: '',
                subjectLessonOpen: false,
                subjectLessonBusy: false,
                subjectLessonError: '',
                openCreatePanel: Boolean(config.openCreatePanel),
                subjectQuery: '',
                organSystemQuery: '',
                subjectOpen: false,
                organSystemOpen: false,
                filterSubjectQuery: '',
                filterOrganSystemQuery: '',
                filterSubjectOpen: false,
                filterOrganSystemOpen: false,
                form: { name: '', slug: '', description: '', status: 'active', subject_ids: [], organ_system_ids: [], lessons: [] },
                fieldErrors: { name: config.fieldErrors?.name || '', slug: config.fieldErrors?.slug || '' },
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
                    for (let n = start; n <= end; n++) {
                        pages.push(n);
                    }
                    return pages;
                },
                get pageRangeLabel() {
                    if (this.total === 0) {
                        return '0 mục';
                    }
                    return (this.from || 0) + '–' + (this.to || 0) + ' / ' + this.total + ' mục';
                },
                catalogUrl(params) {
                    const search = new URLSearchParams();
                    search.set('tab', this.catalogKey);
                    if (params.q) search.set('q', params.q);
                    if (params.status && params.status !== 'all') search.set('status', params.status);
                    if (params.dir === 'desc') search.set('dir', 'desc');
                    if (params.page > 1) search.set('page', String(params.page));
                    (params.subject_ids || []).forEach((id) => search.append('subject_ids[]', String(id)));
                    (params.organ_system_ids || []).forEach((id) => search.append('organ_system_ids[]', String(id)));
                    return this.indexUrl + '?' + search.toString();
                },
                currentParams() {
                    return {
                        q: this.query.trim(),
                        status: this.statusFilter,
                        dir: this.nameSort,
                        page: this.page,
                        subject_ids: [...this.filterSubjectIds],
                        organ_system_ids: [...this.filterOrganSystemIds],
                    };
                },
                applySearch() {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => this.fetchPage({ resetPage: true, history: 'replace' }), 400);
                },
                changeFilter() {
                    this.fetchPage({ resetPage: true, history: 'replace' });
                },
                toggleNameSort() {
                    this.nameSort = this.nameSort === 'asc' ? 'desc' : 'asc';
                    this.fetchPage({ resetPage: true, history: 'push' });
                },
                goToPage(page) {
                    this.page = page;
                    this.fetchPage({ history: 'push' });
                },
                applyUrl(search) {
                    const params = new URLSearchParams(search);
                    this.query = params.get('q') || '';
                    this.statusFilter = params.get('status') || 'all';
                    this.nameSort = params.get('dir') === 'desc' ? 'desc' : 'asc';
                    this.page = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);
                    this.filterSubjectIds = this.parseIdList(params, 'subject_ids');
                    this.filterOrganSystemIds = this.parseIdList(params, 'organ_system_ids');
                },
                async fetchPage(options = {}) {
                    if (options.resetPage) {
                        this.page = 1;
                    }
                    const url = this.catalogUrl(this.currentParams());
                    if (options.history === 'push') {
                        history.pushState({ catalog: this.catalogKey }, '', url);
                    } else if (options.history !== false) {
                        history.replaceState({ catalog: this.catalogKey }, '', url);
                    }
                    this.loading = true;
                    this.abort?.abort();
                    this.abort = new AbortController();
                    try {
                        const response = await fetch(url, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            signal: this.abort.signal,
                        });
                        if (! response.ok) {
                            return;
                        }
                        const json = await response.json();
                        this.items = json.data || [];
                        this.total = Number(json.meta?.total || 0);
                        this.page = Number(json.meta?.page || 1);
                        this.lastPage = Number(json.meta?.last_page || 1);
                        this.from = json.meta?.from || 0;
                        this.to = json.meta?.to || 0;
                    } catch (error) {
                        if (error?.name !== 'AbortError') {
                            console.error(error);
                        }
                    } finally {
                        this.loading = false;
                    }
                },
                linkField(kind) {
                    return kind === 'subject' ? 'subject_ids' : 'organ_system_ids';
                },
                linkOptions(kind) {
                    return kind === 'subject' ? this.subjects : this.organSystems;
                },
                linkQuery(kind) {
                    return kind === 'subject' ? this.subjectQuery : this.organSystemQuery;
                },
                linkLabel(kind, id) {
                    const needle = Number(id);
                    return this.linkOptions(kind).find((item) => Number(item.id) === needle)?.name || ('#' + id);
                },
                get subjectSuggestions() {
                    return this.linkSuggestions('subject');
                },
                get organSystemSuggestions() {
                    return this.linkSuggestions('organSystem');
                },
                get filterSubjectSuggestions() {
                    return this.linkSuggestions('subject', this.filterSubjectIds, this.filterSubjectQuery);
                },
                get filterOrganSystemSuggestions() {
                    return this.linkSuggestions('organSystem', this.filterOrganSystemIds, this.filterOrganSystemQuery);
                },
                filterField(kind) {
                    return kind === 'subject' ? 'filterSubjectIds' : 'filterOrganSystemIds';
                },
                openFilterPicker(kind) {
                    this.filterSubjectOpen = kind === 'subject';
                    this.filterOrganSystemOpen = kind === 'organSystem';
                },
                addFilterLink(kind, item) {
                    const field = this.filterField(kind);
                    const current = this[field] || [];
                    const id = Number(item.id);
                    if (! current.map(Number).includes(id)) {
                        this[field] = [...current, id];
                    }
                    if (kind === 'subject') {
                        this.filterSubjectQuery = '';
                    } else {
                        this.filterOrganSystemQuery = '';
                    }
                    this.changeFilter();
                },
                addFirstFilterSuggestion(kind) {
                    const first = kind === 'subject'
                        ? this.filterSubjectSuggestions[0]
                        : this.filterOrganSystemSuggestions[0];
                    if (first) {
                        this.addFilterLink(kind, first);
                    }
                },
                removeFilterLink(kind, id) {
                    const field = this.filterField(kind);
                    this[field] = (this[field] || []).filter((value) => Number(value) !== Number(id));
                    this.changeFilter();
                },
                parseIdList(params, key) {
                    return [...new Set([
                        ...params.getAll(key + '[]'),
                        ...params.getAll(key),
                    ].flatMap((value) => String(value).split(',')).map((value) => parseInt(value, 10)).filter((id) => id > 0))];
                },
                linkSuggestions(kind, selectedIds = null, query = null) {
                    const q = (query ?? this.linkQuery(kind)).trim().toLowerCase();
                    const selected = (selectedIds ?? (this.form[this.linkField(kind)] || [])).map(Number);

                    return this.linkOptions(kind)
                        .filter((item) => ! selected.includes(Number(item.id)))
                        .filter((item) => {
                            if (q === '') {
                                return true;
                            }

                            return [item.name, item.slug]
                                .filter(Boolean)
                                .some((value) => String(value).toLowerCase().includes(q));
                        })
                        .slice()
                        .sort((a, b) => String(a.name).localeCompare(String(b.name), 'vi', { sensitivity: 'base' }))
                        .slice(0, 10);
                },
                openLinkPicker(kind) {
                    this.subjectOpen = kind === 'subject';
                    this.organSystemOpen = kind === 'organSystem';
                },
                addLink(kind, item) {
                    const field = this.linkField(kind);
                    const current = this.form[field] || [];
                    const id = Number(item.id);
                    if (! current.map(Number).includes(id)) {
                        this.form[field] = [...current, id];
                    }
                    if (kind === 'subject') {
                        this.subjectQuery = '';
                    } else {
                        this.organSystemQuery = '';
                    }
                },
                addFirstSuggestion(kind) {
                    const first = this.linkSuggestions(kind)[0];
                    if (first) {
                        this.addLink(kind, first);
                    }
                },
                removeLink(kind, id) {
                    const field = this.linkField(kind);
                    this.form[field] = (this.form[field] || []).filter((value) => Number(value) !== Number(id));
                },
                resetLinkPickers() {
                    this.subjectQuery = '';
                    this.organSystemQuery = '';
                    this.subjectOpen = false;
                    this.organSystemOpen = false;
                    this.subjectLessonQuery = '';
                    this.subjectLessonOpen = false;
                    this.subjectLessonError = '';
                },
                blankForm() {
                    return {
                        name: '',
                        slug: '',
                        description: '',
                        status: 'active',
                        subject_ids: [],
                        organ_system_ids: [],
                        lessons: [],
                        attach_lessons_url: '',
                        create_lesson_url: '',
                    };
                },
                clearFieldErrors() {
                    this.fieldErrors = { name: '', slug: '' };
                },
                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]')?.content || '';
                },
                get subjectLessonSuggestions() {
                    const q = this.subjectLessonQuery.trim().toLowerCase();
                    const linked = (this.form.lessons || []).map((lesson) => Number(lesson.id));

                    return (this.lessons || [])
                        .filter((lesson) => ! linked.includes(Number(lesson.id)))
                        .filter((lesson) => {
                            if (q === '') {
                                return true;
                            }

                            return [lesson.name, lesson.slug]
                                .filter(Boolean)
                                .some((value) => String(value).toLowerCase().includes(q));
                        })
                        .slice()
                        .sort((a, b) => String(a.name).localeCompare(String(b.name), 'vi', { sensitivity: 'base' }))
                        .slice(0, 10);
                },
                attachFirstSubjectLessonSuggestion() {
                    const first = this.subjectLessonSuggestions[0];
                    if (first) {
                        this.attachSubjectLesson(first);
                    }
                },
                applySubjectLessonsPayload(json) {
                    const lessons = json.lessons || [];
                    this.form.lessons = lessons;
                    const count = Number(json.lessons_count ?? lessons.length);
                    this.form.lessons_count = count;
                    const item = this.items.find((row) => Number(row.id) === Number(this.form.id));
                    if (item) {
                        item.lessons = lessons;
                        item.lessons_count = count;
                    }
                },
                async attachSubjectLesson(lesson) {
                    if (! this.canUpdate || ! this.form.attach_lessons_url || this.subjectLessonBusy) {
                        return;
                    }
                    this.subjectLessonBusy = true;
                    this.subjectLessonError = '';
                    try {
                        const response = await fetch(this.form.attach_lessons_url, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ lesson_id: Number(lesson.id) }),
                        });
                        const json = await response.json().catch(() => ({}));
                        if (! response.ok) {
                            this.subjectLessonError = json.message || 'Không gắn được bài học.';
                            return;
                        }
                        this.applySubjectLessonsPayload(json);
                        this.subjectLessonQuery = '';
                        this.subjectLessonOpen = false;
                    } catch (error) {
                        console.error(error);
                        this.subjectLessonError = 'Không gắn được bài học.';
                    } finally {
                        this.subjectLessonBusy = false;
                    }
                },
                async detachSubjectLesson(lesson) {
                    if (! this.canUpdate || ! lesson?.detach_url || this.subjectLessonBusy) {
                        return;
                    }
                    this.subjectLessonBusy = true;
                    this.subjectLessonError = '';
                    try {
                        const response = await fetch(lesson.detach_url, {
                            method: 'DELETE',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });
                        const json = await response.json().catch(() => ({}));
                        if (! response.ok) {
                            this.subjectLessonError = json.message || 'Không gỡ được bài học.';
                            return;
                        }
                        this.applySubjectLessonsPayload(json);
                    } catch (error) {
                        console.error(error);
                        this.subjectLessonError = 'Không gỡ được bài học.';
                    } finally {
                        this.subjectLessonBusy = false;
                    }
                },
                openCreate() {
                    this.resetLinkPickers();
                    this.clearFieldErrors();
                    this.form = this.blankForm();
                    if (this.filterSubjectIds?.length) {
                        this.form.subject_ids = [...this.filterSubjectIds];
                    }
                    if (this.filterOrganSystemIds?.length) {
                        this.form.organ_system_ids = [...this.filterOrganSystemIds];
                    }
                    this.panel = 'create';
                },
                openEdit(item) {
                    this.resetLinkPickers();
                    this.clearFieldErrors();
                    this.form = {
                        ...this.blankForm(),
                        ...item,
                        description: item.description || '',
                        subject_ids: [...(item.subject_ids || [])],
                        organ_system_ids: [...(item.organ_system_ids || [])],
                        lessons: [...(item.lessons || [])],
                    };
                    this.panel = 'edit';
                },
                closePanel() {
                    this.resetLinkPickers();
                    this.clearFieldErrors();
                    this.panel = null;
                },
                init() {
                    window.addEventListener('popstate', () => {
                        this.applyUrl(window.location.search);
                        this.fetchPage({ history: false });
                    });
                    if (config.reopenPanel) {
                        this.form = { ...this.blankForm(), ...(config.oldForm || {}) };
                        this.panel = config.reopenPanel;
                    } else if (this.openCreatePanel) {
                        this.openCreate();
                    }
                    if (! this.focusId) {
                        return;
                    }
                    this.$nextTick(() => {
                        const el = document.getElementById('node-' + this.catalogKey + '-' + this.focusId);
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                },
            };
        }
    </script>
</x-layouts.admin>
