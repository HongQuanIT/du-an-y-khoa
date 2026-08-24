@php
    $selectedCoreTopics = $question->relationLoaded('coreClinicalTopics')
        ? $question->coreClinicalTopics->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'section_name' => $t->section?->name,
        ])->values()->all()
        : [];

    $selectedCoreTopicIds = collect(old(
        'core_clinical_topic_ids',
        collect($selectedCoreTopics)->pluck('id')->all(),
    ))->map(fn ($id) => (int) $id)->unique()->values()->all();

    $medicalNodes = $question->relationLoaded('medicalTaxonomyNodes')
        ? $question->medicalTaxonomyNodes
        : collect();

    $selectedMedicalNodes = $medicalNodes->map(fn ($n) => [
        'id' => $n->id,
        'name' => $n->name,
        'node_type' => $n->node_type,
    ])->values()->all();

    $selectedMedicalNodeIds = collect(old(
        'medical_taxonomy_node_ids',
        $medicalNodes->pluck('id')->all(),
    ))->map(fn ($id) => (int) $id)->unique()->values()->all();

    $selectedTags = $question->relationLoaded('tags')
        ? $question->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all()
        : [];

    $selectedTagIds = collect(old(
        'tag_ids',
        collect($selectedTags)->pluck('id')->all(),
    ))->map(fn ($id) => (int) $id)->unique()->values()->all();
@endphp

