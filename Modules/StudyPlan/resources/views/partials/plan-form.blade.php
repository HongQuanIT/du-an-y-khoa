@php
    /**
     * Wizard form shared by create and edit.
     *
     * @var \Illuminate\Support\Collection $specialties  specialty topics
     * @var \Illuminate\Support\Collection $systems  organ-system topics
     * @var array $exams
     * @var \Modules\StudyPlan\Models\StudyPlan|null $plan
     */
    $plan = $plan ?? null;
    $systems = $systems ?? collect();
    $filters = $plan?->scopeFilters() ?? [
        'medical_taxonomy_node_ids' => [],
        'exam_tags' => [],
        'articles' => [],
        'symptoms' => [],
        'saved_only' => false,
        'difficulties' => [],
        'difficulty' => null,
        'question_statuses' => [],
        'question_status_mode' => 'latest',
        'blueprint_id' => null,
        'blueprint_section_id' => null,
        'core_clinical_topic_ids' => [],
        'tag_ids' => [],
    ];

    $selectedTopicIds = array_map('intval', old('medical_taxonomy_node_ids', $filters['medical_taxonomy_node_ids'] ?? $filters['topic_ids'] ?? []));
    $specialtyIds = $specialties->pluck('id')->all();
    $systemIdList = $systems->pluck('id')->all();
    $defaultExam = old('exam_key', $plan?->exam_key ?? array_key_first($exams));
    $savedExamTags = array_values(old('exam_tags', $filters['exam_tags']));

    $initial = [
        'exam' => $defaultExam,
        'date' => old('exam_target_date', $plan?->exam_target_date?->toDateString() ?? $defaultDate ?? now()->addMonths(3)->toDateString()),
        'specialtyIds' => array_values(array_intersect($selectedTopicIds, $specialtyIds)),
        'systemIds' => array_values(array_intersect($selectedTopicIds, $systemIdList !== [] ? $systemIdList : array_diff($selectedTopicIds, $specialtyIds))),
        'examTags' => $savedExamTags,
        'articles' => array_values(old('articles', $filters['articles'])),
        'symptoms' => array_values(old('symptoms', $filters['symptoms'])),
        'savedOnly' => (bool) old('saved_only', $filters['saved_only']),
        'difficulties' => array_values((array) old(
            'difficulties',
            $filters['difficulties'] !== []
                ? $filters['difficulties']
                : (is_string($filters['difficulty']) ? [$filters['difficulty']] : []),
        )),
        'questionStatuses' => array_values(old('question_statuses', $filters['question_statuses'])),
        'questionStatusMode' => old('question_status_mode', $filters['question_status_mode']),
        'intensity' => (int) old('daily_goal_questions', $plan?->daily_goal_questions ?? 40),
        'days' => array_map('intval', old('study_days', $plan?->studyWeekdays() ?? [1, 2, 3, 4, 5])),
        'strategy' => old('strategy', $plan?->strategy->value ?? 'fixed'),
        'blueprintId' => old('blueprint_id', $filters['blueprint_id'] ?? null),
        'blueprintSectionId' => old('blueprint_section_id', $filters['blueprint_section_id'] ?? null),
        'coreClinicalTopicIds' => array_map('intval', old('core_clinical_topic_ids', $filters['core_clinical_topic_ids'] ?? [])),
        'tagIds' => array_map('intval', old('tag_ids', $filters['tag_ids'] ?? [])),
    ];

    $weekdays = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

    $systemOptions = $systems
        ->map(fn ($topic) => ['id' => $topic->id, 'name' => $topic->name])
        ->values();

    $specialtyOptions = $specialties
        ->map(fn ($topic) => ['id' => $topic->id, 'name' => $topic->name])
        ->values();

    $examTagOptions = \Modules\StudyPlan\Support\ScopeFilters::examTags();
    $articleOptions = \Modules\StudyPlan\Support\ScopeFilters::articles();
    $symptomOptions = \Modules\StudyPlan\Support\ScopeFilters::symptoms();
    $difficultyOptions = \Modules\StudyPlan\Support\ScopeFilters::difficulties();
    $statusOptions = \Modules\StudyPlan\Support\ScopeFilters::questionStatuses();
@endphp

