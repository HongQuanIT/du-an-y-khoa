@php
    /**
     * @var \Illuminate\Support\Collection<int, \Modules\QuestionBank\Models\Subject> $subjects
     * @var \Illuminate\Support\Collection<int, \Modules\QuestionBank\Models\OrganSystem> $organSystems
     * @var list<array{id: int, title: string, icon: string, hint: string}> $exams
     */
    $legacyBuilderPreferences = is_array($legacyBuilderPreferences ?? null) ? $legacyBuilderPreferences : [];
    $initialMode = old('mode', request('mode', 'study'));
    $initialSource = old('source', request('source', 'custom'));
    if (! in_array($initialSource, ['custom', 'weak_topics'], true)) {
        $initialSource = 'custom';
    }
    $initialAdaptiveFocus = old('adaptive_focus', request('adaptive_focus', 'balanced'));
    if (! in_array($initialAdaptiveFocus, ['weak_focus', 'balanced', 'retention'], true)) {
        $initialAdaptiveFocus = 'balanced';
    }
    $initialCountDefault = 0;
    $initialCountInput = old('count', request('count', $initialCountDefault));
    $initialCount = max(0, (int) $initialCountInput);
    $initialCountTouched = old('count') !== null || request()->has('count');
    $initialDifficultyInput = old(
        'difficulties',
        request('difficulties', old('difficulty', request('difficulty', []))),
    );
    $initialDifficulties = array_values(array_filter((array) $initialDifficultyInput));
    $initialStatusValues = array_values((array) old('question_statuses', request('question_statuses', [])));
    $initialFlaggedOnly = in_array('flagged', $initialStatusValues, true);
    $initialStatuses = array_values(array_filter($initialStatusValues, fn ($status) => $status !== 'flagged'));
    $initialStatusMode = old('question_status_mode', request('question_status_mode', 'latest'));
    $initialSavedOnly = (bool) old('saved_only', request()->boolean('saved_only'));
    $initialFolderIds = array_values(array_unique(array_map('intval', (array) old('folder_ids', request('folder_ids', [])))));
    $initialBlueprintId = old('exam_catalog_id', request('exam_catalog_id'));
    $initialOrganSystemIds = array_map('intval', (array) old('organ_system_ids', request('organ_system_ids', [])));
    $initialSubjectIds = array_map('intval', (array) old('subject_ids', request('subject_ids', [])));
    $initialLessonIds = array_map('intval', (array) old('lesson_ids', request('lesson_ids', [])));
    $sessionName = old('name', 'Phiên luyện từ ' . now()->translatedFormat('j M, H:i'));

    $statusOptions = [
        ['value' => 'unanswered', 'label' => 'Chưa trả lời', 'icon' => 'radio_button_unchecked'],
        ['value' => 'incorrect', 'label' => 'Làm sai', 'icon' => 'cancel'],
        ['value' => 'correct', 'label' => 'Làm đúng', 'icon' => 'check_circle'],
        ['value' => 'correct_with_hints', 'label' => 'Đúng có gợi ý', 'icon' => 'lightbulb'],
    ];
    $difficultyOptions = \App\Support\ScopeFilters::difficulties();

    $selectedOrganSystemIds = array_map('strval', $initialOrganSystemIds);
    $selectedSubjectIds = array_map('strval', $initialSubjectIds);
    $examTitles = collect($exams)->mapWithKeys(fn (array $exam) => [(int) $exam['id'] => $exam['title']])->all();
    $initialBlueprintName = $initialBlueprintId && isset($examTitles[(int) $initialBlueprintId])
        ? $examTitles[(int) $initialBlueprintId]
        : '';
@endphp

