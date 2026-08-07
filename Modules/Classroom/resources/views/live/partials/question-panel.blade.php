<div class="flex shrink-0 items-center justify-between border-b border-outline-variant px-4 py-3">
    <h2 class="font-semibold text-on-surface">Đề đang chữa</h2>
    <div class="flex items-center gap-2">
        @if ($canModerate)
            <button type="button" data-live-presenter-popout
                class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-2 py-1 text-xs text-on-surface-variant hover:bg-surface-container-low"
                title="Mở cửa sổ tham khảo trên màn hình phụ">
                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                Màn phụ
            </button>
        @endif
        <span data-q-index-label class="text-xs text-on-surface-variant">—</span>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-4">
    <div data-q-stem class="prose prose-sm max-w-none text-on-surface"></div>
    <ul data-q-options class="mt-4 space-y-2"></ul>
    <div data-q-explanation class="mt-4 hidden rounded-lg bg-primary/10 p-3 text-sm text-primary"></div>
</div>

@if ($canModerate)
    <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-outline-variant p-3">
        <button type="button" data-q-prev
            class="rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">Trước</button>
        <button type="button" data-q-next
            class="rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">Sau</button>
        <button type="button" data-q-toggle-answer
            class="ml-auto rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
            Hiện đáp án
        </button>
    </div>
    <div data-q-map class="flex max-h-24 flex-wrap gap-1 overflow-y-auto border-t border-outline-variant p-3"></div>
@endif
