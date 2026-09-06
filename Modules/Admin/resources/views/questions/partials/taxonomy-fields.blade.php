@php
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

    $inferredCoreTopics = $question->exists
        ? $question->inferredCoreClinicalTopics()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'section_name' => $t->section?->name,
        ])->values()->all()
        : [];
@endphp

<div class="space-y-4 border-t border-outline-variant pt-3"
     x-data="questionTaxonomyPicker({
         selectedMedicalNodes: @js(collect($selectedMedicalNodes)->keyBy('id')->all()),
         selectedMedicalNodeIds: @js($selectedMedicalNodeIds),
         selectedTags: @js(collect($selectedTags)->keyBy('id')->all()),
         selectedTagIds: @js($selectedTagIds),
         inferredCoreTopics: @js($inferredCoreTopics),
         nodeTypeLabels: @js(\Modules\QuestionBank\Support\MedicalTaxonomyNodeTypes::LABELS),
         urls: {
             medicalNodes: @js(route('admin.taxonomy.lookups.medical-nodes')),
             tags: @js(route('admin.taxonomy.lookups.tags')),
         },
     })">
    <p class="text-[11px] leading-4 text-on-surface-variant">
        Gắn câu hỏi vào <strong>danh mục y khoa</strong> (bắt buộc) và thẻ.
        Chủ đề lâm sàng trên ma trận đề thi được suy ra tự động qua liên kết CCT ↔ danh mục/tag — không gắn trực tiếp từng câu.
    </p>

    @if (count($inferredCoreTopics) > 0)
        <div class="rounded-lg border border-outline-variant/70 bg-surface-container-low/60 p-3">
            <p class="mb-1.5 text-xs font-semibold text-on-surface-variant">Chủ đề lâm sàng (suy ra từ ma trận)</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($inferredCoreTopics as $topic)
                    <span class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                        {{ $topic['name'] }}
                        @if (! empty($topic['section_name']))
                            <span class="font-normal text-primary/70">· {{ $topic['section_name'] }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
            <p class="mt-1.5 text-[11px] text-on-surface-variant">
                Đổi mapping tại Ma trận đề thi → Liên kết. Ma trận mới chỉ cần map thêm, không gắn lại câu hỏi.
            </p>
        </div>
    @else
        <div class="rounded-lg border border-dashed border-outline-variant p-3 text-[11px] text-on-surface-variant">
            Chưa suy ra chủ đề lâm sàng nào. Map CCT ↔ danh mục/tag trên trang Ma trận đề thi sau khi gắn danh mục hoặc tag cho câu hỏi.
        </div>
    @endif

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
                        <span class="text-[10px] text-on-surface-variant" x-text="nodeTypeLabel(node.node_type)"></span>
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
        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Phân loại y khoa (khác) *</label>
        <input type="search" x-model="generalNodeSearch" @input.debounce.300ms="searchGeneralNodes()"
               placeholder="Tìm danh mục (chuyên khoa, hệ cơ quan, thủ thuật…)"
               class="mb-2 h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
        <div class="max-h-28 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
            <template x-for="node in generalNodeResults" :key="'gen-'+node.id">
                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-surface-container-low">
                    <input type="checkbox" :checked="selectedMedicalNodeIds.includes(node.id)"
                           @change="toggleMedicalNode(node)" class="size-4 rounded text-primary">
                    <span x-text="node.name"></span>
                    <span class="text-[10px] text-on-surface-variant" x-text="nodeTypeLabel(node.node_type)"></span>
                </label>
            </template>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="id in selectedMedicalNodeIds.filter(nid => !['disease','condition','symptom','sign','clinical_finding','lab_finding','imaging_finding','concept'].includes(selectedMedicalNodes[nid]?.node_type))" :key="'gen-chip-'+id">
                <span class="inline-flex items-center gap-1 rounded-lg bg-surface-container px-2 py-1 text-xs font-medium text-on-surface">
                    <span x-text="selectedMedicalNodes[id]?.name || ('#'+id)"></span>
                    <button type="button" @click="removeMedicalNode(id)" class="material-symbols-outlined text-[14px]">close</button>
                </span>
            </template>
        </div>
        <template x-for="id in selectedMedicalNodeIds" :key="'med-'+id">
            <input type="hidden" name="medical_taxonomy_node_ids[]" :value="id">
        </template>
        <p x-show="selectedMedicalNodeIds.length === 0" class="mt-1 text-xs text-error">Chọn ít nhất một mục danh mục y khoa.</p>
    </div>

    <div>
        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Thẻ</label>
        <input type="search" x-model="tagSearch" @input.debounce.300ms="searchTags()"
               placeholder="Tìm thẻ (ECG, cấp cứu…)"
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
            generalNodeSearch: '',
            generalNodeResults: [],
            tagSearch: '',
            tagResults: [],
            nodeGroups: [
                { key: 'disease', label: 'Bệnh / Tình trạng', placeholder: 'Tìm bệnh, hội chứng…', types: ['disease', 'condition'], search: '', results: [] },
                { key: 'symptom', label: 'Triệu chứng', placeholder: 'Tìm triệu chứng…', types: ['symptom'], search: '', results: [] },
                { key: 'finding', label: 'Phát hiện lâm sàng / xét nghiệm', placeholder: 'Tìm phát hiện, xét nghiệm, hình ảnh…', types: ['sign', 'clinical_finding', 'lab_finding', 'imaging_finding'], search: '', results: [] },
                { key: 'concept', label: 'Khái niệm', placeholder: 'Tìm khái niệm…', types: ['concept'], search: '', results: [] },
            ],
            nodeTypeLabel(type) {
                if (! type) return '';
                return this.nodeTypeLabels?.[type] || type;
            },
            async init() {
                for (const group of this.nodeGroups) {
                    await this.searchNodes(group);
                }
                await this.searchGeneralNodes();
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
