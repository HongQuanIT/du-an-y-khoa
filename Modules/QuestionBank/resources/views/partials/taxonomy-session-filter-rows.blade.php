{{-- Blueprint / taxonomy / tag filter rows (uses parent Alpine scope) --}}
<button type="button" @click="openFilter('blueprint')"
    :disabled="source === 'weak_topics' || savedOnly"
    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
    <span class="flex items-center gap-4">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
        <span class="font-medium">Ma trận đề thi</span>
    </span>
    <span class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed"
        x-text="blueprintLabel()"></span>
</button>

<button type="button" @click="openFilter('blueprintSection')"
    :disabled="source === 'weak_topics' || savedOnly || !blueprintId"
    :class="(source === 'weak_topics' || savedOnly || !blueprintId) && 'opacity-50 pointer-events-none'"
    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
    <span class="flex items-center gap-4">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
        <span class="font-medium">Phần ma trận</span>
    </span>
    <span class="text-sm text-on-surface-variant" x-text="blueprintSectionLabel()"></span>
</button>

<button type="button" @click="openFilter('coreTopics')"
    :disabled="source === 'weak_topics' || savedOnly"
    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
    <span class="flex items-center gap-4">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
        <span class="font-medium">Chủ đề lâm sàng (128)</span>
    </span>
    <span class="text-sm text-on-surface-variant" x-text="coreTopicLabel()"></span>
</button>

<button type="button" @click="openFilter('medicalNodes')"
    :disabled="source === 'weak_topics' || savedOnly"
    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
    <span class="flex items-center gap-4">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
        <span class="font-medium">Danh mục y khoa</span>
    </span>
    <span class="text-sm text-on-surface-variant" x-text="medicalNodeLabel()"></span>
</button>

<button type="button" @click="openFilter('tags')"
    :disabled="source === 'weak_topics' || savedOnly"
    :class="(source === 'weak_topics' || savedOnly) && 'opacity-50 pointer-events-none'"
    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
    <span class="flex items-center gap-4">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
        <span class="font-medium">Tags</span>
    </span>
    <span class="text-sm text-on-surface-variant" x-text="tagLabel()"></span>
</button>
