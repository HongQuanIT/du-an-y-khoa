{{-- Modal panels for taxonomy session filters --}}
<div x-show="activeFilter === 'blueprint'" class="space-y-4">
    <div class="relative">
        <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
        <input type="search" x-model="taxonomySearch" @input.debounce.300ms="fetchBlueprints()"
            placeholder="Tìm ma trận đề thi..."
            class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
    </div>
    <button type="button" @click="clearBlueprint()"
        class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
        <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
        <span class="block text-sm font-bold">Tất cả ma trận</span>
    </button>
    <div class="max-h-72 space-y-1 overflow-y-auto">
        <template x-for="item in blueprintResults" :key="item.id">
            <button type="button" @click="selectBlueprint(item)"
                class="flex w-full items-center justify-between rounded-lg p-3 text-left hover:bg-surface-container-low"
                :class="blueprintId == item.id && 'bg-primary/5 ring-1 ring-primary'">
                <span class="text-sm font-medium" x-text="item.name"></span>
                <span x-show="blueprintId == item.id" class="material-symbols-outlined text-primary">check</span>
            </button>
        </template>
        <p x-show="!blueprintResults.length" class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">Không có ma trận đề thi.</p>
    </div>
</div>

<div x-show="activeFilter === 'blueprintSection'" class="space-y-4">
    <p class="text-sm text-on-surface-variant" x-show="!blueprintId">Chọn ma trận đề thi trước.</p>
    <template x-if="blueprintId">
        <div class="space-y-4">
            <div class="relative">
                <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                <input type="search" x-model="taxonomySearch" @input.debounce.300ms="fetchBlueprintSections()"
                    placeholder="Tìm phần..."
                    class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
            </div>
            <button type="button" @click="clearBlueprintSection()"
                class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
                <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
                <span class="block text-sm font-bold">Tất cả phần</span>
            </button>
            <div class="max-h-72 space-y-1 overflow-y-auto">
                <template x-for="item in blueprintSectionResults" :key="item.id">
                    <button type="button" @click="selectBlueprintSection(item)"
                        class="flex w-full items-center justify-between rounded-lg p-3 text-left hover:bg-surface-container-low"
                        :class="blueprintSectionId == item.id && 'bg-primary/5 ring-1 ring-primary'">
                        <span class="text-sm font-medium" x-text="item.name"></span>
                        <span x-show="blueprintSectionId == item.id" class="material-symbols-outlined text-primary">check</span>
                    </button>
                </template>
            </div>
        </div>
    </template>
</div>

<div x-show="activeFilter === 'coreTopics'" class="space-y-4">
    <div class="relative">
        <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
        <input type="search" x-model="taxonomySearch" @input.debounce.300ms="fetchCoreTopics()"
            placeholder="Tìm chủ đề lâm sàng (≥2 ký tự)..."
            class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
    </div>
    <button type="button" @click="coreClinicalTopicIds = []; coreClinicalTopicLabels = {}; $nextTick(() => refreshCount())"
        class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
        <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
        <span class="block text-sm font-bold">Tất cả chủ đề</span>
    </button>
    <div class="max-h-72 space-y-1 overflow-y-auto">
        <template x-for="item in coreTopicResults" :key="item.id">
            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                <input type="checkbox" :checked="coreClinicalTopicIds.includes(item.id)"
                    @change="toggleCoreTopic(item)" class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                <span class="text-sm">
                    <span x-text="item.name"></span>
                    <span x-show="item.section_name" class="ml-1 text-xs text-on-surface-variant" x-text="'(' + item.section_name + ')'"></span>
                </span>
            </label>
        </template>
        <p x-show="taxonomySearch.length >= 2 && !coreTopicResults.length" class="text-sm text-on-surface-variant">Không tìm thấy.</p>
    </div>
</div>

<div x-show="activeFilter === 'medicalNodes'" class="space-y-4">
    <div class="relative">
        <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
        <input type="search" x-model="taxonomySearch" @input.debounce.300ms="fetchMedicalNodes()"
            placeholder="Tìm node danh mục y khoa..."
            class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
    </div>
    <button type="button" @click="medicalTaxonomyNodeIds = []; medicalNodeLabels = {}; $nextTick(() => refreshCount())"
        class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
        <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
        <span class="block text-sm font-bold">Tất cả node</span>
    </button>
    <div class="max-h-72 space-y-1 overflow-y-auto">
        <template x-for="item in medicalNodeResults" :key="item.id">
            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                <input type="checkbox" :checked="medicalTaxonomyNodeIds.includes(item.id)"
                    @change="toggleMedicalNode(item)" class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                <span class="text-sm" x-text="item.name"></span>
                <span class="text-[10px] uppercase text-on-surface-variant" x-text="item.node_type || ''"></span>
            </label>
        </template>
    </div>
</div>

<div x-show="activeFilter === 'tags'" class="space-y-4">
    <div class="relative">
        <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
        <input type="search" x-model="taxonomySearch" @input.debounce.300ms="fetchTags()"
            placeholder="Tìm tag..."
            class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
    </div>
    <button type="button" @click="tagIds = []; tagLabels = {}; $nextTick(() => refreshCount())"
        class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
        <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
        <span class="block text-sm font-bold">Tất cả tag</span>
    </button>
    <div class="max-h-72 space-y-1 overflow-y-auto">
        <template x-for="item in tagResults" :key="item.id">
            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                <input type="checkbox" :checked="tagIds.includes(item.id)"
                    @change="toggleTag(item)" class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                <span class="text-sm" x-text="item.name"></span>
            </label>
        </template>
    </div>
</div>
