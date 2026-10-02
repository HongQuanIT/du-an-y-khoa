<div x-show="showConflict" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    role="dialog" aria-modal="true" aria-labelledby="flag-conflict-title">
    <div class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-6 shadow-lg"
        @click.outside="dismissConflict()">
        <h3 id="flag-conflict-title" class="text-base font-semibold text-on-surface">Quyết định đang lệch</h3>
        <p class="mt-3 text-sm leading-6 text-on-surface-variant">
            Quyết định này khác với reviewer còn lại. Hãy xem lại câu hỏi, đáp án và checklist.
            Giữ quyết định sẽ ghi nhận cờ đang chọn. Đổi cờ để chọn lại, rồi gửi quyết định mới.
        </p>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low"
                @click="dismissConflict()">
                Đổi cờ
            </button>
            <button type="submit" form="{{ $formId }}" data-keep="1"
                class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                Giữ quyết định
            </button>
        </div>
    </div>
</div>
