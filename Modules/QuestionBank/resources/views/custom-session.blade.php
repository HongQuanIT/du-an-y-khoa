@php
    /**
     * @var \Illuminate\Support\Collection<int, \Modules\QuestionBank\Models\Topic> $specialties
     * @var \Illuminate\Support\Collection<int, \Modules\QuestionBank\Models\Topic> $systems
     * @var array<string, array{title: string, icon: string, hint: string}> $exams
     * @var array<int, array{id: string, name: string}> $articles
     * @var array<int, array{id: string, name: string}> $symptoms
     */
    $initialMode = old('mode', request('mode', 'study'));
    $initialSource = old('source', request('source', 'custom'));
    $initialCount = max(1, (int) old('count', 1));
    $initialDifficultyInput = old(
        'difficulties',
        request('difficulties', old('difficulty', request('difficulty', []))),
    );
    $initialDifficulties = array_values(array_filter((array) $initialDifficultyInput));
    $initialStatuses = array_values((array) old('question_statuses', request('question_statuses', [])));
    $initialTopics = array_map('intval', (array) old('topic_ids', request('topic_ids', [])));
    $initialStatusMode = old('question_status_mode', request('question_status_mode', 'latest'));
    $initialSavedOnly = (bool) old('saved_only', request()->boolean('saved_only'));
    $initialExam = (string) old('exam_key', request('exam_key', ''));
    $initialArticles = array_values((array) old('articles', request('articles', [])));
    $initialSymptoms = array_values((array) old('symptoms', request('symptoms', [])));
    $sessionName = 'Phiên tùy chỉnh từ ' . now()->translatedFormat('j M, H:i');

    $statusOptions = [
        ['value' => 'unanswered', 'label' => 'Chưa trả lời', 'icon' => 'radio_button_unchecked'],
        ['value' => 'incorrect', 'label' => 'Làm sai', 'icon' => 'cancel'],
        ['value' => 'correct', 'label' => 'Làm đúng', 'icon' => 'check_circle'],
        ['value' => 'correct_with_hints', 'label' => 'Đúng có gợi ý', 'icon' => 'lightbulb'],
        ['value' => 'omitted', 'label' => 'Bỏ qua', 'icon' => 'remove_circle'],
        ['value' => 'marked', 'label' => 'Đã đánh dấu', 'icon' => 'folder_managed'],
    ];
    $difficultyOptions = \App\Support\ScopeFilters::difficulties();

    $selectedTopicIds = array_map('strval', $initialTopics);
    $systemIds = $systems->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    $specialtyIds = $specialties->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    $examTitles = collect($exams)->map(fn (array $exam) => $exam['title'])->all();
    $articleTitles = collect($articles)->pluck('name', 'id')->all();
    $symptomTitles = collect($symptoms)->pluck('name', 'id')->all();
@endphp

