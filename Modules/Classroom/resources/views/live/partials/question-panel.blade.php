<div class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant px-4 py-3">
    <h2 class="min-w-0 truncate font-semibold text-on-surface">Đề đang chữa</h2>
    <div class="flex shrink-0 items-center gap-2">
        @if ($canModerate && empty($inStage))
            <button type="button" data-live-stage-teach-toggle
                class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-2 py-1 text-xs text-on-surface-variant hover:bg-surface-container-low"
                title="Hiện đề trong khung video, camera thu góc phải — học viên nhìn cùng một khung">
                <span class="material-symbols-outlined text-[16px]">picture_in_picture_alt</span>
                <span data-live-stage-teach-label>Khung đề</span>
            </button>
        @endif
        @if ($canModerate && ! empty($inStage))
            <button type="button" data-live-stage-teach-toggle data-live-stage-teach-exit
                class="inline-flex items-center gap-1 rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-xs font-medium text-on-surface shadow-sm hover:bg-surface-container-low"
                title="Thoát chế độ chữa đề trên khung video"
                aria-label="Thoát chữa đề trên video">
                <span class="material-symbols-outlined text-[16px]">close_fullscreen</span>
                Thoát
            </button>
        @endif
        <span data-q-index-label class="text-xs text-on-surface-variant">—</span>
    </div>
</div>

<div class="min-h-0 flex-1 overflow-y-auto p-4">
    <div data-q-stem class="prose prose-sm max-w-none select-text text-on-surface"></div>
    <div data-q-stem-image class="mt-3 hidden"></div>
    <ul data-q-options class="mt-4 space-y-2"></ul>
    <div data-q-explanation class="mt-4 hidden rounded-lg bg-primary/10 p-3 text-sm text-primary"></div>
</div>

@if ($canModerate)
    <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-outline-variant p-3">
        <button type="button" data-q-prev
            class="rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">Trước</button>
        <button type="button" data-q-next
            class="rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">Sau</button>
        <button type="button" data-q-clear-marks
            class="rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-sm text-on-surface-variant hover:bg-surface-container-low">Xóa tô màu</button>
    </div>
    <div data-q-map class="flex max-h-24 flex-wrap gap-1 overflow-y-auto border-t border-outline-variant p-3"></div>
@endif
