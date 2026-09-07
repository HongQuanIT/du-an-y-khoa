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
    $additionalTopicGroups = $additionalTopicGroups ?? [];
    $filters = $plan?->scopeFilters() ?? [
        'medical_taxonomy_node_ids' => [],
        'system_ids' => [],
        'discipline_ids' => [],
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

    $defaultQuestionStatuses = $plan === null
        ? ['unanswered', 'incorrect']
        : array_values($filters['question_statuses']);

    $initial = [
        'exam' => $defaultExam,
        'date' => old('exam_target_date', $plan?->exam_target_date?->toDateString() ?? ''),
        'specialtyIds' => array_values(array_map('intval', old(
            'discipline_ids',
            ($filters['discipline_ids'] ?? []) ?: array_intersect($selectedTopicIds, $specialtyIds),
        ))),
        'systemIds' => array_values(array_map('intval', old(
            'system_ids',
            ($filters['system_ids'] ?? []) ?: array_intersect($selectedTopicIds, $systemIdList !== [] ? $systemIdList : array_diff($selectedTopicIds, $specialtyIds)),
        ))),
        'additionalTopicIds' => array_values(array_map('intval', array_diff(
            $selectedTopicIds,
            $specialtyIds,
            $systemIdList,
        ))),
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
        'questionStatuses' => array_values(old('question_statuses', $defaultQuestionStatuses)),
        'questionStatusMode' => old('question_status_mode', $filters['question_status_mode']),
        'hoursPerDay' => (float) old('hours_per_day', $plan?->hours_per_day ?? 2),
        'days' => array_map('intval', old('study_days', $plan?->studyWeekdays() ?? [7, 1, 2, 3, 4, 5, 6])),
        'strategy' => old('strategy', $plan?->strategy->value ?? 'fixed'),
        'blueprintId' => old('blueprint_id', $filters['blueprint_id'] ?? null),
        'blueprintSectionId' => old('blueprint_section_id', $filters['blueprint_section_id'] ?? null),
        'coreClinicalTopicIds' => array_map('intval', old('core_clinical_topic_ids', $filters['core_clinical_topic_ids'] ?? [])),
        'tagIds' => array_map('intval', old('tag_ids', $filters['tag_ids'] ?? [])),
    ];

    $weekdays = [7 => 'CN', 1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7'];

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
    $examOptions = collect($exams)
        ->map(fn (array $exam, string $key) => ['id' => $key, 'name' => $exam['title']])
        ->values();
@endphp

<form method="POST" action="{{ $formAction }}" class="contents" x-ref="planForm"
    x-data="{
        step: 1,
        exam: @js($initial['exam']),
        examOptions: @js($examOptions),
        date: @js($initial['date']),
        specialtyIds: @js($initial['specialtyIds']),
        systemIds: @js($initial['systemIds']),
        additionalTopicIds: @js($initial['additionalTopicIds']),
        additionalTopicGroups: @js($additionalTopicGroups),
        activeAdditionalTopicGroup: null,
        examTags: @js($initial['examTags']),
        articles: @js($initial['articles']),
        symptoms: @js($initial['symptoms']),
        savedOnly: @js($initial['savedOnly']),
        difficulties: @js($initial['difficulties']),
        questionStatuses: @js($initial['questionStatuses']),
        questionStatusMode: @js($initial['questionStatusMode']),
        hoursPerDay: @js($initial['hoursPerDay']),
        questionsPerHour: 20,
        days: @js($initial['days']),
        strategy: @js($initial['strategy']),
        matching: null,
        questionBreakdown: {
            total_in_scope: 0,
            eligible_total: 0,
            unanswered: 0,
            incorrect: 0,
            correct_with_hints: 0,
            correct: 0,
        },
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
            specialty: { title: 'Chuyên ngành', search: 'Tìm chuyên ngành...', multi: true, source: 'specialtyOptions', target: 'specialtyIds' },
            additionalTopics: { title: 'Phân loại y khoa', search: 'Tìm trong phân loại...', multi: true, source: null, target: 'additionalTopicIds' },
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
        async refreshCount(adjustHours = false){
            await this.$nextTick();
            this.counting=true;
            try {
                const body=new FormData(this.$refs.planForm);
                body.set('mode','study'); body.set('source','custom'); body.set('count','1');
                body.delete('exam_tags[]');
                const r=await fetch(this.countUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':@js(csrf_token())},body});
                const j=await r.json();
                const data=j?.data??{};
                this.matching=r.ok?Number(data.pool_count??data.count??0):0;
                this.questionBreakdown={
                    total_in_scope:Number(data.total_in_scope??this.matching),
                    eligible_total:Number(data.eligible_total??this.matching),
                    unanswered:Number(data.unanswered??0),
                    incorrect:Number(data.incorrect??0),
                    correct_with_hints:Number(data.correct_with_hints??0),
                    correct:Number(data.correct??0),
                };
                if (adjustHours) this.applyRecommendedHours();
            } catch(e) {
                this.matching=0;
                this.questionBreakdown={total_in_scope:0,eligible_total:0,unanswered:0,incorrect:0,correct_with_hints:0,correct:0};
            } finally { this.counting=false }
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
        openAdditionalTopicGroup(key) {
            this.activeAdditionalTopicGroup = key;
            this.modalMeta.additionalTopics.title = this.additionalGroup(key)?.label || 'Phân loại y khoa';
            this.modal = 'additionalTopics';
            this.search = '';
            this.draft = [...this.additionalIdsForGroup(key)];
        },
        additionalGroup(key = this.activeAdditionalTopicGroup) {
            return this.additionalTopicGroups.find((group) => group.key === key) || null;
        },
        additionalIdsForGroup(key) {
            const ids = (this.additionalGroup(key)?.options || []).map((option) => option.id);
            return this.additionalTopicIds.filter((id) => ids.includes(id));
        },
        closeModal() { this.modal = null; },
        options() {
            if (this.modal === 'additionalTopics') return this.additionalGroup()?.options || [];
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
            } else if (this.modal === 'additionalTopics') {
                const groupIds = (this.additionalGroup()?.options || []).map((option) => option.id);
                this.additionalTopicIds = [
                    ...this.additionalTopicIds.filter((id) => !groupIds.includes(id)),
                    ...this.draft,
                ];
            } else if (meta.multi) {
                this[meta.target] = [...this.draft];
            } else {
                this[meta.target] = this.draftSingle;
            }
            this.closeModal();
            this.refreshCount();
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
            this.applyRecommendedHours();
        },
        availableWeekdays() {
            if (!this.date) return [];
            const target = new Date(this.date + 'T00:00:00');
            const cursor = new Date();
            cursor.setHours(0, 0, 0, 0);
            const available = [];
            let inspectedDays = 0;
            while (cursor <= target && inspectedDays < 2000) {
                const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
                if (!available.includes(iso)) available.push(iso);
                cursor.setDate(cursor.getDate() + 1);
                inspectedDays++;
            }
            return available;
        },
        isDayAvailable(day) {
            return this.availableWeekdays().includes(day);
        },
        syncDaysToDate() {
            this.days = this.availableWeekdays();
        },
        topicIds() { return [...new Set([...this.systemIds, ...this.specialtyIds, ...this.additionalTopicIds])]; },
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
        dailyQuestionTarget() {
            return Math.round(this.hoursPerDay * this.questionsPerHour);
        },
        studyCapacity() { return Math.floor(this.studyDaysUntilExam() * this.dailyQuestionTarget()); },
        totalQuestions() { return this.matching ?? 0; },
        selectedQuestions() { return Math.min(this.studyCapacity(), this.totalQuestions()); },
        coveragePercent() {
            if (this.totalQuestions() <= 0) return 0;
            return Math.min(100, (this.studyCapacity() / this.totalQuestions()) * 100);
        },
        deadlineRecommendedHours() {
            const remainingDays = this.daysUntilExam();
            const milestones = [
                { days: 1, hours: 10 },
                { days: 5, hours: 9 },
                { days: 10, hours: 8 },
                { days: 30, hours: 6 },
                { days: 60, hours: 5 },
                { days: 70, hours: 4.5 },
                { days: 90, hours: 4 },
                { days: 183, hours: 2 },
                { days: 366, hours: 1 },
                { days: 731, hours: 0.5 },
            ];
            if (remainingDays <= milestones[0].days) return milestones[0].hours;
            for (let i = 1; i < milestones.length; i++) {
                const right = milestones[i];
                if (remainingDays > right.days) continue;
                const left = milestones[i - 1];
                const progress = (remainingDays - left.days) / (right.days - left.days);
                const interpolated = left.hours + ((right.hours - left.hours) * progress);
                return Math.max(0.5, Math.round(interpolated * 2) / 2);
            }
            return 0.5;
        },
        recommendedHoursPerDay() {
            if (this.totalQuestions() <= 0 || this.days.length === 0) return 0;
            const availableDays = Math.max(1, this.availableWeekdays().length);
            const adjusted = this.deadlineRecommendedHours() * (availableDays / this.days.length);
            return Math.min(10, Math.ceil(adjusted * 2) / 2);
        },
        applyRecommendedHours() {
            const recommended = this.recommendedHoursPerDay();
            if (recommended <= 0) return;
            this.hoursPerDay = Math.max(0.5, Math.min(this.rangeMaximum(), recommended));
        },
        formattedHours(hours) {
            return Number(hours).toLocaleString('vi-VN', { maximumFractionDigits: 1 });
        },
        rangeMaximum() {
            return 10;
        },
        examName() {
            return (this.examOptions.find((option) => option.id === this.exam) || {}).name || 'kỳ thi đã chọn';
        },
        canContinue() {
            return !this.counting && this.date && this.daysUntilExam() > 0 && this.totalQuestions() > 0;
        },
        async goToSchedule() {
            if (!this.date || this.daysUntilExam() <= 0) return;
            await this.refreshCount(true);
            if (this.totalQuestions() <= 0) return;
            this.step = 2;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        daysUntilExam() {
            if (!this.date) return 0;
            const target = new Date(this.date + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return Math.max(0, Math.round((target - today) / 86400000));
        },
        isPoolInsufficient() { return this.matching !== null && this.totalQuestions() === 0; },
        canSubmit() { return !this.counting && this.days.length > 0 && this.date && this.hoursPerDay >= 0.5 && this.hoursPerDay <= 10 && !this.isPoolInsufficient() && this.studyDaysUntilExam() > 0; },
    }" x-init="refreshCount(@js($plan === null))" @change.debounce.300ms="refreshCount()" @keydown.escape.window="if (activeFilter) activeFilter = null; else if (modal) closeModal()">
    @csrf
    @isset($formMethod)
        @method($formMethod)
    @endisset

    <input type="hidden" name="exam_key" :value="exam">
    <input type="hidden" name="hours_per_day" :value="hoursPerDay">
    <input type="hidden" name="daily_goal_questions" :value="dailyQuestionTarget()">
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
    <template x-for="id in additionalTopicIds" :key="'topic-' + id">
        <input type="hidden" name="medical_taxonomy_node_ids[]" :value="id">
    </template>
    <template x-for="id in topicIds()" :key="'all-topic-' + id">
        <input type="hidden" name="topic_ids[]" :value="id">
    </template>
    <template x-for="id in systemIds" :key="'system-' + id">
        <input type="hidden" name="system_ids[]" :value="id">
    </template>
    <template x-for="id in specialtyIds" :key="'discipline-' + id">
        <input type="hidden" name="discipline_ids[]" :value="id">
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

    @if ($plan === null)
        <header class="mb-6 flex items-center justify-between gap-4">
            <h1
                class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface md:font-headline-lg md:text-headline-lg">
                Tạo kế hoạch học tập tùy chỉnh cho
                <span class="text-primary underline underline-offset-4" x-text="examName()"></span>
            </h1>
            <a href="{{ $cancelUrl }}" aria-label="Đóng"
                class="flex size-10 shrink-0 items-center justify-center rounded-full transition-colors hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-on-surface-variant">close</span>
            </a>
        </header>

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
            @if ($errors->any())
                <div class="border-b border-error/30 bg-error-container/20 px-6 py-4">
                    <ul class="space-y-1 text-body-sm text-error">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="border-b border-outline-variant px-6 py-3 text-center font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase"
                x-text="'Bước ' + step + ' / 2'"></div>

            <div x-show="step === 1" x-cloak>
                <div class="grid min-h-[600px] gap-10 p-6 md:p-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
                    <section class="flex flex-col">
                        <h2 class="font-headline-md text-headline-md text-on-surface">Cho chúng tôi biết mục tiêu của bạn</h2>
                        <p class="mt-3 max-w-md font-body-md text-body-md leading-6 text-on-surface-variant">
                            Nội dung ôn tập sẽ được cá nhân hóa theo lựa chọn của bạn. Hãy kiểm tra kỹ thông tin vì hệ
                            thống sẽ dùng chúng để tính lịch học phù hợp.
                        </p>
                        <div class="mt-10 flex min-h-56 items-center justify-center rounded-xl bg-surface-container-low"
                            aria-hidden="true">
                            <div class="relative flex items-end gap-5 text-primary">
                                <span class="material-symbols-outlined text-[88px] font-light">clinical_notes</span>
                                <span class="material-symbols-outlined mb-5 text-[112px] font-light">medical_services</span>
                                <span class="material-symbols-outlined text-[76px] font-light">stethoscope</span>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div>
                            <label for="study_plan_exam"
                                class="mb-2 block font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase">
                                Bạn đang ôn thi gì?
                            </label>
                            <select id="study_plan_exam" x-model="exam"
                                class="w-full rounded border border-outline-variant bg-surface px-3 py-3 text-body-md text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                                @foreach ($exams as $key => $exam)
                                    <option value="{{ $key }}">{{ $exam['title'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-7">
                            <label for="exam_target_date"
                                class="mb-2 block font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase">
                                Hoàn thành ôn tập vào ngày
                            </label>
                            <input id="exam_target_date" type="date" name="exam_target_date" x-model="date"
                                @change.stop="syncDaysToDate(); refreshCount(true)"
                                min="{{ now()->addDay()->toDateString() }}" required
                                class="w-full rounded border border-outline-variant bg-surface px-3 py-3 text-body-md text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>

                        <div class="mt-8">
                            <h3 class="mb-2 font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase">
                                Chọn chủ đề
                            </h3>
                            <div class="border-y border-outline-variant">
                                <button type="button" @click="openModal('systems')"
                                    class="flex w-full items-center justify-between border-b border-outline-variant px-2 py-4 text-left hover:bg-surface-container-low">
                                    <span class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-on-surface-variant">add</span>
                                        <span>Hệ cơ quan</span>
                                    </span>
                                    <span class="max-w-[55%] truncate text-sm text-on-surface-variant"
                                        x-text="systemIds.length === 0 ? 'Tất cả' : chips(systemIds, systemOptions).join(', ')"></span>
                                </button>
                                <button type="button" @click="openModal('specialty')"
                                    class="flex w-full items-center justify-between border-b border-outline-variant px-2 py-4 text-left hover:bg-surface-container-low">
                                    <span class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-on-surface-variant">add</span>
                                        <span>Chuyên ngành</span>
                                    </span>
                                    <span class="max-w-[55%] truncate text-sm text-on-surface-variant"
                                        x-text="specialtyIds.length === 0 ? 'Tất cả' : chips(specialtyIds, specialtyOptions).join(', ')"></span>
                                </button>
                                @foreach ($additionalTopicGroups as $group)
                                    <button type="button" @click="openAdditionalTopicGroup(@js($group['key']))"
                                        class="flex w-full items-center justify-between {{ $loop->last ? '' : 'border-b border-outline-variant' }} px-2 py-4 text-left hover:bg-surface-container-low">
                                        <span class="flex items-center gap-3">
                                            <span class="material-symbols-outlined text-on-surface-variant">add</span>
                                            <span>{{ $group['label'] }}</span>
                                        </span>
                                        <span class="max-w-[55%] truncate text-sm text-on-surface-variant"
                                            x-text="additionalIdsForGroup(@js($group['key'])).length === 0 ? 'Tất cả' : chips(additionalIdsForGroup(@js($group['key'])), @js($group['options'])).join(', ')"></span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <fieldset class="mt-8">
                            <legend class="mb-2 font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase">
                                Bao gồm câu hỏi tôi đã
                            </legend>
                            <div class="space-y-2">
                                @foreach ($statusOptions as $option)
                                    @php
                                        $dotClass = match ($option['id']) {
                                            'correct_with_hints' => 'bg-amber-500',
                                            'incorrect' => 'bg-error',
                                            'correct' => 'bg-primary',
                                            default => 'bg-on-surface-variant',
                                        };
                                    @endphp
                                    <label class="flex cursor-pointer items-center gap-3">
                                        <input type="checkbox" value="{{ $option['id'] }}" x-model="questionStatuses"
                                            class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                        <span class="size-2 rounded-full {{ $dotClass }}"></span>
                                        <span class="text-body-sm text-on-surface">{{ $option['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-3 text-body-sm leading-5 text-on-surface-variant">
                                Câu hỏi sẽ được sắp xếp ngẫu nhiên sau khi ưu tiên nội dung quan trọng, giúp mô phỏng kỳ
                                thi và tăng khả năng ghi nhớ.
                            </p>
                        </fieldset>

                        <div class="mt-8 flex justify-end">
                            <button type="button" @click="goToSchedule()" :disabled="!canContinue()"
                                :class="canContinue() ? 'bg-primary text-white hover:bg-primary/90' : 'cursor-not-allowed bg-outline-variant text-on-surface-variant opacity-60'"
                                class="rounded px-5 py-2.5 font-label-md text-label-md transition-colors">
                                Tiếp tục
                            </button>
                        </div>
                    </section>
                </div>
            </div>

            <div x-show="step === 2" x-cloak>
                <div class="grid min-h-[500px] gap-10 p-6 md:p-10 lg:grid-cols-[1fr_1fr] lg:gap-16">
                    <section class="flex flex-col">
                        <h2 class="font-headline-md text-headline-md text-on-surface">Thiết lập lịch học</h2>
                        <p class="mt-3 max-w-md font-body-md text-body-md leading-6 text-on-surface-variant">
                            Dựa trên mục tiêu đã chọn, hệ thống tính rằng bạn cần học
                            <strong class="font-semibold text-on-surface"
                                x-text="formattedHours(recommendedHoursPerDay()) + ' giờ, ' + days.length + ' ngày'"></strong>
                            mỗi tuần để hoàn thành kế hoạch trước ngày kết thúc.
                        </p>
                        <div class="mt-10 flex min-h-52 items-center justify-center rounded-xl bg-surface-container-low"
                            aria-hidden="true">
                            <div class="flex items-center gap-6 text-primary">
                                <span class="material-symbols-outlined text-[96px] font-light">fact_check</span>
                                <span class="material-symbols-outlined text-[118px] font-light">cardiology</span>
                            </div>
                        </div>
                    </section>

                    <section>
                        <label for="hours_per_day_range"
                            class="block font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase">
                            Số giờ học mỗi ngày
                        </label>
                        <output for="hours_per_day_range"
                            class="mt-2 inline-flex min-w-24 justify-center rounded bg-surface-container px-4 py-2 text-body-md text-on-surface-variant"
                            x-text="formattedHours(hoursPerDay) + ' giờ'"></output>
                        <input id="hours_per_day_range" type="range" min="0.5" max="10" step="0.5"
                            x-model.number="hoursPerDay"
                            class="mt-4 h-1.5 w-full cursor-pointer appearance-none rounded-lg bg-surface-variant accent-primary">
                        <p class="mt-4 text-body-sm leading-5 text-on-surface-variant">
                            Bao gồm thời gian làm câu hỏi và xem lại đáp án. Hệ thống giả định trung bình 20 câu hỏi mỗi giờ.
                        </p>
                        <p class="mt-2 text-body-sm leading-5 text-on-surface-variant" aria-live="polite">
                            Mục tiêu mỗi phiên: <strong class="font-semibold text-on-surface"
                                x-text="dailyQuestionTarget().toLocaleString('vi-VN') + ' câu'"></strong>.
                        </p>

                        <fieldset class="mt-8">
                            <legend class="mb-3 font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase">
                                Ngày học trong tuần
                            </legend>
                            <div class="grid grid-cols-2 gap-x-12 gap-y-2">
                                @foreach ($weekdays as $iso => $label)
                                    @php
                                        $fullDayLabel = match ($iso) {
                                            7 => 'Chủ nhật',
                                            1 => 'Thứ Hai',
                                            2 => 'Thứ Ba',
                                            3 => 'Thứ Tư',
                                            4 => 'Thứ Năm',
                                            5 => 'Thứ Sáu',
                                            6 => 'Thứ Bảy',
                                        };
                                    @endphp
                                    <label class="flex items-center gap-2.5 text-body-sm text-on-surface"
                                        :class="isDayAvailable({{ $iso }}) ? 'cursor-pointer' : 'cursor-not-allowed opacity-40'">
                                        <input type="checkbox" :checked="days.includes({{ $iso }})"
                                            :disabled="!isDayAvailable({{ $iso }})"
                                            @change="toggleDay({{ $iso }})"
                                            class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                        {{ $fullDayLabel }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="mt-8 text-body-sm leading-6 text-error"
                            x-show="totalQuestions() > 0 && (coveragePercent() < 100 || daysUntilExam() === 1)" x-cloak
                            aria-live="polite">
                            <template x-if="coveragePercent() < 100">
                                <p>
                                    Kế hoạch học tập này chỉ bao phủ <strong class="font-semibold"
                                        x-text="Math.round(coveragePercent()) + '% nội dung kỳ thi của bạn.'"></strong>
                                    Bạn vẫn có thể tạo kế hoạch và hệ thống sẽ đưa vào những nội dung quan trọng nhất.
                                    Hãy tăng thời gian học và số ngày học để bao phủ thêm nội dung. Hoặc quay lại và chọn
                                    ngày thi muộn hơn để tăng thời gian học.
                                </p>
                            </template>
                            <template x-if="coveragePercent() >= 100 && daysUntilExam() === 1">
                                <p>
                                    Ngày kết thúc là ngày mai nên lịch học đang rất gấp. Hệ thống đã đề xuất mức tối đa
                                    <span class="font-semibold">10 giờ/ngày</span> trong 2 ngày còn lại. Bạn nên chọn ngày
                                    kết thúc muộn hơn để có thời gian học và xem lại đáp án hợp lý hơn.
                                </p>
                            </template>
                        </div>

                        <div class="mt-10 flex justify-end gap-2">
                            <button type="button" @click="step = 1"
                                class="rounded border border-outline-variant bg-surface px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                                Quay lại
                            </button>
                            <button type="submit" :disabled="!canSubmit()"
                                :class="canSubmit() ? 'bg-primary text-white hover:bg-primary/90' : 'cursor-not-allowed bg-outline-variant text-on-surface-variant opacity-60'"
                                class="rounded px-5 py-2.5 font-label-md text-label-md transition-colors">
                                Tạo kế hoạch
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    @else
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
                            @change.stop="refreshCount(true)"
                            min="{{ now()->addDay()->toDateString() }}" required
                            class="w-full rounded-lg border border-outline-variant bg-surface px-4 py-3 font-body-md text-body-md transition-all focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                        <div class="mt-3 rounded-lg border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-sm text-on-surface-variant"
                            x-show="date" x-cloak aria-live="polite">
                            <p>
                                Còn <span class="font-semibold text-on-surface" x-text="daysUntilExam()"></span>
                                ngày đến kỳ thi.
                            </p>
                            <p class="mt-1" x-show="counting">Đang tính thời lượng học phù hợp...</p>
                            <p class="mt-1" x-show="!counting && totalQuestions() > 0 && studyDaysUntilExam() > 0">
                                Trong <span class="font-semibold text-on-surface"
                                    x-text="studyDaysUntilExam() + ' ngày học còn lại'"></span>, bạn cần học khoảng
                                <span class="font-semibold text-on-surface"
                                    x-text="formattedHours(recommendedHoursPerDay()) + ' giờ/ngày'"></span>
                                để hoàn thành toàn bộ nội dung.
                            </p>
                        </div>
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
                            <button type="button" @click="openModal('systems')"
                                class="group flex w-full cursor-pointer items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                <div class="flex shrink-0 items-center gap-4">
                                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                    <span class="font-medium">Hệ cơ quan</span>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-4">
                                    <template x-if="systemIds.length === 0">
                                        <span class="text-sm text-on-surface-variant">Tất cả</span>
                                    </template>
                                    <template x-for="chip in chips(systemIds, systemOptions)" :key="'system-chip-' + chip">
                                        <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                            x-text="chip"></span>
                                    </template>
                                </div>
                            </button>

                            <button type="button" @click="openModal('specialty')"
                                class="group flex w-full cursor-pointer items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                <div class="flex shrink-0 items-center gap-4">
                                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                    <span class="font-medium">Chuyên ngành</span>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-4">
                                    <template x-if="specialtyIds.length === 0">
                                        <span class="text-sm text-on-surface-variant">Tất cả</span>
                                    </template>
                                    <template x-for="chip in chips(specialtyIds, specialtyOptions)" :key="'specialty-chip-' + chip">
                                        <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                            x-text="chip"></span>
                                    </template>
                                </div>
                            </button>

                            @foreach ($additionalTopicGroups as $group)
                                <button type="button" @click="openAdditionalTopicGroup(@js($group['key']))"
                                    class="group flex w-full cursor-pointer items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                    <div class="flex shrink-0 items-center gap-4">
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                        <span class="font-medium">{{ $group['label'] }}</span>
                                    </div>
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-4">
                                        <template x-if="additionalIdsForGroup(@js($group['key'])).length === 0">
                                            <span class="text-sm text-on-surface-variant">Tất cả</span>
                                        </template>
                                        <template x-for="chip in chips(additionalIdsForGroup(@js($group['key'])), @js($group['options']))"
                                            :key="@js($group['key']) + '-chip-' + chip">
                                            <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                                x-text="chip"></span>
                                        </template>
                                    </div>
                                </button>
                            @endforeach

                            <button type="button" @click="openModal('status')"
                                class="group flex w-full cursor-pointer items-center justify-between px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                                <div class="flex shrink-0 items-center gap-4">
                                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                    <span class="font-medium">Trạng thái câu hỏi</span>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 pl-4">
                                    <template x-if="questionStatuses.length === 0">
                                        <span class="text-sm text-on-surface-variant">Tất cả</span>
                                    </template>
                                    <template x-for="chip in chips(questionStatuses, statusOptions)" :key="'scope-status-chip-' + chip">
                                        <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
                                            x-text="chip"></span>
                                    </template>
                                </div>
                            </button>
                        </div>
                    </div>
                </section>

                <div class="mb-10 h-px w-full bg-outline-variant"></div>

                <!-- Schedule -->
                <section>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                            <span class="material-symbols-outlined text-primary">speed</span>
                            Thiết lập lịch học
                        </h2>
                        <span class="rounded-full bg-primary-container/10 px-3 py-1 font-label-md text-label-md text-primary"
                            x-text="hoursPerDay + ' giờ/ngày · khoảng ' + Math.round(hoursPerDay * questionsPerHour) + ' câu'"></span>
                    </div>
                    <p class="mb-6 max-w-2xl text-body-md leading-6 text-on-surface-variant"
                        x-show="totalQuestions() > 0 && studyDaysUntilExam() > 0" x-cloak aria-live="polite">
                        Dựa trên mục tiêu đã chọn, hệ thống tính rằng bạn cần học
                        <span class="font-semibold text-on-surface"
                            x-text="formattedHours(recommendedHoursPerDay()) + ' giờ/ngày'"></span>,
                        <span class="font-semibold text-on-surface" x-text="days.length + ' ngày/tuần'"></span>
                        để hoàn thành kế hoạch trước ngày kết thúc.
                    </p>
                    <div class="mb-8 px-2">
                        <label for="hours_per_day_range" class="mb-3 block font-label-md text-label-md text-on-surface">
                            Số giờ học mỗi ngày
                        </label>
                        <input id="hours_per_day_range" type="range" min="0.5" max="10" step="0.5"
                            x-model.number="hoursPerDay"
                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-surface-variant accent-primary">
                        <div class="mt-2 flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                            <span>0,5 giờ</span>
                            <span>10 giờ</span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h3 class="mb-3 font-label-md text-label-md text-on-surface">Ngày học trong tuần</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($weekdays as $iso => $label)
                                <button type="button" @click="toggleDay({{ $iso }})"
                                    :aria-pressed="days.includes({{ $iso }})"
                                    :class="days.includes({{ $iso }}) ? 'bg-primary text-white border-primary shadow-sm' : 'bg-surface text-on-surface-variant border-outline-variant hover:bg-surface-container-low'"
                                    class="h-10 min-w-10 rounded-lg border font-label-md text-label-md transition-all">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-5 max-w-2xl text-body-sm leading-6 text-error"
                            x-show="totalQuestions() > 0 && coveragePercent() < 100" x-cloak>
                            Kế hoạch học tập này chỉ bao phủ <span class="font-semibold"
                                x-text="Math.round(coveragePercent()) + '%'"></span> nội dung kỳ thi. Hệ thống sẽ ưu tiên
                            những câu hỏi có giá trị ôn tập cao nhất. Hãy tăng số giờ học, thêm ngày học hoặc lùi ngày
                            kết thúc để bao phủ nhiều nội dung hơn.
                        </p>
                    </div>

                    <!-- Strategy -->
                    <div>
                        <h3 class="mb-3 font-label-md text-label-md text-on-surface">Chiến lược học tập</h3>
                        <div class="space-y-3">
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 transition-colors hover:border-primary/40">
                                <input type="radio" name="strategy_choice" value="fixed" x-model="strategy"
                                    class="mt-1 size-4 border-outline-variant text-primary focus:ring-primary">
                                <div>
                                    <span class="block font-label-md text-label-md text-on-surface">Cố định (Fixed)</span>
                                    <span class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">Chia đều
                                        khối lượng theo từng ngày học cố định.</span>
                                </div>
                            </label>
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 transition-colors hover:border-primary/40">
                                <input type="radio" name="strategy_choice" value="adaptive" x-model="strategy"
                                    class="mt-1 size-4 border-outline-variant text-primary focus:ring-primary">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-label-md text-label-md text-on-surface">Thích ứng
                                            (Adaptive)</span>
                                        <span
                                            class="premium-gradient rounded px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">Premium</span>
                                    </div>
                                    <span class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">Tự động dồn
                                        ngày lỡ và ưu tiên chủ đề đang yếu.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                </section>
            </div>
        </div>

        <!-- Right: Preview + criteria -->
        <div class="w-full shrink-0 lg:w-[320px]">
            <div class="sticky top-24 space-y-4">
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm"
                    aria-live="polite">
                    <h3 class="mb-4 border-b border-outline-variant pb-4 font-headline-sm text-headline-sm text-on-surface">
                        Xem trước lộ trình</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">summarize</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Tổng câu phù hợp trong ngân hàng</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="counting ? 'Đang kiểm tra kho...' : totalQuestions().toLocaleString('vi-VN') + ' câu hỏi'"></span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">timer</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Số ngày học thực tế</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="studyDaysUntilExam().toLocaleString('vi-VN') + ' ngày'"></span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">flag</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Khả năng học</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="studyCapacity().toLocaleString('vi-VN') + ' câu'"></span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">donut_large</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Mức độ bao phủ</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="Math.round(coveragePercent()) + '%'"></span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined mt-0.5 text-primary">checklist</span>
                            <div>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Số câu được đưa vào kế hoạch</span>
                                <span class="block font-label-md text-label-md text-on-surface"
                                    x-text="selectedQuestions().toLocaleString('vi-VN') + ' câu'"></span>
                            </div>
                        </li>
                    </ul>

                    <!-- Thông báo lỗi khi kho < 5 câu -->
                    <div class="mt-4 rounded-lg border border-error/30 bg-error-container/20 p-3 text-body-sm text-error"
                        x-show="isPoolInsufficient()" x-cloak>
                        <div class="mb-1 flex items-center gap-1.5 font-bold">
                            <span class="material-symbols-outlined text-[18px]">error</span>
                            Phạm vi chưa đủ câu hỏi
                        </div>
                        Không đủ câu hỏi phù hợp để tạo kế hoạch học tập với phạm vi hiện tại.
                    </div>

                </div>

                <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Tiêu chí phiên luyện</h3>
                    </div>
                    <div>
                        <button type="button" @click="openModal('difficulty')"
                            class="group flex w-full cursor-pointer items-center justify-between border-b border-outline-variant px-5 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                            <span class="flex items-center gap-3 font-medium">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                Độ khó
                            </span>
                            <span class="text-sm text-on-surface-variant"
                                x-text="difficulties.length === 0 || difficulties.length === difficultyOptions.length ? 'Tất cả' : chips(difficulties, difficultyOptions).join(', ')"></span>
                        </button>

                        <button type="button" @click="openModal('status')"
                            class="group flex w-full cursor-pointer items-center justify-between px-5 py-4 text-left transition-colors hover:bg-surface-container-lowest">
                            <span class="flex items-center gap-3 font-medium">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                Trạng thái
                            </span>
                            <span class="text-sm text-on-surface-variant"
                                x-text="questionStatuses.length === 0 ? 'Tất cả' : chips(questionStatuses, statusOptions).join(', ')"></span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ $cancelUrl }}"
                        class="flex-1 rounded-lg border border-outline-variant bg-surface-container-lowest px-4 py-3 text-center font-label-md text-label-md text-primary transition-colors hover:bg-surface-container">
                        Quay lại
                    </a>
                    <button type="submit"
                        :disabled="!canSubmit()"
                        :class="!canSubmit() ? 'opacity-50 cursor-not-allowed bg-outline-variant text-on-surface-variant' : 'bg-primary-container text-white shadow-sm hover:bg-primary'"
                        class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-3 font-label-md text-label-md transition-colors">
                        <span class="material-symbols-outlined text-sm">rocket_launch</span>
                        {{ $submitLabel }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

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
                    <div class="space-y-1">
                        <p class="mb-2 text-[11px] font-bold tracking-wide text-on-surface-variant uppercase">
                            Bao gồm câu hỏi theo kết quả gần nhất:
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
