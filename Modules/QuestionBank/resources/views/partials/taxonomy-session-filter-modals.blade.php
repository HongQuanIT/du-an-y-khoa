{{-- Modal panel for medical taxonomy session filter --}}
<div x-show="activeFilter === 'medicalNodes'" class="space-y-4">
    <div class="relative">
        <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
        <input type="search" x-model="taxonomySearch" @input.debounce.300ms="fetchMedicalNodes()"
            placeholder="Tìm chuyên khoa hoặc danh mục y khoa..."
            class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
    </div>
    <button type="button" @click="medicalTaxonomyNodeIds = []; medicalNodeLabels = {}; $nextTick(() => refreshCount())"
        class="flex w-full items-start gap-3 rounded-lg bg-surface-container-low p-3 text-left">
        <span class="material-symbols-outlined mt-0.5 text-primary">select_all</span>
        <span class="block text-sm font-bold">Tất cả chuyên khoa và danh mục</span>
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
