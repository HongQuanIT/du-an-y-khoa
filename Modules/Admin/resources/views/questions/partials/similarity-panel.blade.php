@if ($question->exists)
    <div class="rounded-2xl border border-outline-variant bg-surface p-4">
        <h2 class="mb-2 font-label-md font-semibold text-on-surface-variant">Kiểm tra trùng lặp</h2>
        <p class="mb-3 text-xs text-on-surface-variant">
            So sánh câu này với ngân hàng đề thi (từ ≥{{ (int) \Modules\QuestionBank\Enums\DuplicateSeverity::DISPLAY_THRESHOLD }}%).
            @if ($question->similarity_checked_at)
                · Lần quét: {{ $question->similarity_checked_at->diffForHumans() }}
            @endif
        </p>
        <a href="{{ route('admin.questions.duplicates.show', $question) }}"
           class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
            <span class="material-symbols-outlined text-[18px]">content_copy</span>
            Kiểm tra trùng lặp
        </a>
    </div>
@endif
