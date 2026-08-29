{{-- Overlay đề trong khung video; camera thu PiP góc phải. --}}
@if ($session->hasQuestionSet())
    <div data-live-stage-teach
        class="{{ ! empty($session->stage_teach) ? 'flex' : 'hidden' }} absolute inset-0 z-[15] flex-col bg-surface text-on-surface">
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant bg-surface-container-low/80 px-3 py-2">
            <p class="truncate text-sm font-semibold text-on-surface">Chế độ chữa đề</p>
            @if ($canModerate ?? false)
                <button type="button" data-live-stage-teach-toggle
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-xs font-medium text-on-surface hover:bg-surface-container-low"
                    title="Thoát chế độ chữa đề trên khung video">
                    <span class="material-symbols-outlined text-[16px]">close_fullscreen</span>
                    Thoát
                </button>
            @endif
        </div>
        <div data-live-question-panel class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('classroom::live.partials.question-panel', ['inStage' => true])
        </div>
    </div>
@endif