<div class="space-y-4 border-t border-outline-variant pt-3"
     x-data="questionTaxonomyPicker({
         selectedCoreTopics: @js(collect($selectedCoreTopics)->keyBy('id')->all()),
         selectedCoreTopicIds: @js($selectedCoreTopicIds),
         selectedMedicalNodes: @js(collect($selectedMedicalNodes)->keyBy('id')->all()),
         selectedMedicalNodeIds: @js($selectedMedicalNodeIds),
         selectedTags: @js(collect($selectedTags)->keyBy('id')->all()),
         selectedTagIds: @js($selectedTagIds),
         urls: {
             blueprints: @js(route('admin.taxonomy.lookups.blueprints')),
             sections: @js(url('/admin/taxonomy/lookups/blueprints')),
             coreTopics: @js(route('admin.taxonomy.lookups.core-topics.search')),
             medicalNodes: @js(route('admin.taxonomy.lookups.medical-nodes')),
             tags: @js(route('admin.taxonomy.lookups.tags')),
         },
     })">
    <p class="text-[11px] leading-4 text-on-surface-variant">
        Phân loại độc lập: ma trận đề thi (17 đề mục / 128 vấn đề), medical taxonomy, và tags.
    </p>

    {{-- Blueprint core clinical topics --}}
    <div>
        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Chủ đề lâm sàng (Blueprint) *</label>
        <div class="mb-2 grid grid-cols-1 gap-2">
            <select x-model="blueprintId" @change="loadSections()"
                    class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                <option value="">— Chọn kỳ thi / blueprint —</option>
                <template x-for="bp in blueprints" :key="bp.id">
                    <option :value="bp.id" x-text="bp.name"></option>
                </template>
            </select>
            <select x-model="sectionId" @change="loadSectionTopics()" :disabled="!blueprintId"
                    class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm disabled:opacity-50">
                <option value="">— Chọn đề mục (section) —</option>
                <template x-for="sec in sections" :key="sec.id">
                    <option :value="sec.id" x-text="sec.name"></option>
                </template>
            </select>
        </div>
        <input type="search" x-model="coreTopicSearch" @input.debounce.300ms="searchCoreTopics()"
               placeholder="Hoặc tìm chủ đề lâm sàng (≥2 ký tự)..."
               class="mb-2 h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
        <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
            <template x-for="topic in coreTopicResults" :key="topic.id">
                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-surface-container-low">
                    <input type="checkbox" :checked="selectedCoreTopicIds.includes(topic.id)"
                           @change="toggleCoreTopic(topic)" class="size-4 rounded text-primary">
                    <span class="min-w-0 flex-1" x-text="topic.name"></span>
                    <span class="text-[10px] text-on-surface-variant" x-text="topic.section_name || ''"></span>
                </label>
            </template>
            <p x-show="coreTopicResults.length === 0" class="px-2 py-1 text-xs text-on-surface-variant">Chọn section hoặc tìm kiếm.</p>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="id in selectedCoreTopicIds" :key="'core-chip-'+id">
                <span class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                    <span x-text="selectedCoreTopics[id]?.name || ('#'+id)"></span>
                    <button type="button" @click="removeCoreTopic(id)" class="material-symbols-outlined text-[14px]">close</button>
                </span>
            </template>
        </div>
        <template x-for="id in selectedCoreTopicIds" :key="'core-'+id">
            <input type="hidden" name="core_clinical_topic_ids[]" :value="id">
        </template>
    </div>

    {{-- Typed medical taxonomy pickers --}}
    <template x-for="group in nodeGroups" :key="group.key">
        <div>
            <label class="mb-1 block text-xs font-semibold text-on-surface-variant" x-text="group.label"></label>
            <input type="search" x-model="group.search" @input.debounce.300ms="searchNodes(group)"
                   :placeholder="group.placeholder"
                   class="mb-2 h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
            <div class="max-h-28 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
                <template x-for="node in group.results" :key="group.key+'-'+node.id">
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-surface-container-low">
                        <input type="checkbox" :checked="selectedMedicalNodeIds.includes(node.id)"
                               @change="toggleMedicalNode(node)" class="size-4 rounded text-primary">
                        <span class="min-w-0 flex-1" x-text="node.name"></span>
                        <span class="text-[10px] uppercase text-on-surface-variant" x-text="node.node_type || ''"></span>
                    </label>
                </template>
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <template x-for="id in selectedMedicalNodeIds.filter(nid => group.types.includes(selectedMedicalNodes[nid]?.node_type))" :key="group.key+'-chip-'+id">
                    <span class="inline-flex items-center gap-1 rounded-lg bg-surface-container px-2 py-1 text-xs font-medium text-on-surface">
                        <span x-text="selectedMedicalNodes[id]?.name || ('#'+id)"></span>
                        <button type="button" @click="removeMedicalNode(id)" class="material-symbols-outlined text-[14px]">close</button>
                    </span>
                </template>
            </div>
        </div>
    </template>

    <div>
        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Medical taxonomy (khác)</label>
        <input type="search" x-model="generalNodeSearch" @input.debounce.300ms="searchGeneralNodes()"
               placeholder="Tìm node (specialty, system, procedure…)"
               class="mb-2 h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
        <div class="max-h-28 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
            <template x-for="node in generalNodeResults" :key="'gen-'+node.id">
                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-surface-container-low">
                    <input type="checkbox" :checked="selectedMedicalNodeIds.includes(node.id)"
                           @change="toggleMedicalNode(node)" class="size-4 rounded text-primary">
                    <span x-text="node.name"></span>
                    <span class="text-[10px] uppercase text-on-surface-variant" x-text="node.node_type || ''"></span>
                </label>
            </template>
        </div>
        <template x-for="id in selectedMedicalNodeIds" :key="'med-'+id">
            <input type="hidden" name="medical_taxonomy_node_ids[]" :value="id">
        </template>
    </div>

    <div>
        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Tags</label>
        <input type="search" x-model="tagSearch" @input.debounce.300ms="searchTags()"
               placeholder="Tìm tag (ECG, Emergency…)"
               class="mb-2 h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
        <div class="max-h-28 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
            <template x-for="tag in tagResults" :key="tag.id">
                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-surface-container-low">
                    <input type="checkbox" :checked="selectedTagIds.includes(tag.id)"
                           @change="toggleTag(tag)" class="size-4 rounded text-primary">
                    <span x-text="tag.name"></span>
                </label>
            </template>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="id in selectedTagIds" :key="'tag-chip-'+id">
                <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    <span x-text="selectedTags[id]?.name || ('#'+id)"></span>
                    <button type="button" @click="removeTag(id)" class="material-symbols-outlined text-[14px]">close</button>
                </span>
            </template>
        </div>
        <template x-for="id in selectedTagIds" :key="'tag-'+id">
            <input type="hidden" name="tag_ids[]" :value="id">
        </template>
    </div>
</div>

