<div class="flex items-center gap-2">
    <div class="flex flex-wrap items-center gap-1">
        <button type="button" data-live-react="heart"
            class="inline-flex size-8 items-center justify-center rounded-full bg-white/10 text-rose-300 transition hover:bg-rose-500/30 hover:text-rose-200"
            aria-label="Thả tim" title="Thả tim">
            <span class="material-symbols-outlined text-[18px]">favorite</span>
        </button>
        <button type="button" data-live-react="like"
            class="inline-flex size-8 items-center justify-center rounded-full bg-white/10 text-sky-300 transition hover:bg-sky-500/30 hover:text-sky-200"
            aria-label="Thả like" title="Thả like">
            <span class="material-symbols-outlined text-[18px]">thumb_up</span>
        </button>
    </div>

    <div data-lk-controls class="{{ ($tokenPayload['can_publish_audio'] ?? false) || ($tokenPayload['can_publish_video'] ?? false) || ($tokenPayload['can_publish_screen'] ?? false) ? 'flex' : 'hidden' }} flex-wrap items-center gap-1">
        <button type="button" data-lk-mic
            class="{{ ($tokenPayload['can_publish_audio'] ?? false) ? '' : 'hidden ' }}inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-40"
            aria-label="Micro" title="Bật/tắt micro">
            <span class="material-symbols-outlined text-[22px]">mic_off</span>
        </button>
        <button type="button" data-lk-cam
            class="{{ ($tokenPayload['can_publish_video'] ?? false) ? '' : 'hidden ' }}inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-40"
            aria-label="Camera">
            <span class="material-symbols-outlined text-[22px]">videocam</span>
        </button>
        <button type="button" data-lk-screen
            class="{{ ($tokenPayload['can_publish_screen'] ?? false) ? '' : 'hidden ' }}inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-40"
            aria-label="Chia sẻ màn hình" title="Chia sẻ slide/PDF/app ngoài (không cần khi chữa đề trong app)">
            <span class="material-symbols-outlined text-[22px]">present_to_all</span>
        </button>
        <button type="button" data-lk-teach
            class="hidden items-center gap-1 rounded-full bg-teal-600/80 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 md:inline-flex"
            title="Hiện đề trong khung video (camera góc phải) — học viên nhìn cùng một khung">
            <span class="material-symbols-outlined text-[18px]">menu_book</span>
            Chữa đề
        </button>
    </div>
    <button type="button" data-lk-leave
        class="inline-flex size-10 items-center justify-center rounded-full bg-red-600/80 text-white transition hover:bg-red-600"
        aria-label="Rời phòng">
        <span class="material-symbols-outlined text-[22px]">call_end</span>
    </button>
</div>
