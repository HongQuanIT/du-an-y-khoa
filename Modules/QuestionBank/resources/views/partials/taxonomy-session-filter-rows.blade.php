{{-- Lesson (bài học) filter row (uses parent Alpine scope) --}}
<button type="button" @click="openFilter('lessons')"
    :disabled="taxonomyLocked()"
    :class="taxonomyLocked() && 'opacity-50 pointer-events-none'"
    class="group flex w-full items-center justify-between border-b border-outline-variant px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest">
    <span class="flex items-center gap-4">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
        <span class="font-medium">Bài học</span>
    </span>
    <span class="text-sm text-on-surface-variant" x-text="lessonLabel()"></span>
</button>