<script>
    function questionTaxonomyPicker(config) {
        return {
            ...config,
            blueprints: [],
            sections: [],
            blueprintId: '',
            sectionId: '',
            coreTopicSearch: '',
            coreTopicResults: [],
            generalNodeSearch: '',
            generalNodeResults: [],
            tagSearch: '',
            tagResults: [],
            nodeGroups: [
                { key: 'disease', label: 'Disease / Condition', placeholder: 'Tìm disease…', types: ['disease', 'condition'], search: '', results: [] },
                { key: 'symptom', label: 'Symptoms', placeholder: 'Tìm symptom…', types: ['symptom'], search: '', results: [] },
                { key: 'finding', label: 'Clinical / Lab findings', placeholder: 'Tìm finding…', types: ['sign', 'clinical_finding', 'lab_finding', 'imaging_finding'], search: '', results: [] },
                { key: 'concept', label: 'Concepts', placeholder: 'Tìm concept…', types: ['concept'], search: '', results: [] },
            ],
            async init() {
                await this.loadBlueprints();
                for (const group of this.nodeGroups) {
                    await this.searchNodes(group);
                }
            },
            async loadBlueprints() {
                const res = await fetch(this.urls.blueprints);
                const json = await res.json();
                this.blueprints = json.data ?? [];
                if (this.blueprints.length === 1) {
                    this.blueprintId = String(this.blueprints[0].id);
                    await this.loadSections();
                }
            },
            async loadSections() {
                this.sections = [];
                this.sectionId = '';
                this.coreTopicResults = [];
                if (!this.blueprintId) return;
                const res = await fetch(`${this.urls.sections}/${this.blueprintId}/sections`);
                const json = await res.json();
                this.sections = json.data ?? [];
            },
            async loadSectionTopics() {
                if (!this.sectionId) { this.coreTopicResults = []; return; }
                const res = await fetch(`${this.urls.coreTopics}?blueprint_section_id=${this.sectionId}`);
                const json = await res.json();
                this.coreTopicResults = json.data ?? [];
            },
            async searchCoreTopics() {
                const q = this.coreTopicSearch.trim();
                if (q.length < 2) {
                    if (this.sectionId) await this.loadSectionTopics();
                    else this.coreTopicResults = [];
                    return;
                }
                const params = new URLSearchParams({ q });
                if (this.blueprintId) params.set('blueprint_id', this.blueprintId);
                if (this.sectionId) params.set('blueprint_section_id', this.sectionId);
                const res = await fetch(`${this.urls.coreTopics}?${params}`);
                const json = await res.json();
                this.coreTopicResults = json.data ?? [];
            },
            toggleCoreTopic(topic) {
                const idx = this.selectedCoreTopicIds.indexOf(topic.id);
                if (idx >= 0) {
                    this.selectedCoreTopicIds.splice(idx, 1);
                    delete this.selectedCoreTopics[topic.id];
                } else {
                    this.selectedCoreTopicIds.push(topic.id);
                    this.selectedCoreTopics[topic.id] = topic;
                }
            },
            removeCoreTopic(id) {
                this.selectedCoreTopicIds = this.selectedCoreTopicIds.filter(x => x !== id);
                delete this.selectedCoreTopics[id];
            },
            async searchNodes(group) {
                const q = group.search.trim();
                const params = new URLSearchParams({ node_type: group.types.join(',') });
                if (q.length >= 1) params.set('q', q);
                const res = await fetch(`${this.urls.medicalNodes}?${params}`);
                const json = await res.json();
                group.results = json.data ?? [];
            },
            async searchGeneralNodes() {
                const q = this.generalNodeSearch.trim();
                const url = q.length >= 1
                    ? `${this.urls.medicalNodes}?q=${encodeURIComponent(q)}`
                    : this.urls.medicalNodes;
                const res = await fetch(url);
                const json = await res.json();
                this.generalNodeResults = (json.data ?? []).filter(n => !['disease','condition','symptom','sign','clinical_finding','lab_finding','imaging_finding','concept'].includes(n.node_type));
            },
            toggleMedicalNode(node) {
                const idx = this.selectedMedicalNodeIds.indexOf(node.id);
                if (idx >= 0) {
                    this.selectedMedicalNodeIds.splice(idx, 1);
                    delete this.selectedMedicalNodes[node.id];
                } else {
                    this.selectedMedicalNodeIds.push(node.id);
                    this.selectedMedicalNodes[node.id] = node;
                }
            },
            removeMedicalNode(id) {
                this.selectedMedicalNodeIds = this.selectedMedicalNodeIds.filter(x => x !== id);
                delete this.selectedMedicalNodes[id];
            },
            async searchTags() {
                const q = this.tagSearch.trim();
                if (q.length < 1) { this.tagResults = []; return; }
                const res = await fetch(`${this.urls.tags}?q=${encodeURIComponent(q)}`);
                const json = await res.json();
                this.tagResults = json.data ?? [];
            },
            toggleTag(tag) {
                const idx = this.selectedTagIds.indexOf(tag.id);
                if (idx >= 0) {
                    this.selectedTagIds.splice(idx, 1);
                    delete this.selectedTags[tag.id];
                } else {
                    this.selectedTagIds.push(tag.id);
                    this.selectedTags[tag.id] = tag;
                }
            },
            removeTag(id) {
                this.selectedTagIds = this.selectedTagIds.filter(x => x !== id);
                delete this.selectedTags[id];
            },
        };
    }
</script>