<x-layouts.app
    title="Tạo phiên luyện tập câu hỏi y khoa"
    description="Tạo phiên luyện tập câu hỏi y khoa theo kỳ thi, chủ đề, bài học và độ khó; chọn câu thủ công hoặc ôn tập theo tiến độ học của bạn.">
    <form method="POST" action="{{ route('qbank.store', absolute: false) }}" x-ref="builderForm"
        class="flex min-h-[calc(100vh-var(--spacing-header-height))] flex-col pb-24"
        x-data="{
            mode: {{ Illuminate\Support\Js::from($initialMode)->toHtml() }},
            source: {{ Illuminate\Support\Js::from($initialSource)->toHtml() }},
            adaptiveFocus: {{ Illuminate\Support\Js::from($initialAdaptiveFocus)->toHtml() }},
            count: {{ Illuminate\Support\Js::from($initialCount)->toHtml() }},
            difficulties: {{ Illuminate\Support\Js::from($initialDifficulties)->toHtml() }},
            difficultyLabels: {{ Illuminate\Support\Js::from(collect($difficultyOptions)->pluck('name', 'id')->all())->toHtml() }},
            difficultyOptionCount: {{ count($difficultyOptions) }},
            statuses: {{ Illuminate\Support\Js::from($initialStatuses)->toHtml() }},
            flaggedOnly: {{ Illuminate\Support\Js::from($initialFlaggedOnly)->toHtml() }},
            organSystemIds: {{ Illuminate\Support\Js::from($selectedOrganSystemIds)->toHtml() }},
            subjectIds: {{ Illuminate\Support\Js::from($selectedSubjectIds)->toHtml() }},
            searchExams: {{ Illuminate\Support\Js::from(collect($exams)->map(fn (array $exam): array => ['id' => (int) $exam['id'], 'name' => $exam['title']])->values()->all())->toHtml() }},
            searchOrganSystems: {{ Illuminate\Support\Js::from($organSystems->map(fn ($item): array => ['id' => (int) $item->id, 'name' => $item->name])->values()->all())->toHtml() }},
            searchSubjects: {{ Illuminate\Support\Js::from($subjects->map(fn ($item): array => ['id' => (int) $item->id, 'name' => $item->name])->values()->all())->toHtml() }},
            savedOnly: {{ Illuminate\Support\Js::from($initialSavedOnly)->toHtml() }},
            blueprintId: {{ Illuminate\Support\Js::from($initialBlueprintId ? (int) $initialBlueprintId : null)->toHtml() }},
            blueprintName: {{ Illuminate\Support\Js::from($initialBlueprintName)->toHtml() }},
            examTitles: {{ Illuminate\Support\Js::from($examTitles)->toHtml() }},
            blueprintScopes: {{ Illuminate\Support\Js::from($blueprintScopes)->toHtml() }},
            lessonIds: {{ Illuminate\Support\Js::from($initialLessonIds)->toHtml() }},
            lessonLabels: {},
            taxonomySearch: '',
            lessonResults: [],
            taxonomyUrls: {
                lessons: {{ Illuminate\Support\Js::from(route('qbank.taxonomy.lookups.lessons', absolute: false))->toHtml() }},
            },
            folderId: null,
            folderIds: {{ Illuminate\Support\Js::from($initialFolderIds)->toHtml() }},
            folderName: '',
            folders: {{ Illuminate\Support\Js::from($bookmarkFolders)->toHtml() }},
            activeFilter: null,
            filterSearch: '',
            filterSuggestionsOpen: false,
            filterSearchLessons: [],
            filterSearchLoading: false,
            filterSearchRequest: 0,
            sessionName: {{ Illuminate\Support\Js::from($sessionName)->toHtml() }},
            matching: null,
            counting: false,
            countRequest: 0,
            countTouched: {{ $initialCountTouched ? 'true' : 'false' }},
            questionStatusMode: {{ Illuminate\Support\Js::from($initialStatusMode)->toHtml() }},
            submitting: false,
            preferenceStorageKey: {{ Illuminate\Support\Js::from('qbank.session-builder.'.(int) auth()->id())->toHtml() }},
            restoreStoredPreferences: {{ ($restoreBuilderPreferences ?? true) ? 'true' : 'false' }},
            legacyBuilderPreferences: {{ Illuminate\Support\Js::from($legacyBuilderPreferences)->toHtml() }},
            countUrl: {{ Illuminate\Support\Js::from(route('qbank.count', absolute: false))->toHtml() }},
            csrf: {{ Illuminate\Support\Js::from(csrf_token())->toHtml() }},
            init() {
                this.restoreBuilderPreferences();
                if (this.source === 'weak_topics') {
                    this.difficulties = [];
                    this.statuses = [];
                    this.flaggedOnly = false;
                    this.lessonIds = [];
                    this.lessonLabels = {};
                    this.savedOnly = false;
                    this.folderId = null;
                    this.folderIds = [];
                    this.folderName = '';
                    if (!this.countTouched) this.count = 0;
                }
                if (this.blueprintId) this.pruneFiltersToBlueprint();
                this.$nextTick(() => this.refreshCount());
            },
            restoreBuilderPreferences() {
                if (!this.restoreStoredPreferences) return;

                let preferences = null;
                try {
                    const stored = localStorage.getItem(this.preferenceStorageKey);
                    preferences = stored ? JSON.parse(stored) : null;
                } catch (error) {
                    preferences = null;
                }

                const restoredFromBrowser = preferences && typeof preferences === 'object';
                if (!restoredFromBrowser) preferences = this.legacyBuilderPreferences;
                if (!preferences || typeof preferences !== 'object') return;

                if (['study', 'exam'].includes(preferences.mode)) this.mode = preferences.mode;
                if (['custom', 'weak_topics'].includes(preferences.source)) this.source = preferences.source;
                if (['weak_focus', 'balanced', 'retention'].includes(preferences.adaptive_focus)) {
                    this.adaptiveFocus = preferences.adaptive_focus;
                }
                if (Number.isFinite(Number(preferences.count))) {
                    this.count = Math.max(0, Number(preferences.count));
                    this.countTouched = true;
                }

                const statuses = Array.isArray(preferences.question_statuses) ? preferences.question_statuses : [];
                this.statuses = statuses.filter((status) => status !== 'flagged');
                this.flaggedOnly = statuses.includes('flagged');
                if (['all', 'latest'].includes(preferences.question_status_mode)) {
                    this.questionStatusMode = preferences.question_status_mode;
                }
                this.difficulties = Array.isArray(preferences.difficulties) ? preferences.difficulties : [];
                this.organSystemIds = Array.isArray(preferences.organ_system_ids) ? preferences.organ_system_ids.map(Number) : [];
                this.subjectIds = Array.isArray(preferences.subject_ids) ? preferences.subject_ids.map(Number) : [];
                this.lessonIds = Array.isArray(preferences.lesson_ids) ? preferences.lesson_ids.map(Number) : [];
                this.savedOnly = Boolean(preferences.saved_only);
                const availableFolderIds = new Set(this.folders.map((folder) => Number(folder.id)));
                this.folderIds = (Array.isArray(preferences.folder_ids) ? preferences.folder_ids : [])
                    .map(Number)
                    .filter((id) => availableFolderIds.has(id));
                this.savedOnly = this.savedOnly || this.folderIds.length > 0;
                this.folderId = this.folderIds[0] || null;
                this.folderName = this.folderIds.length === 1
                    ? this.folders.find((folder) => Number(folder.id) === this.folderId)?.name || ''
                    : (this.savedOnly ? 'Tất cả câu đã lưu' : '');
                this.blueprintId = preferences.exam_catalog_id ? Number(preferences.exam_catalog_id) : null;
                this.blueprintName = this.blueprintId ? this.examTitles[this.blueprintId] || '' : '';
                this.lessonLabels = {};

                if (!restoredFromBrowser) this.saveBuilderPreferences();
            },
            saveBuilderPreferences() {
                try {
                    localStorage.setItem(this.preferenceStorageKey, JSON.stringify({
                        mode: this.mode,
                        source: this.source,
                        adaptive_focus: this.adaptiveFocus,
                        count: Number(this.count) || 0,
                        exam_catalog_id: this.blueprintId,
                        organ_system_ids: this.organSystemIds,
                        subject_ids: this.subjectIds,
                        lesson_ids: this.lessonIds,
                        difficulties: this.difficulties,
                        question_statuses: [...this.statuses, ...(this.flaggedOnly ? ['flagged'] : [])],
                        question_status_mode: this.questionStatusMode,
                        saved_only: this.savedOnly,
                        folder_ids: this.folderIds,
                    }));
                } catch (error) {
                    // Browser storage may be unavailable; session creation still works.
                }
            },
            isAdaptive() {
                return this.source === 'weak_topics';
            },
            taxonomyLocked() {
                return this.savedOnly;
            },
            setAdaptiveFocus(next) {
                if (!['weak_focus', 'balanced', 'retention'].includes(next)) return;
                this.adaptiveFocus = next;
                this.saveBuilderPreferences();
            },
            adaptiveFocusLabel() {
                return ({
                    weak_focus: 'Điểm yếu',
                    balanced: 'Cân bằng',
                    retention: 'Củng cố',
                })[this.adaptiveFocus] || 'Cân bằng';
            },
            setSource(next) {
                if (this.source === next) return;
                this.source = next;
                this.activeFilter = null;
                if (next === 'weak_topics') {
                    // Adaptive keeps exam / hệ / môn; drops lesson + manual difficulty/status + saved.
                    this.difficulties = [];
                    this.statuses = [];
                    this.flaggedOnly = false;
                    this.lessonIds = [];
                    this.lessonLabels = {};
                    this.savedOnly = false;
                    this.folderId = null;
                    this.folderIds = [];
                    this.folderName = '';
                    if (!this.adaptiveFocus) this.adaptiveFocus = 'balanced';
                    // Adaptive: user must choose size — default 0 disables start.
                    this.count = 0;
                }
                this.countTouched = false;
                this.saveBuilderPreferences();
                this.$nextTick(() => this.refreshCount());
            },
            clearCustomFilters(refresh = true) {
                this.difficulties = [];
                this.statuses = [];
                this.flaggedOnly = false;
                this.organSystemIds = [];
                this.subjectIds = [];
                this.savedOnly = false;
                this.folderId = null;
                this.folderIds = [];
                this.folderName = '';
                this.lessonIds = [];
                this.lessonLabels = {};
                this.saveBuilderPreferences();
                if (refresh) this.$nextTick(() => this.refreshCount());
            },
            clearTaxonomySelection() {
                this.organSystemIds = [];
                this.subjectIds = [];
                this.lessonIds = [];
                this.lessonLabels = {};
            },
            selectAllSavedQuestions() {
                this.savedOnly = true;
                this.folderId = null;
                this.folderIds = [];
                this.folderName = 'Tất cả câu đã lưu';
                this.clearTaxonomySelection();
                this.blueprintId = null;
                this.blueprintName = '';
                this.saveBuilderPreferences();
                this.refreshCount();
            },
            toggleSavedFolder(folder) {
                const id = Number(folder.id);
                const selected = this.folderIds.map(Number);
                this.folderIds = selected.includes(id)
                    ? selected.filter((folderId) => folderId !== id)
                    : [...selected, id];
                this.savedOnly = this.folderIds.length > 0;
                this.folderId = this.folderIds[0] || null;
                this.folderName = this.folderIds.length === 1
                    ? this.folders.find((item) => Number(item.id) === Number(this.folderIds[0]))?.name || ''
                    : '';
                this.clearTaxonomySelection();
                this.blueprintId = null;
                this.blueprintName = '';
                this.saveBuilderPreferences();
                this.refreshCount();
            },
            savedFolderLabel() {
                if (!this.savedOnly) return 'Tất cả';
                if (this.folderIds.length === 0) return 'Tất cả câu đã lưu';
                if (this.folderIds.length === 1) return this.folderName || '1 bộ sưu tập';
                return `${this.folderIds.length} bộ sưu tập`;
            },
            canStart() {
                if (this.matching === null || this.matching === 0 || this.counting || this.submitting) return false;
                if (this.count < 1 || this.count > this.questionLimit()) return false;
                return true;
            },
            async refreshCount() {
                if (!this.$refs.builderForm) return;
                await this.$nextTick();
                const requestId = ++this.countRequest;
                this.counting = true;
                try {
                    const body = new FormData(this.$refs.builderForm);
                    // Count endpoint validates min:1; UI may show 0 — send a placeholder.
                    body.set('count', String(Math.max(1, Number(this.count) || 1)));
                    body.set('source', this.source);
                    body.set('saved_only', this.savedOnly ? '1' : '0');
                    body.delete('question_statuses[]');
                    this.statuses.forEach((status) => body.append('question_statuses[]', status));
                    if (!this.isAdaptive() && this.flaggedOnly) {
                        body.append('question_statuses[]', 'flagged');
                    }
                    body.delete('folder_id');
                    body.delete('folder_ids[]');
                    this.folderIds.forEach((id) => body.append('folder_ids[]', String(id)));
                    if (this.blueprintId) {
                        body.set('exam_catalog_id', String(this.blueprintId));
                    } else {
                        body.delete('exam_catalog_id');
                    }
                    body.delete('blueprint_id');
                    const response = await fetch(this.countUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    });
                    const payload = await response.json();
                    if (requestId !== this.countRequest) return;
                    if (!response.ok) {
                        const details = payload?.errors ? Object.values(payload.errors).flat() : [];
                        throw new Error(details[0] || payload?.error?.message || 'Không thể đếm câu hỏi.');
                    }
                    this.matching = Number(payload?.data?.count ?? 0);
                    if (this.matching === 0) {
                        this.count = 0;
                    }
                    this.syncQuestionCount();
                } catch (error) {
                    if (requestId !== this.countRequest) return;
                    this.matching = 0;
                } finally {
                    if (requestId === this.countRequest) this.counting = false;
                }
            },
            openFilter(filter) {
                if (this.isAdaptive() && ['difficulty', 'statuses', 'lessons'].includes(filter)) return;
                if (['systems', 'subjects', 'lessons'].includes(filter) && this.taxonomyLocked()) return;
                this.activeFilter = filter;
                this.filterSearch = '';
                this.taxonomySearch = '';
                if (filter === 'lessons') this.fetchLessons();
            },
            normalizedSearch(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLocaleLowerCase();
            },
            filterSearchItems(items) {
                const query = this.normalizedSearch(this.filterSearch.trim());
                if (!query) return [];
                return (items || []).filter((item) => this.normalizedSearch(item.name).includes(query));
            },
            filterSearchGroups() {
                return [
                    { id: 'exams', label: 'Kỳ thi', items: this.filterSearchItems(this.searchExams) },
                    { id: 'systems', label: 'Hệ cơ quan', items: this.filterSearchItems(this.searchOrganSystems) },
                    { id: 'subjects', label: 'Môn học', items: this.filterSearchItems(this.searchSubjects) },
                    { id: 'lessons', label: 'Bài học', items: this.filterSearchLessons },
                    { id: 'saved', label: 'Câu hỏi đã lưu', items: this.filterSearchItems(this.folders.map((folder) => ({ id: Number(folder.id), name: folder.name, items_count: folder.items_count }))) },
                ].filter((group) => group.items.length > 0 && !this.filterSuggestionDisabled(group.id));
            },
            filterSuggestionDisabled(type) {
                if (this.isAdaptive() && ['lessons', 'saved'].includes(type)) return true;
                if (this.taxonomyLocked() && ['exams', 'systems', 'subjects', 'lessons'].includes(type)) return true;
                return false;
            },
            filterSuggestionSelected(type, item) {
                const id = Number(item.id);
                if (type === 'exams') return Number(this.blueprintId) === id;
                if (type === 'systems') return this.organSystemIds.map(Number).includes(id);
                if (type === 'subjects') return this.subjectIds.map(Number).includes(id);
                if (type === 'lessons') return this.lessonIds.map(Number).includes(id);
                if (type === 'saved') return this.folderIds.map(Number).includes(id);
                return false;
            },
            filterSuggestionCount() {
                return (this.blueprintId ? 1 : 0)
                    + this.organSystemIds.length
                    + this.subjectIds.length
                    + this.lessonIds.length
                    + this.folderIds.length;
            },
            async updateFilterSuggestions() {
                const query = this.filterSearch.trim();
                const requestId = ++this.filterSearchRequest;
                this.filterSuggestionsOpen = query.length > 0;
                this.filterSearchLessons = [];
                if (query.length < 2 || this.filterSuggestionDisabled('lessons')) return;

                this.filterSearchLoading = true;
                try {
                    const params = new URLSearchParams({ q: query });
                    const response = await fetch(`${this.taxonomyUrls.lessons}?${params}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const payload = await response.json();
                    if (requestId !== this.filterSearchRequest) return;
                    this.filterSearchLessons = (payload.data || []).map((item) => ({
                        id: Number(item.id),
                        name: item.name,
                        context: [...(item.organ_system_names || []), ...(item.subject_names || [])].join(' · '),
                    }));
                } catch (error) {
                    if (requestId === this.filterSearchRequest) this.filterSearchLessons = [];
                } finally {
                    if (requestId === this.filterSearchRequest) this.filterSearchLoading = false;
                }
            },
            toggleFilterSuggestion(type, item) {
                const id = Number(item.id);
                if (type === 'exams') {
                    if (Number(this.blueprintId) === id) this.clearExam();
                    else this.selectExam(id, item.name);
                    return;
                }
                if (type === 'systems') {
                    this.organSystemIds = this.organSystemIds.map(Number).includes(id)
                        ? this.organSystemIds.map(Number).filter((value) => value !== id)
                        : [...this.organSystemIds.map(Number), id];
                    this.onTaxonomyAxisChange();
                    return;
                }
                if (type === 'subjects') {
                    this.subjectIds = this.subjectIds.map(Number).includes(id)
                        ? this.subjectIds.map(Number).filter((value) => value !== id)
                        : [...this.subjectIds.map(Number), id];
                    this.onTaxonomyAxisChange();
                    return;
                }
                if (type === 'lessons') {
                    this.toggleLesson({ id, name: item.name });
                    return;
                }
                if (type === 'saved') {
                    const folder = this.folders.find((candidate) => Number(candidate.id) === id);
                    if (folder) this.toggleSavedFolder(folder);
                }
            },
            clearFilterSearch() {
                this.filterSearch = '';
                this.filterSearchLessons = [];
                this.filterSuggestionsOpen = false;
                this.filterSearchRequest++;
            },
            async fetchLessons() {
                const q = this.taxonomySearch.trim();
                const params = new URLSearchParams();
                if (q.length >= 2) params.set('q', q);
                this.organSystemIds.forEach((id) => params.append('organ_system_ids[]', String(id)));
                this.subjectIds.forEach((id) => params.append('subject_ids[]', String(id)));
                const query = params.toString();
                const url = query ? `${this.taxonomyUrls.lessons}?${query}` : this.taxonomyUrls.lessons;
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                const rows = json.data ?? [];
                this.lessonResults = rows.filter((item) => this.isLessonAllowedForExam(item.id));
            },
            async onTaxonomyAxisChange() {
                // Hệ/Môn change: drop bài học no longer in cascade (server also ANDs).
                if (this.lessonIds.length && (this.organSystemIds.length || this.subjectIds.length)) {
                    await this.pruneLessonsToCascade();
                }
                if (this.activeFilter === 'lessons') await this.fetchLessons();
                await this.$nextTick();
                this.saveBuilderPreferences();
                this.refreshCount();
            },
            async pruneLessonsToCascade() {
                if (!this.lessonIds.length) return;
                const params = new URLSearchParams();
                this.organSystemIds.forEach((id) => params.append('organ_system_ids[]', String(id)));
                this.subjectIds.forEach((id) => params.append('subject_ids[]', String(id)));
                if (![...params.keys()].length) return;
                const res = await fetch(`${this.taxonomyUrls.lessons}?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await res.json();
                const allowed = new Set((json.data ?? []).map((item) => Number(item.id)));
                this.lessonIds = this.lessonIds.filter((id) => allowed.has(Number(id)));
                Object.keys(this.lessonLabels).forEach((id) => {
                    if (!allowed.has(Number(id))) delete this.lessonLabels[id];
                });
            },
            toggleLesson(item) {
                const idx = this.lessonIds.indexOf(item.id);
                if (idx >= 0) {
                    this.lessonIds.splice(idx, 1);
                    delete this.lessonLabels[item.id];
                } else {
                    this.lessonIds.push(item.id);
                    this.lessonLabels[item.id] = item.name;
                }
                this.saveBuilderPreferences();
                this.$nextTick(() => this.refreshCount());
            },
            lessonLabel() {
                if (!this.lessonIds.length) return 'Tất cả';
                return this.lessonIds.length + ' đã chọn';
            },
            organSystemLabel() {
                if (!this.organSystemIds.length) return 'Tất cả';
                return this.organSystemIds.length + ' đã chọn';
            },
            subjectLabel() {
                if (!this.subjectIds.length) return 'Tất cả';
                return this.subjectIds.length + ' đã chọn';
            },
            questionLimit() {
                return Math.max(0, Number(this.matching) || 0);
            },
            syncQuestionCount() {
                const limit = this.questionLimit();
                // Adaptive: keep default 0 until user edits; custom: suggest full pool.
                if (!this.countTouched) {
                    if (this.isAdaptive()) {
                        this.count = 0;
                        return;
                    }
                    this.count = limit >= 1 ? limit : 0;
                    return;
                }
                this.clampQuestionCount();
            },
            clampQuestionCount() {
                const limit = this.questionLimit();
                if (limit < 1) {
                    this.count = 0;
                    return;
                }
                const next = Number(this.count);
                if (!Number.isFinite(next) || next < 0) {
                    this.count = 0;
                    return;
                }
                // 0 is allowed (disables start); otherwise clamp to pool size.
                this.count = Math.min(limit, Math.floor(next));
            },
            examDurationLabel() {
                const seconds = Math.max(1, Number(this.count) || 1) * 90;
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                return remainingSeconds ? `${minutes} phút ${remainingSeconds} giây` : `${minutes} phút`;
            },
            clearOrganSystems() {
                this.organSystemIds = [];
                this.onTaxonomyAxisChange();
            },
            clearSubjects() {
                this.subjectIds = [];
                this.onTaxonomyAxisChange();
            },
            difficultyLabel() {
                if (!this.difficulties.length || this.difficulties.length === this.difficultyOptionCount) return 'Tất cả';
                if (this.difficulties.length > 1) return this.difficulties.length + ' đã chọn';
                return this.difficultyLabels[this.difficulties[0]];
            },
            examLabel() {
                if (this.blueprintName) return this.blueprintName;
                if (this.blueprintId && this.examTitles[this.blueprintId]) return this.examTitles[this.blueprintId];
                return this.blueprintId ? 'Đã chọn' : 'Tất cả';
            },
            currentBlueprintScope() {
                if (!this.blueprintId) return null;
                return this.blueprintScopes[this.blueprintId] || this.blueprintScopes[String(this.blueprintId)] || null;
            },
            /**
             * QBank practice never narrows hệ/môn/bài by a matrix.
             * Kỳ thi only filters questions tagged with that catalog.
             */
            activeTaxonomyScope() {
                return null;
            },
            isLessonAllowedForExam(lessonId) {
                const scope = this.activeTaxonomyScope();
                if (!scope) return true;
                const id = Number(lessonId);
                return (scope.lessonIds || []).some((item) => Number(item) === id);
            },
            isSystemAllowedForExam(organSystemId) {
                const scope = this.activeTaxonomyScope();
                if (!scope) return true;
                const id = Number(organSystemId);
                return (scope.organSystemIds || []).some((item) => Number(item) === id);
            },
            isSubjectAllowedForExam(subjectId) {
                const scope = this.activeTaxonomyScope();
                if (!scope) return true;
                const id = Number(subjectId);
                return (scope.subjectIds || []).some((item) => Number(item) === id);
            },
            pruneFiltersToBlueprint() {
                const scope = this.activeTaxonomyScope();
                // No exam → full bank; keep any current taxonomy picks.
                if (!scope) return;
                if (!(scope.lessonIds || []).length) {
                    this.clearTaxonomySelection();
                    return;
                }
                const allowedLessons = new Set((scope.lessonIds || []).map((id) => String(id)));
                const allowedSubjects = new Set((scope.subjectIds || []).map((id) => String(id)));
                const allowedSystems = new Set((scope.organSystemIds || []).map((id) => String(id)));
                this.organSystemIds = this.organSystemIds.filter((id) => allowedSystems.has(String(id)));
                this.subjectIds = this.subjectIds.filter((id) => allowedSubjects.has(String(id)));
                this.lessonIds = this.lessonIds.filter((id) => allowedLessons.has(String(id)));
                Object.keys(this.lessonLabels).forEach((id) => {
                    if (!allowedLessons.has(String(id))) delete this.lessonLabels[id];
                });
            },
            selectExam(id, title) {
                this.blueprintId = id;
                this.blueprintName = title;
                this.pruneFiltersToBlueprint();
                this.saveBuilderPreferences();
                this.$nextTick(() => this.refreshCount());
            },
            clearExam() {
                this.blueprintId = null;
                this.blueprintName = '';
                this.saveBuilderPreferences();
                this.$nextTick(() => this.refreshCount());
            },
            resetBuilder() {
                // Do not call form.reset(): radios and the session name have no
                // HTML default, so the browser clears them without Alpine writing
                // the same values back. The count request then fails validation.
                this.mode = 'study';
                this.source = 'custom';
                this.adaptiveFocus = 'balanced';
                this.countTouched = false;
                this.count = 0;
                this.difficulties = [];
                this.statuses = [];
                this.flaggedOnly = false;
                this.organSystemIds = [];
                this.subjectIds = [];
                this.savedOnly = false;
                this.folderId = null;
                this.folderIds = [];
                this.folderName = '';
                this.blueprintId = null;
                this.blueprintName = '';
                this.lessonIds = [];
                this.lessonLabels = {};
                this.sessionName = {{ Illuminate\Support\Js::from('Phiên luyện từ ' . now()->translatedFormat('j M, H:i'))->toHtml() }};
                this.filterSearch = '';
                this.activeFilter = null;
                try {
                    localStorage.removeItem(this.preferenceStorageKey);
                } catch (error) {
                    // Browser storage may be unavailable; reset the form anyway.
                }
                this.$nextTick(() => this.refreshCount());
            },
        }"
        @change.debounce.350ms="
            if (!$event.target.name || $event.target.name === 'name') return;
            saveBuilderPreferences();
            if ($event.target.name === 'count') return;
            if ($event.target.name === 'organ_system_ids[]' || $event.target.name === 'subject_ids[]') {
                onTaxonomyAxisChange();
                return;
            }
            refreshCount();
        "
        @input.debounce.500ms="if ($event.target.name && $event.target.name !== 'name') { saveBuilderPreferences(); if ($event.target.name !== 'count') refreshCount(); }"
        @keydown.escape.window="activeFilter = null"
        @submit="saveBuilderPreferences(); if (!canStart()) { $event.preventDefault(); return; } submitting = true">
        @csrf
        <input type="hidden" name="source" :value="source">
        <input type="hidden" name="adaptive_focus" :value="adaptiveFocus" :disabled="!isAdaptive()">
        <input type="hidden" name="exam_catalog_id" :value="blueprintId ?? ''" :disabled="!blueprintId">
        <input type="hidden" name="question_status_mode" :value="questionStatusMode">
        <input type="hidden" name="saved_only" :value="savedOnly ? '1' : '0'" :disabled="isAdaptive()">
        <input type="hidden" name="question_statuses[]" value="flagged" :disabled="isAdaptive() || !flaggedOnly">
        <template x-for="folderId in folderIds" :key="folderId">
            <input type="hidden" name="folder_ids[]" :value="folderId" :disabled="isAdaptive()">
        </template>
        <template x-for="id in lessonIds" :key="'lesson-' + id">
            <input type="hidden" name="lesson_ids[]" :value="id" :disabled="isAdaptive()">
        </template>

        <div class="mx-auto w-full max-w-[1440px] flex-1 overflow-y-auto p-4 pb-8 md:p-8">
            <div class="mb-8 flex items-center justify-between gap-4">
                <nav class="flex items-center gap-2 text-label-md text-on-surface-variant">
                    <a href="{{ route('qbank.index') }}" class="cursor-pointer transition-colors hover:text-primary">
                        Ngân hàng câu hỏi
                    </a>
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    <span class="font-bold text-primary">Tạo phiên luyện tập</span>
                </nav>
                <button type="button" @click="resetBuilder()"
                    class="flex items-center gap-2 text-sm font-bold tracking-wider text-on-surface-variant uppercase transition-colors hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                    Đặt lại
                </button>
            </div>
            @if ($needsProfession ?? false)
                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    Hồ sơ chưa có chức danh. Hãy chọn chức danh trước khi tạo phiên luyện.
                    <a href="{{ route('profile.show') }}" class="ml-1 font-semibold underline">Mở hồ sơ</a>
                </div>
            @endif

            <div class="mb-8">
                <h1 class="font-headline-sm text-on-surface">Tạo phiên luyện tập câu hỏi y khoa</h1>
                <p class="mt-1 max-w-2xl text-sm text-on-surface-variant">
                    Chọn kỳ thi, chủ đề, bài học và độ khó để tạo phiên luyện theo nhu cầu, hoặc để hệ thống chọn câu theo ma trận đề thi và tiến độ học của bạn.
                </p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2" role="radiogroup" aria-label="Loại phiên luyện tập">
                    <button type="button" @click="setSource('custom')"
                        role="radio" :aria-checked="source === 'custom'"
                        class="rounded-xl border p-5 text-left transition-all"
                        :class="source === 'custom'
                            ? 'border-2 border-primary bg-primary/5 shadow-sm'
                            : 'border-outline-variant bg-white hover:border-primary/40'">
                        <div class="flex items-start gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg"
                                :class="source === 'custom' ? 'bg-primary text-white' : 'bg-surface-container-low text-on-surface-variant'">
                                <span class="material-symbols-outlined text-[24px]">tune</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-bold text-on-surface">Phiên luyện tập theo bài</p>
                                    <span class="material-symbols-outlined text-primary" x-show="source === 'custom'" x-cloak>check_circle</span>
                                </div>
                                <p class="mt-1 text-sm leading-relaxed text-on-surface-variant">
                                    Luyện tập theo bài học với bộ lọc kỳ thi, chủ đề, bài học, độ khó và trạng thái câu hỏi.
                                </p>
                            </div>
                        </div>
                    </button>

                    <button type="button" @click="setSource('weak_topics')"
                        role="radio" :aria-checked="source === 'weak_topics'"
                        class="rounded-xl border p-5 text-left transition-all"
                        :class="source === 'weak_topics'
                            ? 'border-2 border-primary bg-primary/5 shadow-sm'
                            : 'border-outline-variant bg-white hover:border-primary/40'">
                        <div class="flex items-start gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg"
                                :class="source === 'weak_topics' ? 'bg-primary text-white' : 'bg-surface-container-low text-on-surface-variant'">
                                <span class="material-symbols-outlined text-[24px]">auto_awesome</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-bold text-on-surface">Phiên luyện tập thích ứng</p>
                                    <span class="material-symbols-outlined text-primary" x-show="source === 'weak_topics'" x-cloak>check_circle</span>
                                </div>
                                <p class="mt-1 text-sm leading-relaxed text-on-surface-variant">
                                    Hệ thống tự chọn câu theo tiến độ của bạn. Chọn hướng luyện — điểm yếu, cân bằng hoặc củng cố.
                                </p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-error/30 bg-error-container/40 p-4" role="alert">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 text-error">error</span>
                        <div>
                            <p class="font-bold text-on-error-container">Chưa thể tạo phiên luyện</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-body-sm text-on-error-container">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Shared builder layout (custom + adaptive) --}}
            <div class="grid grid-cols-12 gap-8">
                <section class="col-span-12 lg:col-span-7">
                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                        <div class="border-b border-outline-variant p-6">
                            <h2 class="font-headline-sm text-on-surface">Thiết lập chủ đề</h2>
                        </div>
                        <div class="space-y-6 p-6">
                            <div>
                                <p class="mb-3 text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">
                                    Tìm kiếm bộ lọc
                                </p>
                                <div class="relative" @click.outside="filterSuggestionsOpen = false">
                                    <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                                    <input type="search" x-model="filterSearch"
                                        @focus="if (filterSearch.trim()) filterSuggestionsOpen = true"
                                        @input.debounce.250ms="updateFilterSuggestions()"
                                        @keydown.escape.stop="filterSuggestionsOpen = false"
                                        @keydown.down.prevent="$refs.filterSuggestionList?.querySelector('button:not([disabled])')?.focus()"
                                        class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-11 pl-10 text-sm placeholder:italic focus:ring-2 focus:ring-primary"
                                        placeholder="Ví dụ: hệ cơ quan, chuyên khoa">
                                    <button type="button" x-show="filterSearch.length" x-cloak
                                        @click="clearFilterSearch(); $nextTick(() => $el.previousElementSibling.focus())"
                                        class="absolute top-1/2 right-3 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface"
                                        aria-label="Xóa nội dung tìm kiếm">
                                        <span class="material-symbols-outlined text-[20px]">close</span>
                                    </button>

                                    <div x-show="filterSuggestionsOpen && filterSearch.trim().length" x-cloak
                                        class="absolute top-[calc(100%+0.5rem)] right-0 left-0 z-40 overflow-hidden rounded-xl border border-outline-variant bg-white shadow-xl">
                                        <div class="flex items-center justify-between border-b border-outline-variant bg-surface-container-low px-4 py-3">
                                            <span class="text-xs font-bold tracking-wide text-on-surface-variant uppercase">Kết quả phù hợp</span>
                                            <span class="text-xs font-bold text-primary" x-text="filterSuggestionCount() + ' đã chọn'"></span>
                                        </div>

                                        <div x-ref="filterSuggestionList" class="custom-scrollbar max-h-80 overflow-y-auto p-2">
                                            <template x-for="group in filterSearchGroups()" :key="group.id">
                                                <section>
                                                    <h4 class="border-b border-outline-variant px-3 py-2 text-[11px] font-bold tracking-wider text-on-surface-variant uppercase"
                                                        x-text="group.label"></h4>
                                                    <template x-for="item in group.items" :key="group.id + '-' + item.id">
                                                        <button type="button"
                                                            @click="toggleFilterSuggestion(group.id, item)"
                                                            :aria-pressed="filterSuggestionSelected(group.id, item)"
                                                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none">
                                                            <span class="flex size-5 shrink-0 items-center justify-center rounded border-2 transition-colors"
                                                                :class="filterSuggestionSelected(group.id, item)
                                                                    ? 'border-primary bg-primary text-white'
                                                                    : 'border-outline bg-white text-transparent'">
                                                                <span class="material-symbols-outlined text-[15px] font-bold">check</span>
                                                            </span>
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate text-sm text-on-surface"
                                                                    :class="filterSuggestionSelected(group.id, item) && 'font-bold'"
                                                                    x-text="item.name"></span>
                                                                <span x-show="item.context || item.items_count !== undefined" x-cloak
                                                                    class="mt-0.5 block truncate text-[11px] text-on-surface-variant"
                                                                    x-text="item.context || (item.items_count + ' câu hỏi')"></span>
                                                            </span>
                                                        </button>
                                                    </template>
                                                </section>
                                            </template>

                                            <div x-show="filterSearchLoading" class="flex items-center justify-center gap-2 px-3 py-5 text-sm text-on-surface-variant">
                                                <span class="size-4 animate-spin rounded-full border-2 border-primary border-t-transparent"></span>
                                                Đang tìm bài học…
                                            </div>
                                            <p x-show="!filterSearchLoading && filterSearchGroups().length === 0"
                                                class="px-3 py-8 text-center text-sm text-on-surface-variant">
                                                Không tìm thấy bộ lọc phù hợp.
                                            </p>
                                        </div>

                                        <div class="flex justify-end border-t border-outline-variant bg-surface-container-lowest px-4 py-3">
                                            <button type="button" @click="filterSuggestionsOpen = false"
                                                class="text-sm font-bold text-primary hover:underline">
                                                Xong (<span x-text="filterSuggestionCount()"></span>)
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="-mx-6 space-y-0 border-t border-outline-variant">
                                <button type="button" @click="openFilter('exams')"
                                    :disabled="!isAdaptive() && savedOnly"
                                    :class="(!isAdaptive() && savedOnly) && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Kỳ thi</span>
                                    </span>
                                    <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                        x-text="examLabel()"></span>
                                </button>

                                <button type="button" @click="openFilter('systems')"
                                    :disabled="taxonomyLocked()"
                                    :class="taxonomyLocked() && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Hệ cơ quan</span>
                                    </span>
                                    <span class="text-sm text-on-surface-variant" x-text="organSystemLabel()"></span>
                                </button>

                                <button type="button" @click="openFilter('subjects')"
                                    :disabled="taxonomyLocked()"
                                    :class="taxonomyLocked() && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Môn học</span>
                                    </span>
                                    <span class="text-sm text-on-surface-variant" x-text="subjectLabel()"></span>
                                </button>

                                <div x-show="!isAdaptive()">
                                    @include('questionbank::partials.taxonomy-session-filter-rows')
                                </div>

                                @can('bookmark.view')
                                <button type="button" x-show="!isAdaptive()" @click="openFilter('saved')"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Câu hỏi đã lưu</span>
                                    </span>
                                    <span class="text-sm text-on-surface-variant" x-text="savedFolderLabel()"></span>
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>

                </section>

                <aside class="col-span-12 lg:col-span-5">
                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                        <div class="border-b border-outline-variant p-6">
                            <h2 class="font-headline-sm text-on-surface">Tiêu chí phiên luyện</h2>
                        </div>
                        <div class="space-y-8 p-6">
                            <div>
                                <label for="session-name" class="mb-3 block text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">
                                    Tên phiên luyện
                                </label>
                                <input id="session-name" type="text" name="name" maxlength="120"
                                    x-model="sessionName"
                                    class="w-full rounded-lg border border-outline-variant bg-white px-4 py-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary">
                            </div>

                            {{-- Custom: độ khó / trạng thái --}}
                            <div x-show="!isAdaptive()" class="-mx-6 space-y-0 border-y border-outline-variant">
                                <button type="button" @click="openFilter('difficulty')"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Độ khó</span>
                                    </span>
                                    <span class="rounded bg-error-container px-3 py-1 text-[12px] font-medium text-on-error-container"
                                        x-text="difficultyLabel()"></span>
                                </button>
                                <button type="button" @click="openFilter('statuses')"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Trạng thái</span>
                                    </span>
                                    <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                        x-text="statuses.length ? statuses.length + ' đã chọn' : 'Tất cả'"></span>
                                </button>
                                <label class="flex cursor-pointer items-center justify-between px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="font-medium">Chỉ câu hỏi đã gắn cờ</span>
                                    <span class="relative inline-flex h-6 w-12 shrink-0 items-center rounded-full transition-colors"
                                        :class="flaggedOnly ? 'bg-[#087f8c]' : 'bg-outline-variant'">
                                        <input type="checkbox" role="switch" aria-label="Chỉ câu hỏi đã gắn cờ"
                                            x-model="flaggedOnly" @change="refreshCount()"
                                            class="peer sr-only">
                                        <span class="absolute left-0.5 size-5 rounded-full bg-white shadow transition-transform"
                                            :class="flaggedOnly ? 'translate-x-6' : 'translate-x-0'"></span>
                                    </span>
                                </label>
                            </div>

                            {{-- Adaptive: 3 hướng luyện thay độ khó / trạng thái --}}
                            <div x-show="isAdaptive()" x-cloak class="-mx-6 space-y-0 border-y border-outline-variant">
                                <div class="border-b border-outline-variant px-6 py-4">
                                    <p class="text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">Hướng luyện</p>
                                    <p class="mt-1 text-xs text-on-surface-variant">
                                        Chọn mục tiêu phiên luyện. Hệ thống sẽ ưu tiên câu phù hợp với hướng bạn chọn.
                                    </p>
                                </div>
                                <div class="space-y-0" role="radiogroup" aria-label="Hướng luyện thích ứng">
                                    <template x-for="option in [
                                        { id: 'weak_focus', title: 'Điểm yếu', subtitle: 'Tập trung câu bạn hay trả lời sai', icon: 'target' },
                                        { id: 'balanced', title: 'Cân bằng', subtitle: 'Kết hợp điểm yếu và kiến thức lâu chưa ôn', icon: 'balance' },
                                        { id: 'retention', title: 'Củng cố', subtitle: 'Ôn lại kiến thức đã học nhưng lâu chưa gặp', icon: 'history_edu' },
                                    ]" :key="option.id">
                                        <button type="button"
                                            @click="setAdaptiveFocus(option.id)"
                                            role="radio"
                                            :aria-checked="adaptiveFocus === option.id"
                                            class="group flex w-full items-center gap-4 border-b border-outline-variant px-6 py-4 text-left transition-colors last:border-b-0 hover:bg-surface-container-lowest"
                                            :class="adaptiveFocus === option.id ? 'bg-primary/[0.04]' : ''">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg transition-colors"
                                                :class="adaptiveFocus === option.id
                                                    ? 'bg-primary text-white'
                                                    : 'bg-surface-container-low text-on-surface-variant group-hover:text-primary'">
                                                <span class="material-symbols-outlined text-[22px]" x-text="option.icon"></span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium text-on-surface" x-text="option.title"></span>
                                                <span class="mt-0.5 block text-xs text-on-surface-variant" x-text="option.subtitle"></span>
                                            </span>
                                            <span class="material-symbols-outlined text-[20px] text-primary"
                                                x-show="adaptiveFocus === option.id" x-cloak>check_circle</span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div x-show="mode === 'exam'" x-cloak
                                class="rounded-lg border border-primary/20 bg-primary/5 p-4">
                                <p class="text-[11px] font-bold tracking-widest text-primary uppercase">Thời gian thi tự động</p>
                                <p class="mt-1 text-sm font-medium text-on-surface">
                                    1 phút 30 giây mỗi câu · Tổng <strong x-text="examDurationLabel()"></strong>
                                </p>
                            </div>

                            <div>
                                <label for="question-count" class="mb-3 block text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">
                                    Số lượng câu hỏi
                                </label>
                                <div class="flex items-center gap-3">
                                    <input id="question-count" type="number" name="count" min="0" step="1"
                                        :max="Math.max(0, questionLimit())" x-model.number="count"
                                        :disabled="matching === 0 && !flaggedOnly"
                                        @input="countTouched = true; clampQuestionCount(); saveBuilderPreferences()"
                                        @change="countTouched = true; clampQuestionCount(); saveBuilderPreferences()"
                                        @blur="clampQuestionCount()"
                                        required
                                        class="w-20 rounded-lg border border-outline-variant py-2.5 text-center text-lg font-bold focus:ring-2 focus:ring-primary disabled:cursor-not-allowed disabled:opacity-60">
                                    <span class="text-lg font-medium text-on-surface-variant">
                                        / <span x-text="counting ? '…' : (matching ?? 0)"></span>
                                    </span>
                                </div>
                                <p x-show="isAdaptive()" x-cloak class="mt-2 text-xs text-on-surface-variant">
                                    Hệ thống lấy câu trong phạm vi đã chọn theo hướng
                                    <span class="font-semibold text-on-surface" x-text="adaptiveFocusLabel().toLowerCase()"></span>.
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <div class="fixed right-0 bottom-0 left-0 z-[45] flex items-center justify-center gap-4 border-t border-outline-variant bg-white p-4 md:left-sidebar-width md:gap-8">
            <div class="flex items-center gap-3 md:gap-4">
                <span class="hidden text-[11px] font-bold tracking-widest text-on-surface-variant uppercase sm:inline">Chế độ</span>
                <div class="flex rounded-lg border border-outline-variant bg-surface-container-low p-1">
                    <label class="cursor-pointer rounded-lg px-3 py-2 text-sm font-bold transition-all md:px-6"
                        :class="mode === 'study' ? 'border border-primary bg-white text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'">
                        <input type="radio" name="mode" value="study" x-model="mode" class="sr-only">
                        Chế độ học tập
                    </label>
                    @can('exam.take')
                    <label class="cursor-pointer rounded-lg px-3 py-2 text-sm font-bold transition-all md:px-6"
                        :class="mode === 'exam' ? 'border border-primary bg-white text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'">
                        <input type="radio" name="mode" value="exam" x-model="mode" class="sr-only">
                        Chế độ thi
                    </label>
                    @endcan
                </div>
            </div>
            @can('session.start')
            <button type="submit"
                :disabled="!canStart()"
                class="rounded-lg px-6 py-2.5 font-bold text-white transition-all md:px-12"
                :class="!canStart()
                    ? 'cursor-not-allowed bg-primary/30 opacity-70'
                    : 'bg-primary shadow-md hover:bg-primary/90'">
                <span x-text="submitting ? 'Đang tạo…' : 'Bắt đầu'"></span>
            </button>
            @endcan
        </div>

        <div x-show="activeFilter" x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            @click.self="activeFilter = null">
            <div class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-xl bg-white shadow-xl"
                :class="activeFilter === 'exams' ? 'max-w-3xl' : 'max-w-md'">
                <div class="flex items-center justify-between border-b border-outline-variant p-4">
                    <h3 class="font-headline-sm text-on-surface"
                        x-text="({
                            exams: 'Chọn kỳ thi',
                            systems: 'Hệ cơ quan',
                            subjects: 'Môn học',
                            difficulty: 'Độ khó',
                            statuses: 'Trạng thái',
                            lessons: 'Bài học',
                            saved: 'Câu hỏi đã lưu',
                        })[activeFilter] || 'Bộ lọc'"></h3>
                    <button type="button" @click="activeFilter = null"
                        class="rounded-full p-2 transition-colors hover:bg-surface-container" aria-label="Đóng">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="custom-scrollbar space-y-4 overflow-y-auto p-4">
                    <div x-show="activeFilter === 'exams'" class="space-y-4">
                        <p class="text-sm text-on-surface-variant" x-show="!isAdaptive()">
                            Không chọn kỳ thi: mọi câu đúng chức danh của bạn. Chọn kỳ thi: chỉ câu đã gắn kỳ thi đó. Hệ, môn và bài học không bị cắt theo ma trận.
                        </p>
                        <p class="text-sm text-on-surface-variant" x-show="isAdaptive()" x-cloak>
                            Không chọn kỳ thi: mọi câu đúng chức danh. Chọn kỳ thi: chỉ câu đã gắn kỳ thi đó.
                        </p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                            @forelse ($exams as $exam)
                                <button type="button" @click="selectExam({{ (int) $exam['id'] }}, {{ Illuminate\Support\Js::from($exam['title'])->toHtml() }})"
                                    class="relative cursor-pointer rounded-lg border p-4 text-left transition-colors"
                                    :class="blueprintId === {{ (int) $exam['id'] }}
                                        ? 'border-2 border-primary bg-[#f0fdfa]'
                                        : 'border-outline-variant bg-surface hover:border-primary/50'">
                                    <span class="absolute top-4 right-4 text-primary" x-show="blueprintId === {{ (int) $exam['id'] }}" x-cloak>
                                        <span class="material-symbols-outlined fill-1">check_circle</span>
                                    </span>
                                    <span class="material-symbols-outlined mb-2 text-3xl"
                                        :class="blueprintId === {{ (int) $exam['id'] }} ? 'text-primary' : 'text-on-surface-variant'">{{ $exam['icon'] }}</span>
                                    <span class="mb-1 block pr-7 font-label-md text-label-md text-on-surface">{{ $exam['title'] }}</span>
                                    <span class="block font-body-sm text-body-sm text-on-surface-variant">{{ $exam['hint'] }}</span>
                                </button>
                            @empty
                                <p class="col-span-full rounded-lg bg-surface-container-low p-4 text-sm text-on-surface-variant">
                                    @if ($needsProfession ?? false)
                                        Hãy chọn chức danh trên hồ sơ trước khi luyện theo kỳ thi.
                                    @else
                                        Chưa có kỳ thi cho chức danh của bạn.
                                    @endif
                                </p>
                            @endforelse
                        </div>
                    </div>

                    <div x-show="activeFilter === 'systems'" class="space-y-4">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                            <input type="search" x-model="filterSearch" placeholder="Tìm kiếm..."
                                class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
                        </div>

                        <button type="button"
                            @click="clearOrganSystems()"
                            class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
                            <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
                            <span class="block text-sm font-bold">Tất cả</span>
                        </button>

                        <div class="space-y-1">
                            @forelse ($organSystems as $topic)
                                <label data-search="{{ Str::lower($topic->name) }}"
                                    x-show="isSystemAllowedForExam({{ (int) $topic->id }}) && $el.dataset.search.includes(filterSearch.toLocaleLowerCase())"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox" name="organ_system_ids[]" value="{{ $topic->id }}" x-model="organSystemIds"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="text-sm">{{ $topic->name }}</span>
                                </label>
                            @empty
                                <p class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">Chưa có dữ liệu hệ cơ quan.</p>
                            @endforelse
                        </div>
                    </div>

                    <div x-show="activeFilter === 'subjects'" class="space-y-4">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                            <input type="search" x-model="filterSearch" placeholder="Tìm kiếm..."
                                class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
                        </div>

                        <button type="button"
                            @click="clearSubjects()"
                            class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
                            <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
                            <span class="block text-sm font-bold">Tất cả</span>
                        </button>

                        <div class="space-y-1">
                            @forelse ($subjects as $topic)
                                <label data-search="{{ Str::lower($topic->name) }}"
                                    x-show="isSubjectAllowedForExam({{ (int) $topic->id }}) && $el.dataset.search.includes(filterSearch.toLocaleLowerCase())"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox" name="subject_ids[]" value="{{ $topic->id }}" x-model="subjectIds"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="text-sm">{{ $topic->name }}</span>
                                </label>
                            @empty
                                <p class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">Chưa có dữ liệu môn học.</p>
                            @endforelse
                        </div>
                    </div>

                    <div x-show="activeFilter === 'difficulty'" class="space-y-1">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 hover:bg-surface-container-low">
                            <input type="checkbox" :checked="difficulties.length === 0"
                                @change="if ($event.target.checked) difficulties = []; $nextTick(() => refreshCount())"
                                class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                            <span class="text-sm font-medium">Tất cả độ khó</span>
                        </label>
                        @foreach ($difficultyOptions as $difficultyOption)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 hover:bg-surface-container-low">
                                <input type="checkbox" name="difficulties[]" value="{{ $difficultyOption['id'] }}" x-model="difficulties"
                                    class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="text-sm font-medium">{{ $difficultyOption['name'] }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div x-show="activeFilter === 'statuses'" class="space-y-1">
                        @foreach ($statusOptions as $status)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 hover:bg-surface-container-low">
                                <input type="checkbox" name="question_statuses[]" value="{{ $status['value'] }}"
                                    x-model="statuses"
                                    class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="material-symbols-outlined text-[19px] text-on-surface-variant">{{ $status['icon'] }}</span>
                                <span class="text-sm font-medium">{{ $status['label'] }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div x-show="activeFilter === 'saved'" class="space-y-4">
                        <button type="button" @click="selectAllSavedQuestions()"
                            class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
                            <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
                            <span>
                                <span class="block text-sm font-bold">Tất cả câu đã lưu</span>
                                <span class="block text-xs text-on-surface-variant">Bao gồm câu hỏi từ tất cả bộ sưu tập</span>
                            </span>
                        </button>

                        <div class="max-h-72 space-y-1 overflow-y-auto">
                            <template x-for="folder in folders" :key="folder.id">
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox"
                                        :checked="folderIds.map(Number).includes(Number(folder.id))"
                                        @change="toggleSavedFolder(folder)"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm" x-text="folder.name"></span>
                                        <span class="block text-xs text-on-surface-variant" x-text="folder.items_count + ' câu hỏi'"></span>
                                    </span>
                                </label>
                            </template>
                            <p x-show="folders.length === 0"
                                class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">
                                Chưa có bộ sưu tập câu hỏi đã lưu.
                            </p>
                        </div>
                    </div>

                    @include('questionbank::partials.taxonomy-session-filter-modals')
                </div>

                <div class="flex items-center justify-between border-t border-outline-variant bg-surface-container-lowest p-4">
                    <button type="button"
                        @click="activeFilter === 'exams' ? clearExam() : activeFilter === 'systems' ? clearOrganSystems() : activeFilter === 'subjects' ? clearSubjects() : activeFilter === 'difficulty' ? difficulties = [] : activeFilter === 'lessons' ? (lessonIds = [], lessonLabels = {}) : activeFilter === 'saved' ? (savedOnly = false, folderId = null, folderIds = [], folderName = '') : statuses = []; saveBuilderPreferences(); $nextTick(() => refreshCount())"
                        class="text-sm font-bold text-primary hover:underline">Đặt lại</button>
                    <button type="button" @click="activeFilter = null"
                        class="rounded-lg bg-primary px-8 py-2 font-bold text-white transition-opacity hover:opacity-90">Xong</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
