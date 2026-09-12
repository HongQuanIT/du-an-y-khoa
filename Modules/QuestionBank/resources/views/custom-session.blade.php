@php
    /**
     * @var \Illuminate\Support\Collection<int, \Modules\QuestionBank\Models\Subject> $subjects
     * @var \Illuminate\Support\Collection<int, \Modules\QuestionBank\Models\OrganSystem> $organSystems
     * @var list<array{id: int, title: string, icon: string, hint: string}> $exams
     */
    $initialMode = old('mode', request('mode', 'study'));
    $initialSource = old('source', request('source', 'custom'));
    if (! in_array($initialSource, ['custom', 'weak_topics'], true)) {
        $initialSource = 'custom';
    }
    $initialAdaptiveFocus = old('adaptive_focus', request('adaptive_focus', 'balanced'));
    if (! in_array($initialAdaptiveFocus, ['weak_focus', 'balanced', 'retention'], true)) {
        $initialAdaptiveFocus = 'balanced';
    }
    $initialCount = max(1, (int) old('count', 1));
    $initialDifficultyInput = old(
        'difficulties',
        request('difficulties', old('difficulty', request('difficulty', []))),
    );
    $initialDifficulties = array_values(array_filter((array) $initialDifficultyInput));
    $initialStatuses = array_values((array) old('question_statuses', request('question_statuses', [])));
    $initialStatusMode = old('question_status_mode', request('question_status_mode', 'latest'));
    $initialSavedOnly = (bool) old('saved_only', request()->boolean('saved_only'));
    $initialBlueprintId = old('blueprint_id', request('blueprint_id'));
    $initialOrganSystemIds = array_map('intval', (array) old('organ_system_ids', request('organ_system_ids', [])));
    $initialSubjectIds = array_map('intval', (array) old('subject_ids', request('subject_ids', [])));
    $initialLessonIds = array_map('intval', (array) old('lesson_ids', request('lesson_ids', [])));
    $sessionName = 'Phiên luyện từ ' . now()->translatedFormat('j M, H:i');

    $statusOptions = [
        ['value' => 'unanswered', 'label' => 'Chưa trả lời', 'icon' => 'radio_button_unchecked'],
        ['value' => 'incorrect', 'label' => 'Làm sai', 'icon' => 'cancel'],
        ['value' => 'correct', 'label' => 'Làm đúng', 'icon' => 'check_circle'],
        ['value' => 'correct_with_hints', 'label' => 'Đúng có gợi ý', 'icon' => 'lightbulb'],
        ['value' => 'omitted', 'label' => 'Bỏ qua', 'icon' => 'remove_circle'],
        ['value' => 'marked', 'label' => 'Đã đánh dấu', 'icon' => 'folder_managed'],
    ];
    $difficultyOptions = \App\Support\ScopeFilters::difficulties();

    $selectedOrganSystemIds = array_map('strval', $initialOrganSystemIds);
    $selectedSubjectIds = array_map('strval', $initialSubjectIds);
    $examTitles = collect($exams)->mapWithKeys(fn (array $exam) => [(int) $exam['id'] => $exam['title']])->all();
    $initialBlueprintName = $initialBlueprintId && isset($examTitles[(int) $initialBlueprintId])
        ? $examTitles[(int) $initialBlueprintId]
        : '';
@endphp