<x-layouts.app title="Tạo phiên luyện tập">
    <form method="POST" action="{{ route('qbank.store', absolute: false) }}" x-ref="builderForm"
        class="flex min-h-[calc(100vh-var(--spacing-header-height))] flex-col pb-24"
        x-data="{
            mode: {{ Illuminate\Support\Js::from($initialMode)->toHtml() }},
            source: {{ Illuminate\Support\Js::from($initialSource)->toHtml() }},
            count: {{ Illuminate\Support\Js::from($initialCount)->toHtml() }},
            difficulties: {{ Illuminate\Support\Js::from($initialDifficulties)->toHtml() }},
            difficultyLabels: {{ Illuminate\Support\Js::from(collect($difficultyOptions)->pluck('name', 'id')->all())->toHtml() }},
            difficultyOptionCount: {{ count($difficultyOptions) }},
            statuses: {{ Illuminate\Support\Js::from($initialStatuses)->toHtml() }},
            selectedTopics: {{ Illuminate\Support\Js::from($selectedTopicIds)->toHtml() }},
            systemIds: {{ Illuminate\Support\Js::from($systemIds)->toHtml() }},
            specialtyIds: {{ Illuminate\Support\Js::from($specialtyIds)->toHtml() }},
            savedOnly: {{ Illuminate\Support\Js::from($initialSavedOnly)->toHtml() }},
            examKey: {{ Illuminate\Support\Js::from($initialExam)->toHtml() }},
            examTitles: {{ Illuminate\Support\Js::from($examTitles)->toHtml() }},
            articles: {{ Illuminate\Support\Js::from($initialArticles)->toHtml() }},
            articleTitles: {{ Illuminate\Support\Js::from($articleTitles)->toHtml() }},
            symptoms: {{ Illuminate\Support\Js::from($initialSymptoms)->toHtml() }},
            symptomTitles: {{ Illuminate\Support\Js::from($symptomTitles)->toHtml() }},
            folderId: null,
            folderName: '',
            foldersModalOpen: false,
            folders: {{ Illuminate\Support\Js::from($bookmarkFolders)->toHtml() }},
            activeFilter: null,
            filterSearch: '',
            showAdvanced: false,
            matching: null,
            counting: false,
            countRequest: 0,
            countTouched: {{ old('count') !== null ? 'true' : 'false' }},
            submitting: false,
            countUrl: {{ Illuminate\Support\Js::from(route('qbank.count', absolute: false))->toHtml() }},
            csrf: {{ Illuminate\Support\Js::from(csrf_token())->toHtml() }},
            init() {
                this.$nextTick(() => this.refreshCount());
            },
            async refreshCount() {
                if (!this.$refs.builderForm) return;
                // Wait for Alpine to flush DOM updates so hidden inputs reflect current state
                await this.$nextTick();
                const requestId = ++this.countRequest;
                this.counting = true;
                try {
                    const body = new FormData(this.$refs.builderForm);
                    if (!body.has('count')) {
                        body.set('count', String(Math.max(1, Number(this.count) || 1)));
                    }
                    // Explicitly override from Alpine state to avoid stale DOM values
                    body.set('saved_only', this.savedOnly ? '1' : '0');
                    body.set('folder_id', this.folderId ? String(this.folderId) : '');
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
                this.activeFilter = filter;
                this.filterSearch = '';
            },
            selectedCount(ids) {
                return this.selectedTopics.filter((id) => ids.includes(String(id))).length;
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
            clearTopics(ids) {
                this.selectedTopics = this.selectedTopics.filter((id) => !ids.includes(String(id)));
                this.$nextTick(() => this.refreshCount());
            },
            difficultyLabel() {
                if (!this.difficulties.length || this.difficulties.length === this.difficultyOptionCount) return 'Tất cả';
                if (this.difficulties.length > 1) return this.difficulties.length + ' đã chọn';
                return this.difficultyLabels[this.difficulties[0]];
            },
            examLabel() {
                return this.examTitles[this.examKey] || 'Tất cả';
            },
            articleLabel() {
                if (!this.articles.length) return 'Tất cả';
                if (this.articles.length === 1) return this.articleTitles[this.articles[0]] || '1 đã chọn';
                return this.articles.length + ' đã chọn';
            },
            symptomLabel() {
                if (!this.symptoms.length) return 'Tất cả';
                if (this.symptoms.length === 1) return this.symptomTitles[this.symptoms[0]] || '1 đã chọn';
                return this.symptoms.length + ' đã chọn';
            },
            resetBuilder() {
                this.$refs.builderForm.reset();
                this.mode = 'study';
                this.source = 'custom';
                this.countTouched = false;
                this.count = 1;
                this.difficulties = [];
                this.statuses = [];
                this.selectedTopics = [];
                this.savedOnly = false;
                this.examKey = '';
                this.articles = [];
                this.symptoms = [];
                this.activeFilter = null;
                this.showAdvanced = false;
                this.$nextTick(() => this.refreshCount());
            },
        }"
        @change.debounce.350ms="if ($event.target.name && $event.target.name !== 'count') refreshCount()"
        @input.debounce.500ms="if ($event.target.name && $event.target.name !== 'count') refreshCount()"
        @keydown.escape.window="activeFilter = null" @submit="submitting = true">
        @csrf
        <input type="hidden" name="source" :value="source">
        <input type="hidden" name="exam_key" :value="examKey" :disabled="!examKey">
        <template x-for="article in articles" :key="'article-' + article">
            <input type="hidden" name="articles[]" :value="article">
        </template>
        <template x-for="symptom in symptoms" :key="'symptom-' + symptom">
            <input type="hidden" name="symptoms[]" :value="symptom">
        </template>
        <input type="hidden" name="question_status_mode" value="{{ $initialStatusMode }}">
        <input type="hidden" name="saved_only" :value="savedOnly ? '1' : '0'">
        <input type="hidden" name="folder_id" :value="folderId ?? ''">

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

            <div class="mb-8 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2 font-bold text-primary">
                    <span class="material-symbols-outlined fill-1">auto_awesome</span>
                    <span>Tạo phiên luyện tập bằng chế độ AI</span>
                </div>
                <button type="button" disabled
                    class="flex cursor-not-allowed items-center gap-2 rounded-full border border-outline-variant bg-white px-4 py-2 text-sm font-medium opacity-60"
                    title="Tính năng đang được phát triển">
                    <span class="material-symbols-outlined text-[18px] text-primary">upload_file</span>
                    Tải tệp lên để tạo phiên luyện
                </button>
                <button type="button" disabled
                    class="flex cursor-not-allowed items-center gap-2 rounded-full border border-outline-variant bg-white px-4 py-2 text-sm font-medium opacity-60"
                    title="Tính năng đang được phát triển">
                    <span class="material-symbols-outlined text-[18px] text-primary">chat_bubble_outline</span>
                    Mô tả nội dung bạn muốn học
                </button>
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
                                    :disabled="source === 'weak_topics' || savedOnly"
                                    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Kỳ thi</span>
                                    </span>
                                    <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                        x-text="examLabel()"></span>
                                </button>

                                <button type="button" @click="openFilter('articles')"
                                    :disabled="source === 'weak_topics' || savedOnly"
                                    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Bài viết</span>
                                    </span>
                                    <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                        x-text="articleLabel()"></span>
                                </button>

                                <button type="button" @click="openFilter('systems')"
                                    :disabled="source === 'weak_topics' || savedOnly"
                                    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Hệ cơ quan</span>
                                    </span>
                                    <span class="text-sm text-on-surface-variant"
                                        x-text="selectedCount(systemIds) ? selectedCount(systemIds) + ' đã chọn' : 'Tất cả'"></span>
                                </button>

                                <button type="button" @click="openFilter('specialties')"
                                    :disabled="source === 'weak_topics' || savedOnly"
                                    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Chuyên khoa</span>
                                    </span>
                                    <span class="text-sm text-on-surface-variant"
                                        x-text="selectedCount(specialtyIds) ? selectedCount(specialtyIds) + ' đã chọn' : 'Tất cả'"></span>
                                </button>

                                <button type="button" @click="openFilter('symptoms')"
                                    :disabled="source === 'weak_topics' || savedOnly"
                                    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
                                    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <span class="flex items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">Triệu chứng</span>
                                    </span>
                                    <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                        x-text="symptomLabel()"></span>
                                </button>

                                <button type="button" @click="foldersModalOpen = true"
                                    :disabled="source === 'weak_topics'"
                                    :class="source === 'weak_topics' && 'opacity-50 pointer-events-none'"
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

                     <!-- Bookmark Collections Modal -->
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

                             <div class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                                 <!-- Option: Tất cả câu hỏi đã lưu -->
                                 <button type="button"
                                     @click="savedOnly = true; folderId = null; folderName = 'Tất cả câu đã lưu'; selectedTopics = []; articles = []; symptoms = []; examKey = ''; foldersModalOpen = false; refreshCount()"
                                     :class="savedOnly && !folderId ? 'border-primary bg-primary/5 text-primary font-bold' : 'border-outline-variant hover:bg-surface-container-low text-on-surface'"
                                     class="flex w-full items-center justify-between rounded-xl border p-4 text-left transition-all">
                                     <div class="flex items-center gap-3">
                                         <span class="material-symbols-outlined text-[22px]">grid_view</span>
                                         <div>
                                             <p class="text-sm font-bold">Tất cả câu đã lưu</p>
                                             <p class="text-xs text-on-surface-variant">Bao gồm câu hỏi từ tất cả bộ sưu tập</p>
                                         </div>
                                     </div>
                                     <span x-show="savedOnly && !folderId" class="material-symbols-outlined text-primary text-[20px]">check</span>
                                 </button>

                                 <!-- User Collections -->
                                 <template x-for="f in folders" :key="f.id">
                                     <button type="button"
                                         @click="savedOnly = true; folderId = f.id; folderName = f.name; selectedTopics = []; articles = []; symptoms = []; examKey = ''; foldersModalOpen = false; refreshCount()"
                                         :class="folderId == f.id ? 'border-primary bg-primary/5 text-primary font-bold' : 'border-outline-variant hover:bg-surface-container-low text-on-surface'"
                                         class="flex w-full items-center justify-between rounded-xl border p-4 text-left transition-all">
                                         <div class="flex items-center gap-3">
                                             <span class="material-symbols-outlined text-[22px]">folder_managed</span>
                                             <div>
                                                 <p class="text-sm font-bold" x-text="f.name"></p>
                                                 <p class="text-xs text-on-surface-variant" x-text="f.items_count + ' câu hỏi'"></p>
                                             </div>
                                         </div>
                                         <span x-show="folderId == f.id" class="material-symbols-outlined text-primary text-[20px]">check</span>
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

                            <div class="flex items-start justify-between rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <div class="flex gap-3">
                                    <span class="material-symbols-outlined mt-0.5 text-primary fill-1">auto_awesome</span>
                                    <div>
                                        <p class="text-sm font-bold text-primary">Phiên luyện thích ứng</p>
                                        <p class="mt-1 text-xs leading-relaxed text-on-surface-variant">
                                            Ưu tiên câu hỏi từ các chủ đề bạn thường trả lời sai.
                                        </p>
                                    </div>
                                </div>
                                <button type="button"
                                    @click="source = source === 'weak_topics' ? 'custom' : 'weak_topics'; $nextTick(() => refreshCount())"
                                    class="relative flex h-6 w-12 shrink-0 items-center rounded-full transition-colors"
                                    :class="source === 'weak_topics' ? 'bg-primary' : 'bg-outline-variant'"
                                    :aria-pressed="source === 'weak_topics'" aria-label="Bật phiên luyện thích ứng">
                                    <span class="absolute size-4 rounded-full bg-white transition-all"
                                        :class="source === 'weak_topics' ? 'left-7' : 'left-1'"></span>
                                </button>
                            </div>

                            <div class="-mx-6 space-y-0 border-y border-outline-variant">
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

                            <button type="button" @click="showAdvanced = !showAdvanced"
                                class="flex items-center gap-1 text-[11px] font-bold tracking-widest text-on-surface-variant uppercase hover:text-primary">
                                Thêm
                                <span class="material-symbols-outlined text-[16px] transition-transform"
                                    :class="showAdvanced && 'rotate-180'">expand_more</span>
                            </button>

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
                                        @blur="clampQuestionCount()" required
                                        class="w-20 rounded-lg border border-outline-variant py-2.5 text-center text-lg font-bold focus:ring-2 focus:ring-primary disabled:cursor-not-allowed disabled:opacity-60">
                                    <span class="text-lg font-medium text-on-surface-variant">
                                        / <span x-text="counting ? '…' : (matching ?? 0)"></span>
                                    </span>
                                </div>
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
                :disabled="matching === null || matching === 0 || counting || submitting || count < 1 || count > questionLimit()"
                class="rounded-lg px-6 py-2.5 font-bold text-white transition-all md:px-12"
                :class="matching === null || matching === 0 || counting || submitting || count < 1 || count > questionLimit()
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
                        x-text="activeFilter === 'exams' ? 'Chọn kỳ thi' : activeFilter === 'articles' ? 'Bài viết' : activeFilter === 'symptoms' ? 'Triệu chứng' : activeFilter === 'systems' ? 'Hệ cơ quan' : activeFilter === 'specialties' ? 'Chuyên khoa' : activeFilter === 'difficulty' ? 'Độ khó' : 'Trạng thái'"></h3>
                    <button type="button" @click="activeFilter = null"
                        class="rounded-full p-2 transition-colors hover:bg-surface-container" aria-label="Đóng">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="custom-scrollbar space-y-4 overflow-y-auto p-4">
                    <div x-show="activeFilter === 'exams'" class="space-y-4">
                        <p class="text-sm text-on-surface-variant">
                            Chọn kỳ thi mục tiêu giống với phạm vi trong Kế hoạch học tập.
                        </p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                            @foreach ($exams as $key => $exam)
                                <button type="button" @click="examKey = '{{ $key }}'"
                                    class="relative cursor-pointer rounded-lg border p-4 text-left transition-colors"
                                    :class="examKey === '{{ $key }}'
                                        ? 'border-2 border-primary bg-[#f0fdfa]'
                                        : 'border-outline-variant bg-surface hover:border-primary/50'">
                                    <span class="absolute top-4 right-4 text-primary" x-show="examKey === '{{ $key }}'" x-cloak>
                                        <span class="material-symbols-outlined fill-1">check_circle</span>
                                    </span>
                                    <span class="material-symbols-outlined mb-2 text-3xl"
                                        :class="examKey === '{{ $key }}' ? 'text-primary' : 'text-on-surface-variant'">{{ $exam['icon'] }}</span>
                                    <span class="mb-1 block pr-7 font-label-md text-label-md text-on-surface">{{ $exam['title'] }}</span>
                                    <span class="block font-body-sm text-body-sm text-on-surface-variant">{{ $exam['hint'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="activeFilter === 'articles'" class="space-y-4">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                            <input type="search" x-model="filterSearch" placeholder="Tìm bài viết..."
                                class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="space-y-1">
                            @foreach ($articles as $article)
                                <label data-search="{{ Str::lower($article['name']) }}"
                                    x-show="$el.dataset.search.includes(filterSearch.toLocaleLowerCase())"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox" value="{{ $article['id'] }}" x-model="articles"
                                        @change="$nextTick(() => refreshCount())"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="text-sm">{{ $article['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="activeFilter === 'symptoms'" class="space-y-4">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                            <input type="search" x-model="filterSearch" placeholder="Tìm triệu chứng..."
                                class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="space-y-1">
                            @foreach ($symptoms as $symptom)
                                <label data-search="{{ Str::lower($symptom['name']) }}"
                                    x-show="$el.dataset.search.includes(filterSearch.toLocaleLowerCase())"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox" value="{{ $symptom['id'] }}" x-model="symptoms"
                                        @change="$nextTick(() => refreshCount())"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="text-sm">{{ $symptom['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="activeFilter === 'systems' || activeFilter === 'specialties'" class="space-y-4">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                            <input type="search" x-model="filterSearch" placeholder="Tìm kiếm..."
                                class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
                        </div>

                        <button type="button"
                            @click="clearTopics(activeFilter === 'systems' ? systemIds : specialtyIds)"
                            class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
                            <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
                            <span>
                                <span class="block text-sm font-bold">Tất cả</span>
                                <span class="block text-xs leading-relaxed text-on-surface-variant">Không giới hạn theo nhóm chủ đề này.</span>
                            </span>
                        </button>

                        <div x-show="activeFilter === 'systems'" class="space-y-1">
                            @forelse ($systems as $topic)
                                <label data-search="{{ Str::lower($topic->name) }}"
                                    x-show="$el.dataset.search.includes(filterSearch.toLocaleLowerCase())"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" x-model="selectedTopics"
                                        :disabled="source === 'weak_topics'"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="text-sm">{{ $topic->name }}</span>
                                </label>
                            @empty
                                <p class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">Chưa có dữ liệu hệ cơ quan.</p>
                            @endforelse
                        </div>

                        <div x-show="activeFilter === 'specialties'" class="space-y-1">
                            @forelse ($specialties as $topic)
                                <label data-search="{{ Str::lower($topic->name) }}"
                                    x-show="$el.dataset.search.includes(filterSearch.toLocaleLowerCase())"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" x-model="selectedTopics"
                                        :disabled="source === 'weak_topics'"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="text-sm">{{ $topic->name }}</span>
                                </label>
                            @empty
                                <p class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">Chưa có dữ liệu chuyên khoa.</p>
                            @endforelse
                        </div>
                    </div>

                    <div x-show="activeFilter === 'difficulty'" class="space-y-1">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 hover:bg-surface-container-low">
                            <input type="checkbox" :checked="difficulties.length === 0"
                                @change="if ($event.target.checked) difficulties = []; $nextTick(() => refreshCount())"
                                :disabled="source === 'weak_topics'"
                                class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                            <span class="text-sm font-medium">Tất cả độ khó</span>
                        </label>
                        @foreach ($difficultyOptions as $difficultyOption)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 hover:bg-surface-container-low">
                                <input type="checkbox" name="difficulties[]" value="{{ $difficultyOption['id'] }}" x-model="difficulties"
                                    :disabled="source === 'weak_topics'"
                                    class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="text-sm font-medium">{{ $difficultyOption['name'] }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div x-show="activeFilter === 'statuses'" class="space-y-1">
                        @foreach ($statusOptions as $status)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 hover:bg-surface-container-low">
                                <input type="checkbox" name="question_statuses[]" value="{{ $status['value'] }}"
                                    x-model="statuses" :disabled="source === 'weak_topics'"
                                    class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="material-symbols-outlined text-[19px] text-on-surface-variant">{{ $status['icon'] }}</span>
                                <span class="text-sm font-medium">{{ $status['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-outline-variant bg-surface-container-lowest p-4">
                    <button type="button"
                        @click="activeFilter === 'exams' ? examKey = '' : activeFilter === 'articles' ? articles = [] : activeFilter === 'symptoms' ? symptoms = [] : activeFilter === 'systems' ? clearTopics(systemIds) : activeFilter === 'specialties' ? clearTopics(specialtyIds) : activeFilter === 'difficulty' ? difficulties = [] : statuses = []; $nextTick(() => refreshCount())"
                        class="text-sm font-bold text-primary hover:underline">Đặt lại</button>
                    <button type="button" @click="activeFilter = null"
                        class="rounded-lg bg-primary px-8 py-2 font-bold text-white transition-opacity hover:opacity-90">Xong</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