<form method="POST" action="{{ $formAction }}" class="contents" x-ref="planForm"
    x-data="{
        exam: @js($initial['exam']),
        date: @js($initial['date']),
        specialtyIds: @js($initial['specialtyIds']),
        systemIds: @js($initial['systemIds']),
        examTags: @js($initial['examTags']),
        articles: @js($initial['articles']),
        symptoms: @js($initial['symptoms']),
        savedOnly: @js($initial['savedOnly']),
        difficulties: @js($initial['difficulties']),
        questionStatuses: @js($initial['questionStatuses']),
        questionStatusMode: @js($initial['questionStatusMode']),
        intensity: @js($initial['intensity']),
        days: @js($initial['days']),
        strategy: @js($initial['strategy']),
        matching: null,
        counting: false,
        countUrl: @js(route('qbank.count', absolute: false)),
        source: 'custom',
        activeFilter: null,
        taxonomySearch: '',
        blueprintId: @js($initial['blueprintId'] ? (int) $initial['blueprintId'] : null),
        blueprintName: '',
        blueprintSectionId: @js($initial['blueprintSectionId'] ? (int) $initial['blueprintSectionId'] : null),
        blueprintSectionName: '',
        coreClinicalTopicIds: @js($initial['coreClinicalTopicIds']),
        coreClinicalTopicLabels: {},
        medicalTaxonomyNodeIds: @js($selectedTopicIds),
        medicalNodeLabels: {},
        tagIds: @js($initial['tagIds']),
        tagLabels: {},
        blueprintResults: [], blueprintSectionResults: [], coreTopicResults: [], medicalNodeResults: [], tagResults: [],
        taxonomyUrls: {
            blueprints: @js(route('qbank.taxonomy.lookups.blueprints', absolute: false)),
            coreTopicsSearch: @js(route('qbank.taxonomy.lookups.core-topics.search', absolute: false)),
            medicalNodes: @js(route('qbank.taxonomy.lookups.medical-nodes', absolute: false)),
            tags: @js(route('qbank.taxonomy.lookups.tags', absolute: false)),
        },
        specialtyOptions: @js($specialtyOptions),
        systemOptions: @js($systemOptions),
        examTagOptions: @js($examTagOptions),
        articleOptions: @js($articleOptions),
        symptomOptions: @js($symptomOptions),
        difficultyOptions: @js($difficultyOptions),
        statusOptions: @js($statusOptions),
        modal: null,
        draft: [],
        draftSingle: null,
        draftStatusMode: 'latest',
        search: '',
        modalMeta: {
            systems: { title: 'Hệ cơ quan', search: 'Tìm hệ cơ quan...', multi: true, source: 'systemOptions', target: 'systemIds' },
            specialty: { title: 'Chuyên khoa', search: 'Tìm chuyên khoa...', multi: true, source: 'specialtyOptions', target: 'specialtyIds' },
            articles: { title: 'Bài viết', search: 'Tìm bài viết...', multi: true, source: 'articleOptions', target: 'articles' },
            symptoms: { title: 'Triệu chứng', search: 'Tìm triệu chứng...', multi: true, source: 'symptomOptions', target: 'symptoms' },
            saved: { title: 'Câu hỏi đã lưu', search: null, multi: false, source: null, target: 'savedOnly' },
            difficulty: { title: 'Độ khó', search: null, multi: true, source: 'difficultyOptions', target: 'difficulties' },
            status: { title: 'Trạng thái', search: null, multi: true, source: 'statusOptions', target: 'questionStatuses' },
        },
        selectExam(key) {
            this.exam = key;
        },
        openFilter(filter) {
            this.activeFilter = filter; this.taxonomySearch = '';
            if (filter === 'blueprint') this.fetchBlueprints();
            if (filter === 'coreTopics') this.fetchCoreTopics();
            if (filter === 'medicalNodes') this.fetchMedicalNodes();
            if (filter === 'tags') this.fetchTags();
        },
        async fetchBlueprints() { const q=this.taxonomySearch.trim(); const r=await fetch(this.taxonomyUrls.blueprints+(q?'?q='+encodeURIComponent(q):'')); this.blueprintResults=(await r.json()).data??[]; },
        async fetchBlueprintSections() { if(!this.blueprintId)return; const q=this.taxonomySearch.trim(); const r=await fetch(this.taxonomyUrls.blueprints+'/'+this.blueprintId+'/sections'+(q?'?q='+encodeURIComponent(q):'')); this.blueprintSectionResults=(await r.json()).data??[]; },
        async fetchCoreTopics() { const p=new URLSearchParams(); if(this.blueprintId)p.set('blueprint_id',this.blueprintId); if(this.blueprintSectionId)p.set('blueprint_section_id',this.blueprintSectionId); if(this.taxonomySearch.trim().length>=2)p.set('q',this.taxonomySearch.trim()); const r=await fetch(this.taxonomyUrls.coreTopicsSearch+'?'+p); this.coreTopicResults=(await r.json()).data??[]; },
        async fetchMedicalNodes() { const p=new URLSearchParams({include_descendants:'1'}); if(this.taxonomySearch.trim().length>=2)p.set('q',this.taxonomySearch.trim()); const r=await fetch(this.taxonomyUrls.medicalNodes+'?'+p); this.medicalNodeResults=(await r.json()).data??[]; },
        async fetchTags() { const q=this.taxonomySearch.trim(); const r=await fetch(this.taxonomyUrls.tags+(q?'?q='+encodeURIComponent(q):'')); this.tagResults=(await r.json()).data??[]; },
        selectBlueprint(i){this.blueprintId=i.id;this.blueprintName=i.name;this.clearBlueprintSection();},
        clearBlueprint(){this.blueprintId=null;this.blueprintName='';this.clearBlueprintSection();},
        selectBlueprintSection(i){this.blueprintSectionId=i.id;this.blueprintSectionName=i.name;},
        clearBlueprintSection(){this.blueprintSectionId=null;this.blueprintSectionName='';},
        toggleCoreTopic(i){const n=this.coreClinicalTopicIds.indexOf(i.id);if(n>=0){this.coreClinicalTopicIds.splice(n,1);delete this.coreClinicalTopicLabels[i.id]}else{this.coreClinicalTopicIds.push(i.id);this.coreClinicalTopicLabels[i.id]=i.name}},
        toggleMedicalNode(i){const n=this.medicalTaxonomyNodeIds.indexOf(i.id);if(n>=0){this.medicalTaxonomyNodeIds.splice(n,1);delete this.medicalNodeLabels[i.id]}else{this.medicalTaxonomyNodeIds.push(i.id);this.medicalNodeLabels[i.id]=i.name}},
        toggleTag(i){const n=this.tagIds.indexOf(i.id);if(n>=0){this.tagIds.splice(n,1);delete this.tagLabels[i.id]}else{this.tagIds.push(i.id);this.tagLabels[i.id]=i.name}},
        blueprintLabel(){return this.blueprintName||(this.blueprintId?'Đã chọn':'Tất cả')},
        coreTopicLabel(){return this.coreClinicalTopicIds.length?this.coreClinicalTopicIds.length+' đã chọn':'Tất cả'},
        medicalNodeLabel(){return this.medicalTaxonomyNodeIds.length?this.medicalTaxonomyNodeIds.length+' đã chọn':'Tất cả'},
        tagLabel(){return this.tagIds.length?this.tagIds.length+' đã chọn':'Tất cả'},
        groupedCoreTopics(){const g=[];const m=new Map;this.coreTopicResults.forEach(t=>{const k=String(t.blueprint_section_id??'other');if(!m.has(k)){const x={id:k,name:t.section_name||'Chủ đề khác',topics:[]};m.set(k,x);g.push(x)}m.get(k).topics.push(t)});return g},
        async refreshCount(){
            await this.$nextTick();
            this.counting=true;
            try {
                const body=new FormData(this.$refs.planForm);
                body.set('mode','study'); body.set('source','custom'); body.set('count','1');
                // Kỳ thi mục tiêu dùng để đặt tên/mốc kế hoạch, không phải bộ lọc
                // phạm vi. Khi các hàng phạm vi đều là "Tất cả", phải đếm toàn kho.
                body.delete('exam_key');
                body.delete('exam_tags[]');
                const r=await fetch(this.countUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':@js(csrf_token())},body});
                const j=await r.json(); this.matching=r.ok?Number(j?.data?.count??0):0;
            } catch(e) { this.matching=0 } finally { this.counting=false }
        },
        openModal(type) {
            this.modal = type;
            this.search = '';
            const meta = this.modalMeta[type];
            if (type === 'status') {
                this.draft = [...this.questionStatuses];
                this.draftStatusMode = this.questionStatusMode;
                return;
            }
            if (type === 'saved') {
                this.draftSingle = this.savedOnly ? 'saved' : null;
                return;
            }
            if (meta.multi) {
                this.draft = [...(this[meta.target] || [])];
            } else {
                this.draftSingle = this[meta.target] || null;
            }
        },
        closeModal() { this.modal = null; },
        options() {
            const source = this.modalMeta[this.modal]?.source;
            return source ? (this[source] || []) : [];
        },
        visibleOptions() {
            const needle = this.search.trim().toLowerCase();
            if (!needle) return this.options();
            return this.options().filter((option) => option.name.toLowerCase().includes(needle));
        },
        toggleDraft(id) {
            const i = this.draft.indexOf(id);
            if (i === -1) this.draft.push(id);
            else this.draft.splice(i, 1);
        },
        applyModal() {
            const meta = this.modalMeta[this.modal];
            if (!meta) return;
            if (this.modal === 'saved') {
                this.savedOnly = this.draftSingle === 'saved';
            } else if (this.modal === 'status') {
                this.questionStatuses = [...this.draft];
                this.questionStatusMode = this.draftStatusMode;
            } else if (meta.multi) {
                this[meta.target] = [...this.draft];
            } else {
                this[meta.target] = this.draftSingle;
            }
            this.closeModal();
        },
        resetModal() {
            const meta = this.modalMeta[this.modal];
            if (!meta) return;
            if (this.modal === 'status') {
                this.draft = [];
                this.draftStatusMode = 'latest';
            } else if (this.modal === 'saved' || !meta.multi) this.draftSingle = null;
            else this.draft = [];
        },
        chips(ids, options) {
            const names = ids.map((id) => (options.find((o) => o.id === id) || {}).name).filter(Boolean);
            if (names.length === 0) return [];
            if (names.length <= 2) return names;
            return [names[0], '+' + (names.length - 1)];
        },
        labelFor(id, options) {
            return (options.find((o) => o.id === id) || {}).name || null;
        },
        toggleDay(day) {
            const i = this.days.indexOf(day);
            if (i === -1) this.days.push(day);
            else this.days.splice(i, 1);
        },
        topicIds() { return this.medicalTaxonomyNodeIds; },
        studyDaysUntilExam() {
            if (!this.date || this.days.length === 0) return 0;
            const target = new Date(this.date + 'T00:00:00');
            const cursor = new Date();
            cursor.setHours(0, 0, 0, 0);
            let count = 0;
            while (cursor <= target && count < 2000) {
                const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
                if (this.days.includes(iso)) count++;
                cursor.setDate(cursor.getDate() + 1);
            }
            return count;
        },
        totalQuestions() { const capacity=this.studyDaysUntilExam()*this.intensity; return this.matching===null?capacity:Math.min(capacity,this.matching); },
        daysUntilExam() {
            if (!this.date) return 0;
            const target = new Date(this.date + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return Math.max(0, Math.round((target - today) / 86400000));
        },
    }" x-init="refreshCount()" @change.debounce.300ms="refreshCount()" @keydown.escape.window="if (activeFilter) activeFilter = null; else if (modal) closeModal()">
    @csrf
    @isset($formMethod)
        @method($formMethod)
    @endisset

    <input type="hidden" name="exam_key" :value="exam">
    <input type="hidden" name="daily_goal_questions" :value="intensity">
    <input type="hidden" name="strategy" :value="strategy">
    <input type="hidden" name="mode" value="study">
    <input type="hidden" name="source" value="custom">
    <input type="hidden" name="count" value="1">
    <input type="hidden" name="saved_only" :value="savedOnly ? 1 : 0">
    <template x-for="difficulty in difficulties" :key="'difficulty-' + difficulty">
        <input type="hidden" name="difficulties[]" :value="difficulty">
    </template>
    <input type="hidden" name="question_status_mode" :value="questionStatusMode">
    <input type="hidden" name="blueprint_id" :value="blueprintId || ''">
    <input type="hidden" name="blueprint_section_id" :value="blueprintSectionId || ''">
    <template x-for="id in coreClinicalTopicIds" :key="'core-' + id">
        <input type="hidden" name="core_clinical_topic_ids[]" :value="id">
    </template>
    <template x-for="id in tagIds" :key="'tag-' + id">
        <input type="hidden" name="tag_ids[]" :value="id">
    </template>
    <template x-for="id in topicIds()" :key="'topic-' + id">
        <input type="hidden" name="medical_taxonomy_node_ids[]" :value="id">
    </template>
    <template x-for="tag in examTags" :key="'exam-tag-' + tag">
        <input type="hidden" name="exam_tags[]" :value="tag">
    </template>
    <template x-for="article in articles" :key="'article-' + article">
        <input type="hidden" name="articles[]" :value="article">
    </template>
    <template x-for="symptom in symptoms" :key="'symptom-' + symptom">
        <input type="hidden" name="symptoms[]" :value="symptom">
    </template>
    <template x-for="status in questionStatuses" :key="'question-status-' + status">
        <input type="hidden" name="question_statuses[]" :value="status">
    </template>
    <template x-for="day in days" :key="'day-' + day">
        <input type="hidden" name="study_days[]" :value="day">
    </template>

    <div class="flex flex-col gap-gutter lg:flex-row">
        <!-- Left: Wizard -->
        <div class="flex-1 space-y-8">
            @if ($errors->any())
                <div class="rounded-lg border border-error/30 bg-error-container/20 p-4">
                    <ul class="space-y-1 text-body-sm text-error">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm">
                <!-- Exam -->
                <section class="mb-10">
                    <h2 class="mb-2 flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                        <span class="material-symbols-outlined text-primary">school</span>
                        Chọn kỳ thi mục tiêu
                    </h2>
                    <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant">
                        Kỳ thi bạn đang hướng tới — cũng là bộ lọc câu hỏi của lộ trình.
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                        @foreach ($exams as $key => $exam)
                            <button type="button" @click="selectExam('{{ $key }}')"
                                class="relative cursor-pointer rounded-lg border p-4 text-left transition-colors"
                                :class="exam === '{{ $key }}'
                                    ? 'border-2 border-primary bg-[#f0fdfa]'
                                    : 'border-outline-variant bg-surface hover:border-primary/50'">
                                <div class="absolute top-4 right-4 text-primary" x-show="exam === '{{ $key }}'" x-cloak>
                                    <span class="material-symbols-outlined"
                                        style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                </div>
                                <span class="material-symbols-outlined mb-2 text-3xl"
                                    :class="exam === '{{ $key }}' ? 'text-primary' : 'text-on-surface-variant'">{{ $exam['icon'] }}</span>
                                <h3 class="mb-1 font-label-md text-label-md text-on-surface">{{ $exam['title'] }}</h3>
                                <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $exam['hint'] }}</p>
                            </button>
                        @endforeach
                    </div>
                </section>

                <div class="mb-10 h-px w-full bg-outline-variant"></div>

                <!-- Date -->
                <section class="mb-10">
                    <h2 class="mb-4 flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                        <span class="material-symbols-outlined text-primary">calendar_month</span>
                        Ngày thi dự kiến
                    </h2>
                    <div class="max-w-md">
                        <label for="exam_target_date"
                            class="mb-2 block font-label-md text-label-md text-on-surface-variant">Chọn ngày</label>
                        <input id="exam_target_date" type="date" name="exam_target_date" x-model="date"
                            min="{{ now()->addDay()->toDateString() }}" required
                            class="w-full rounded-lg border border-outline-variant bg-surface px-4 py-3 font-body-md text-body-md transition-all focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                    </div>
                </section>

                <div class="mb-10 h-px w-full bg-outline-variant"></div>

                <!-- Scope -->
                <section class="mb-10">
                    <h2 class="mb-2 flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                        <span class="material-symbols-outlined text-primary">category</span>
                        Phạm vi ôn tập
                    </h2>
                    <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant">
                        Chọn bộ lọc giống khi tạo phiên luyện. Để trống nghĩa là Tất cả.
                    </p>

                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                        <div class="space-y-0">
                            @include('questionbank::partials.taxonomy-session-filter-rows')

                            <button type="button" @click="openModal('articles')"
                                class="group flex w-full cursor-pointer items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                <div class="flex shrink-0 items-center gap-4">
                                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                    <span class="font-medium">Bài viết</span>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-4">
                                    <template x-if="articles.length === 0">
                                        <span class="text-sm text-on-surface-variant">Tất cả</span>
                                    </template>
                                    <template x-for="chip in chips(articles, articleOptions)" :key="'article-chip-' + chip">
                                        <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                            x-text="chip"></span>
                                    </template>
                                </div>
                            </button>

                            <button type="button" @click="openModal('symptoms')"
                                class="group flex w-full cursor-pointer items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                <div class="flex shrink-0 items-center gap-4">
                                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                    <span class="font-medium">Triệu chứng</span>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-4">
                                    <template x-if="symptoms.length === 0">
                                        <span class="text-sm text-on-surface-variant">Tất cả</span>
                                    </template>
                                    <template x-for="chip in chips(symptoms, symptomOptions)" :key="'symptom-chip-' + chip">
                                        <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                            x-text="chip"></span>
                                    </template>
                                </div>
                            </button>

                            <button type="button" @click="openModal('saved')"
                                class="group flex w-full cursor-pointer items-center justify-between px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                <div class="flex shrink-0 items-center gap-4">
                                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                    <span class="font-medium">Câu hỏi đã lưu</span>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-4">
                                    <template x-if="!savedOnly">
                                        <span class="text-sm text-on-surface-variant">Tất cả</span>
                                    </template>
                                    <template x-if="savedOnly">
                                        <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed">Đã lưu</span>
                                    </template>
                                </div>
                            </button>
                        </div>
                    </div>
                </section>

                <div class="mb-10 h-px w-full bg-outline-variant"></div>

                <!-- Intensity -->
                <section>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                            <span class="material-symbols-outlined text-primary">speed</span>
                            Cường độ ôn tập
                        </h2>
                        <span class="rounded-full bg-primary-container/10 px-3 py-1 font-label-md text-label-md text-primary"
                            x-text="intensity + ' câu/ngày ~ ' + Math.round(intensity * 2.25) + ' phút'"></span>
                    </div>
                    <div class="mb-8 px-2">
                        <input type="range" min="5" max="100" step="5" x-model.number="intensity"
                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-surface-variant accent-primary">
                        <div class="mt-2 flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                            <span>Thảnh thơi (5/ngày)</span>
                            <span>Tập trung (100/ngày)</span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h3 class="mb-3 font-label-md text-label-md text-on-surface">Ngày học trong tuần</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($weekdays as $iso => $label)
                                <button type="button" @click="toggleDay({{ $iso }})"
                                    class="flex size-10 items-center justify-center rounded-full font-label-md text-label-md transition-colors"
                                    :class="days.includes({{ $iso }})
                                        ? 'bg-[#e6f2f1] text-[#005c55] border border-[#0f766e]'
                                        : 'bg-surface border border-outline-variant text-on-surface-variant'">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <h3 class="mb-3 font-label-md text-label-md text-on-surface">Chiến lược phân bổ</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant p-4 transition-colors hover:bg-surface-container-lowest"
                            :class="strategy === 'fixed' && 'ring-1 ring-primary border-primary'">
                            <input type="radio" value="fixed" x-model="strategy"
                                class="mt-1 text-primary focus:ring-primary">
                            <div>
                                <span class="block font-label-md text-label-md text-on-surface">Cố định</span>
                                <span class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">Số lượng câu
                                    hỏi không đổi mỗi ngày.</span>
                            </div>
                        </label>
                        <label class="relative flex cursor-pointer items-start gap-3 overflow-hidden rounded-lg border border-outline-variant bg-surface p-4 transition-colors hover:bg-surface-container-lowest"
                            :class="strategy === 'adaptive' && 'ring-1 ring-primary border-primary'">
                            <input type="radio" value="adaptive" x-model="strategy"
                                class="mt-1 text-primary focus:ring-primary">
                            <div class="relative z-10">
                                <div class="flex items-center gap-2">
                                    <span class="block font-label-md text-label-md text-on-surface">Thích ứng</span>
                                    <span
                                        class="premium-gradient rounded px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">Premium</span>
                                </div>
                                <span class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">Tự động dồn
                                    ngày lỡ và ưu tiên chủ đề đang yếu.</span>
                            </div>
                        </label>
                    </div>
                </section>
            </div>
        </div>

        <!-- Right: Preview + criteria -->
        <div class="w-full shrink-0 lg:w-[320px]">
            <div class="sticky top-24 space-y-4">
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm">
                    <h3 class="mb-4 border-b border-outline-variant pb-4 font-headline-sm text-headline-sm text-on-surface">
                        Xem trước lộ trình</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">summarize</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Tổng khối lượng</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="totalQuestions().toLocaleString('vi-VN') + ' câu hỏi'"></span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">timer</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Thời gian dự kiến</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="daysUntilExam() + ' ngày (' + studyDaysUntilExam() + ' buổi học)'"></span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">flag</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Mục tiêu hàng ngày</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="intensity + ' câu/ngày'"></span>
                            </div>
                        </li>
                    </ul>
                    <p class="mt-4 rounded-lg bg-error-container/20 p-3 text-body-sm text-error"
                        x-show="daysUntilExam() > 0 && intensity >= 80" x-cloak>
                        Cường độ cao — cân nhắc giảm số câu mỗi ngày hoặc dời ngày thi.
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Tiêu chí phiên luyện</h3>
                    </div>
                    <div class="space-y-0">
                        <button type="button" @click="openModal('difficulty')"
                            class="group flex w-full cursor-pointer items-center justify-between border-b border-outline-variant px-5 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                <span class="font-medium">Độ khó</span>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-3">
                                <template x-if="difficulties.length === 0 || difficulties.length === difficultyOptions.length">
                                    <span class="text-sm text-on-surface-variant">Tất cả</span>
                                </template>
                                <template x-if="difficulties.length > 0 && difficulties.length < difficultyOptions.length">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <template x-for="chip in chips(difficulties, difficultyOptions)"
                                            :key="'difficulty-chip-' + chip">
                                            <span class="rounded bg-error-container px-3 py-1 text-[12px] font-medium text-on-error-container"
                                                x-text="chip"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </button>

                        <button type="button" @click="openModal('status')"
                            class="group flex w-full cursor-pointer items-center justify-between px-5 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                <span class="font-medium">Trạng thái</span>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-3">
                                <template x-if="questionStatuses.length === 0">
                                    <span class="text-sm text-on-surface-variant">Tất cả</span>
                                </template>
                                <template x-for="chip in chips(questionStatuses, statusOptions)"
                                    :key="'status-chip-' + chip">
                                    <span
                                        class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                        x-text="chip"></span>
                                </template>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ $cancelUrl }}"
                        class="flex-1 rounded-lg border border-outline-variant bg-surface-container-lowest px-4 py-3 text-center font-label-md text-label-md text-primary transition-colors hover:bg-surface-container">
                        Quay lại
                    </a>
                    <button type="submit"
                        class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary-container px-4 py-3 font-label-md text-label-md text-white shadow-sm transition-colors hover:bg-primary">
                        <span class="material-symbols-outlined text-sm">rocket_launch</span>
                        {{ $submitLabel }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bộ chọn taxonomy dùng chung với Ngân hàng câu hỏi -->
    <div x-show="activeFilter" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
        @click.self="activeFilter = null">
        <div class="flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-outline-variant p-4">
                <h3 class="font-headline-sm text-on-surface" x-text="({blueprint:'Ma trận đề thi',coreTopics:'Chủ đề lâm sàng',medicalNodes:'Chuyên khoa & danh mục y khoa',tags:'Tags'})[activeFilter] || 'Bộ lọc'"></h3>
                <button type="button" @click="activeFilter = null" class="rounded-full p-2 hover:bg-surface-container" aria-label="Đóng">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="custom-scrollbar space-y-4 overflow-y-auto p-4">
                @include('questionbank::partials.taxonomy-session-filter-modals')
            </div>
            <div class="flex items-center justify-between border-t border-outline-variant bg-surface-container-lowest p-4">
                <button type="button" @click="activeFilter === 'blueprint' ? clearBlueprint() : activeFilter === 'coreTopics' ? (coreClinicalTopicIds = []) : activeFilter === 'medicalNodes' ? (medicalTaxonomyNodeIds = []) : (tagIds = [])"
                    class="text-sm font-bold text-primary hover:underline">Đặt lại</button>
                <button type="button" @click="activeFilter = null" class="rounded-lg bg-primary px-8 py-2 font-bold text-white">Xong</button>
            </div>
        </div>
    </div>

    <!-- Scope picker modal -->
    <div x-show="modal" x-cloak x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-xl bg-white shadow-xl"
            @click.outside="closeModal()">
            <div class="flex items-center justify-between border-b border-outline-variant p-4">
                <h3 class="font-headline-sm text-headline-sm text-on-surface"
                    x-text="modalMeta[modal]?.title || ''"></h3>
                <button type="button" @click="closeModal()"
                    class="inline-flex size-10 items-center justify-center rounded-full transition-colors hover:bg-surface-container">
                    <span class="material-symbols-outlined text-[24px] leading-none">close</span>
                </button>
            </div>
            <div class="custom-scrollbar space-y-4 overflow-y-auto p-4">
                <template x-if="modalMeta[modal]?.search">
                    <div class="relative">
                        <span
                            class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                        <input type="text" x-model="search"
                            :placeholder="modalMeta[modal]?.search"
                            class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
                    </div>
                </template>

                <template x-if="modal === 'saved'">
                    <div class="space-y-1">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                            <input type="radio" name="saved_draft" :checked="draftSingle === null"
                                @change="draftSingle = null"
                                class="size-5 border-outline-variant text-primary focus:ring-primary">
                            <div>
                                <span class="block text-sm font-medium">Tất cả</span>
                                <span class="block text-xs text-on-surface-variant">Bao gồm câu hỏi chưa lưu.</span>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                            <input type="radio" name="saved_draft" :checked="draftSingle === 'saved'"
                                @change="draftSingle = 'saved'"
                                class="size-5 border-outline-variant text-primary focus:ring-primary">
                            <div>
                                <span class="block text-sm font-medium">Chỉ câu hỏi đã lưu</span>
                                <span class="block text-xs text-on-surface-variant">Chỉ lấy từ danh sách đã đánh dấu.</span>
                            </div>
                        </label>
                    </div>
                </template>

                <template x-if="modal === 'status'">
                    <div class="space-y-5">
                        <div class="space-y-1">
                            <p class="mb-2 text-[11px] font-bold tracking-wide text-on-surface-variant uppercase">
                                Bao gồm câu hỏi có trạng thái:
                            </p>
                            <template x-for="option in statusOptions" :key="option.id">
                                <label
                                    class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                    <input type="checkbox" :checked="draft.includes(option.id)"
                                        @change="toggleDraft(option.id)"
                                        class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="text-sm" x-text="option.name"></span>
                                </label>
                            </template>
                        </div>

                        <div class="border-t border-outline-variant pt-4">
                            <p class="mb-2 text-[11px] font-bold tracking-wide text-on-surface-variant uppercase">
                                Áp dụng bộ lọc trạng thái theo:
                            </p>
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                <input type="radio" name="status_mode_draft" value="all"
                                    x-model="draftStatusMode"
                                    class="size-5 border-outline-variant text-primary focus:ring-primary">
                                <span class="text-sm">Tất cả các lần làm câu hỏi trước đây</span>
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                <input type="radio" name="status_mode_draft" value="latest"
                                    x-model="draftStatusMode"
                                    class="size-5 border-outline-variant text-primary focus:ring-primary">
                                <span class="text-sm">Lần làm câu hỏi gần nhất</span>
                            </label>
                        </div>
                    </div>
                </template>

                <template x-if="modal === 'difficulty'">
                    <label class="mb-1 flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                        <input type="checkbox" :checked="draft.length === 0"
                            @change="if ($event.target.checked) draft = []"
                            class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                        <span class="text-sm">Tất cả độ khó</span>
                    </label>
                </template>

                <template x-if="modal !== 'saved' && modal !== 'status' && modalMeta[modal]?.multi">
                    <div class="space-y-1">
                        <template x-for="option in visibleOptions()" :key="option.id">
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                <input type="checkbox" :checked="draft.includes(option.id)" @change="toggleDraft(option.id)"
                                    class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="text-sm" x-text="option.name"></span>
                            </label>
                        </template>
                    </div>
                </template>

                <template
                    x-if="modal !== 'saved' && modal !== 'status' && modalMeta[modal] && !modalMeta[modal].multi">
                    <div class="space-y-1">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                            <input type="radio" name="single_draft" :checked="draftSingle === null"
                                @change="draftSingle = null"
                                class="size-5 border-outline-variant text-primary focus:ring-primary">
                            <span class="text-sm">Tất cả</span>
                        </label>
                        <template x-for="option in options()" :key="option.id">
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                <input type="radio" name="single_draft" :checked="draftSingle === option.id"
                                    @change="draftSingle = option.id"
                                    class="size-5 border-outline-variant text-primary focus:ring-primary">
                                <span class="text-sm" x-text="option.name"></span>
                            </label>
                        </template>
                    </div>
                </template>
            </div>
            <div class="flex items-center justify-between border-t border-outline-variant bg-surface-container-lowest p-4">
                <button type="button" class="text-sm font-bold text-primary hover:underline" @click="resetModal()">Đặt
                    lại</button>
                <button type="button"
                    class="rounded-lg bg-primary px-8 py-2 font-bold text-white transition-opacity hover:opacity-90"
                    @click="applyModal()">Xong</button>
            </div>
        </div>
    </div>
</form>