<x-layouts.app title="Tạo phiên luyện tập">
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
            organSystemIds: {{ Illuminate\Support\Js::from($selectedOrganSystemIds)->toHtml() }},
            subjectIds: {{ Illuminate\Support\Js::from($selectedSubjectIds)->toHtml() }},
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
            folderName: '',
            foldersModalOpen: false,
            folders: {{ Illuminate\Support\Js::from($bookmarkFolders)->toHtml() }},
            activeFilter: null,
            filterSearch: '',
            matching: null,
            counting: false,
            countRequest: 0,
            countTouched: {{ old('count') !== null ? 'true' : 'false' }},
            submitting: false,
            countUrl: {{ Illuminate\Support\Js::from(route('qbank.count', absolute: false))->toHtml() }},
            csrf: {{ Illuminate\Support\Js::from(csrf_token())->toHtml() }},
            init() {
                if (this.source === 'weak_topics') {
                    this.difficulties = [];
                    this.statuses = [];
                    this.lessonIds = [];
                    this.lessonLabels = {};
                    this.savedOnly = false;
                    this.folderId = null;
                    this.folderName = '';
                }
                if (this.blueprintId) this.pruneFiltersToBlueprint();
                this.$nextTick(() => this.refreshCount());
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
                    this.lessonIds = [];
                    this.lessonLabels = {};
                    this.savedOnly = false;
                    this.folderId = null;
                    this.folderName = '';
                    if (!this.adaptiveFocus) this.adaptiveFocus = 'balanced';
                }
                this.countTouched = false;
                this.$nextTick(() => this.refreshCount());
            },
            clearCustomFilters(refresh = true) {
                this.difficulties = [];
                this.statuses = [];
                this.organSystemIds = [];
                this.subjectIds = [];
                this.savedOnly = false;
                this.folderId = null;
                this.folderName = '';
                this.lessonIds = [];
                this.lessonLabels = {};
                if (refresh) this.$nextTick(() => this.refreshCount());
            },
            clearTaxonomySelection() {
                this.organSystemIds = [];
                this.subjectIds = [];
                this.lessonIds = [];
                this.lessonLabels = {};
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
                    if (!body.has('count')) {
                        body.set('count', String(Math.max(1, Number(this.count) || 1)));
                    }
                    body.set('source', this.source);
                    body.set('saved_only', this.savedOnly ? '1' : '0');
                    body.set('folder_id', this.folderId ? String(this.folderId) : '');
                    if (this.blueprintId) {
                        body.set('blueprint_id', String(this.blueprintId));
                    } else {
                        body.delete('blueprint_id');
                    }
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
            async fetchLessons() {
                const q = this.taxonomySearch.trim();
                const params = new URLSearchParams();
                if (q.length >= 2) params.set('q', q);
                const query = params.toString();
                const url = query ? `${this.taxonomyUrls.lessons}?${query}` : this.taxonomyUrls.lessons;
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                const rows = json.data ?? [];
                this.lessonResults = rows.filter((item) => this.isLessonAllowedForExam(item.id));
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
                if (!this.countTouched && limit >= 1) {
                    this.count = limit;
                    return;
                }
                this.clampQuestionCount();
            },
            clampQuestionCount() {
                const limit = this.questionLimit();
                if (limit < 1) {
                    this.count = 1;
                    return;
                }
                const next = Number(this.count);
                if (!Number.isFinite(next) || next < 1) {
                    this.count = 1;
                    return;
                }
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
                this.$nextTick(() => this.refreshCount());
            },
            clearSubjects() {
                this.subjectIds = [];
                this.$nextTick(() => this.refreshCount());
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
             * Taxonomy picker scope:
             * - no exam → unrestricted (full QBank)
             * - exam selected → that exam matrix only
             */
            activeTaxonomyScope() {
                if (!this.blueprintId) return null;
                return this.currentBlueprintScope() || { lessonIds: [], subjectIds: [], organSystemIds: [] };
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
                this.$nextTick(() => this.refreshCount());
            },
            clearExam() {
                this.blueprintId = null;
                this.blueprintName = '';
                this.$nextTick(() => this.refreshCount());
            },
            resetBuilder() {
                this.$refs.builderForm.reset();
                this.mode = 'study';
                this.source = 'custom';
                this.adaptiveFocus = 'balanced';
                this.countTouched = false;
                this.count = 1;
                this.difficulties = [];
                this.statuses = [];
                this.organSystemIds = [];
                this.subjectIds = [];
                this.savedOnly = false;
                this.folderId = null;
                this.folderName = '';
                this.blueprintId = null;
                this.blueprintName = '';
                this.lessonIds = [];
                this.lessonLabels = {};
                this.activeFilter = null;
                this.$nextTick(() => this.refreshCount());
            },
        }"
        @change.debounce.350ms="if ($event.target.name && $event.target.name !== 'count') refreshCount()"
        @input.debounce.500ms="if ($event.target.name && $event.target.name !== 'count') refreshCount()"
        @keydown.escape.window="activeFilter = null"
        @submit="if (!canStart()) { $event.preventDefault(); return; } submitting = true">
        @csrf
        <input type="hidden" name="source" :value="source">
        <input type="hidden" name="adaptive_focus" :value="adaptiveFocus" :disabled="!isAdaptive()">
        <input type="hidden" name="blueprint_id" :value="blueprintId ?? ''" :disabled="!blueprintId">
        <input type="hidden" name="question_status_mode" value="{{ $initialStatusMode }}">
        <input type="hidden" name="saved_only" :value="savedOnly ? '1' : '0'" :disabled="isAdaptive()">
        <input type="hidden" name="folder_id" :value="folderId ?? ''" :disabled="isAdaptive()">
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

            <div class="mb-8">
                <h1 class="font-headline-sm text-on-surface">Chọn loại phiên luyện</h1>
                <p class="mt-1 max-w-2xl text-sm text-on-surface-variant">
                    Tuỳ chỉnh bộ lọc thủ công, hoặc để hệ thống chọn câu theo ma trận đề thi và sức học của bạn.
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
                                <div class="relative">
                                    <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                                    <input type="search" x-model="filterSearch"
                                        class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm placeholder:italic focus:ring-2 focus:ring-primary"
                                        placeholder="Ví dụ: hệ cơ quan, chuyên khoa">
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

                                <button type="button" x-show="!isAdaptive()" @click="foldersModalOpen = true"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Câu hỏi đã lưu</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span class="text-sm font-semibold"
                                            :class="savedOnly ? 'text-primary' : 'text-on-surface-variant'"
                                            x-text="folderId ? folderName : (savedOnly ? 'Chỉ câu đã lưu' : 'Tất cả')"></span>
                                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant">chevron_right</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="foldersModalOpen" x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        @keydown.escape.window="foldersModalOpen = false">
                        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm transition-opacity"
                            @click="foldersModalOpen = false"></div>

                        <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-white p-6 shadow-2xl transition-all"
                            @click.stop>
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-headline-sm font-bold text-on-surface">Chọn bộ sưu tập câu hỏi đã lưu</h3>
                                <button type="button" @click="foldersModalOpen = false"
                                    class="flex size-8 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">
                                    <span class="material-symbols-outlined text-[20px]">close</span>
                                </button>
                            </div>

                            <div class="max-h-80 space-y-2.5 overflow-y-auto pr-1">
                                <button type="button"
                                    @click="savedOnly = true; folderId = null; folderName = 'Tất cả câu đã lưu'; clearTaxonomySelection(); blueprintId = null; blueprintName = ''; foldersModalOpen = false; refreshCount()"
                                    :class="savedOnly && !folderId ? 'border-primary bg-primary/5 text-primary font-bold' : 'border-outline-variant hover:bg-surface-container-low text-on-surface'"
                                    class="flex w-full items-center justify-between rounded-xl border p-4 text-left transition-all">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-[22px]">grid_view</span>
                                        <div>
                                            <p class="text-sm font-bold">Tất cả câu đã lưu</p>
                                            <p class="text-xs text-on-surface-variant">Bao gồm câu hỏi từ tất cả bộ sưu tập</p>
                                        </div>
                                    </div>
                                    <span x-show="savedOnly && !folderId" class="material-symbols-outlined text-[20px] text-primary">check</span>
                                </button>

                                <template x-for="f in folders" :key="f.id">
                                    <button type="button"
                                        @click="savedOnly = true; folderId = f.id; folderName = f.name; clearTaxonomySelection(); blueprintId = null; blueprintName = ''; foldersModalOpen = false; refreshCount()"
                                        :class="folderId == f.id ? 'border-primary bg-primary/5 text-primary font-bold' : 'border-outline-variant hover:bg-surface-container-low text-on-surface'"
                                        class="flex w-full items-center justify-between rounded-xl border p-4 text-left transition-all">
                                        <div class="flex items-center gap-3">
                                            <span class="material-symbols-outlined text-[22px]">folder_managed</span>
                                            <div>
                                                <p class="text-sm font-bold" x-text="f.name"></p>
                                                <p class="text-xs text-on-surface-variant" x-text="f.items_count + ' câu hỏi'"></p>
                                            </div>
                                        </div>
                                        <span x-show="folderId == f.id" class="material-symbols-outlined text-[20px] text-primary">check</span>
                                    </button>
                                </template>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-outline-variant/60 pt-4">
                                <button type="button"
                                    @click="savedOnly = false; folderId = null; folderName = ''; foldersModalOpen = false; refreshCount()"
                                    class="text-xs font-bold text-on-surface-variant hover:text-error">
                                    Bỏ chọn lọc câu lưu
                                </button>
                                <button type="button" @click="foldersModalOpen = false"
                                    class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary/90">
                                    Đóng
                                </button>
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
                                <input id="session-name" type="text" value="{{ $sessionName }}"
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
                                    class="group flex w-full items-center justify-between px-6 py-4 text-left hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Trạng thái</span>
                                    </span>
                                    <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                        x-text="statuses.length ? statuses.length + ' đã chọn' : 'Tất cả'"></span>
                                </button>
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
                                    <input id="question-count" type="number" name="count" min="1" step="1"
                                        :max="Math.max(1, questionLimit())" x-model.number="count"
                                        :disabled="matching === 0"
                                        @input="countTouched = true; clampQuestionCount()"
                                        @change="countTouched = true; clampQuestionCount()"
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
                    <label class="cursor-pointer rounded-lg px-3 py-2 text-sm font-bold transition-all md:px-6"
                        :class="mode === 'exam' ? 'border border-primary bg-white text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'">
                        <input type="radio" name="mode" value="exam" x-model="mode" class="sr-only">
                        Chế độ thi
                    </label>
                </div>
            </div>
            <button type="submit"
                :disabled="!canStart()"
                class="rounded-lg px-6 py-2.5 font-bold text-white transition-all md:px-12"
                :class="!canStart()
                    ? 'cursor-not-allowed bg-primary/30 opacity-70'
                    : 'bg-primary shadow-md hover:bg-primary/90'">
                <span x-text="submitting ? 'Đang tạo…' : 'Bắt đầu'"></span>
            </button>
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
                        })[activeFilter] || 'Bộ lọc'"></h3>
                    <button type="button" @click="activeFilter = null"
                        class="rounded-full p-2 transition-colors hover:bg-surface-container" aria-label="Đóng">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="custom-scrollbar space-y-4 overflow-y-auto p-4">
                    <div x-show="activeFilter === 'exams'" class="space-y-4">
                        <p class="text-sm text-on-surface-variant" x-show="!isAdaptive()">
                            Không chọn kỳ thi → Hệ / Môn / Bài học lấy toàn bộ ngân hàng câu hỏi.
                            Chọn kỳ thi → chỉ danh mục thuộc ma trận kỳ đó.
                        </p>
                        <p class="text-sm text-on-surface-variant" x-show="isAdaptive()" x-cloak>
                            Không chọn kỳ thi → Hệ / Môn lấy toàn bộ ngân hàng.
                            Chọn kỳ thi → chỉ danh mục thuộc ma trận kỳ đó.
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
                                    Chưa có ma trận đề thi. Tạo tại Admin → Ma trận đề thi.
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
                            <p x-show="blueprintId && !(activeTaxonomyScope()?.organSystemIds || []).length"
                                class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">
                                Kỳ thi này chưa map hệ cơ quan nào. Liên kết danh mục trên ma trận đề thi.
                            </p>
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
                            <p x-show="blueprintId && !(activeTaxonomyScope()?.subjectIds || []).length"
                                class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">
                                Kỳ thi này chưa map môn học nào. Liên kết danh mục trên ma trận đề thi.
                            </p>
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

                    @include('questionbank::partials.taxonomy-session-filter-modals')
                </div>

                <div class="flex items-center justify-between border-t border-outline-variant bg-surface-container-lowest p-4">
                    <button type="button"
                        @click="activeFilter === 'exams' ? clearExam() : activeFilter === 'systems' ? clearOrganSystems() : activeFilter === 'subjects' ? clearSubjects() : activeFilter === 'difficulty' ? difficulties = [] : activeFilter === 'lessons' ? (lessonIds = [], lessonLabels = {}) : statuses = []; $nextTick(() => refreshCount())"
                        class="text-sm font-bold text-primary hover:underline">Đặt lại</button>
                    <button type="button" @click="activeFilter = null"
                        class="rounded-lg bg-primary px-8 py-2 font-bold text-white transition-opacity hover:opacity-90">Xong</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
