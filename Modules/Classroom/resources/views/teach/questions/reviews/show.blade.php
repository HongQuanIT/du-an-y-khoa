<x-layouts.teach
    title="Duyệt câu hỏi"
    description="Thẩm định chuyên môn câu hỏi trước khi đưa vào ngân hàng.">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('teach.questions.reviews.index') }}"
                class="flex size-9 items-center justify-center rounded-xl border border-outline-variant text-on-surface-variant hover:bg-surface-container-low"
                aria-label="Quay lại danh sách">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Duyệt câu hỏi</h2>
                <p class="mt-0.5 text-sm text-on-surface-variant">
                    Người gửi:
                    <span class="font-semibold text-on-surface">{{ $question->pendingReviewRequest?->requester?->name ?? $question->creator?->name ?? '—' }}</span>
                    · {{ $question->updated_at?->format('d/m/Y H:i') }}
                    @if ($question->code)
                        · <span class="font-semibold text-on-surface">{{ $question->code }}</span>
                    @endif
                    @if ($comparison['published_version'] ?? null)
                        · So sánh với bản xuất bản v{{ $comparison['published_version'] }}
                    @else
                        · Câu mới — chưa có bản xuất bản
                    @endif
                </p>
            </div>
        </div>
        <span @class([
            'inline-flex whitespace-nowrap rounded-full px-3 py-1 text-sm font-bold',
            'bg-amber-100 text-amber-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview,
            'bg-sky-100 text-sky-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::PendingPublish,
            'bg-emerald-100 text-emerald-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Published,
            'bg-red-100 text-red-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected,
        ])>
            {{ $question->status->label() }}
        </span>
    </div>

    @if (session('status'))
        <div role="status" aria-live="polite"
            class="mb-6 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary">
            <span class="material-symbols-outlined mt-0.5 text-[20px]" aria-hidden="true">check_circle</span>
            <p>{{ session('status') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div role="alert"
            class="mb-6 rounded-xl border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($canDecide)
    <div class="mb-6 rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
        <p class="mb-3 text-sm text-on-surface-variant">
            Bạn được mời thẩm định chuyên môn câu hỏi này. Hãy rà soát đề, đáp án và giải thích về tính chính xác y khoa.
            Duyệt khi nội dung đã đạt; từ chối kèm góp ý nếu cần biên tập lại.
        </p>
        <div class="mb-3">
            @include('questionbank::partials.instructor-review-flags', [
                'question' => $question,
                'hideNames' => $hidePeerVotes ?? false,
            ])
        </div>
        <label for="review_note" class="mb-2 block text-sm font-semibold text-on-surface">Ghi chú / lý do từ chối</label>
        <textarea id="review_note" form="approve-review-form" name="review_note" rows="3"
            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm"
            placeholder="Góp ý không bắt buộc khi duyệt; bắt buộc khi từ chối...">{{ old('review_note') }}</textarea>
        <div class="mt-3 flex flex-wrap justify-end gap-2">
            <form id="reject-review-form" method="post" action="{{ route('teach.questions.reviews.reject', $question) }}">
                @csrf
                <input type="hidden" name="review_note" id="reject-review-note">
                <button type="submit"
                    onclick="document.getElementById('reject-review-note').value = document.getElementById('review_note').value; return confirm('Từ chối câu hỏi này?')"
                    class="inline-flex items-center gap-1 rounded-xl border border-rose-300 px-4 py-2.5 font-semibold text-rose-700 hover:bg-rose-50">
                    <span class="material-symbols-outlined text-[18px]">close</span>Từ chối
                </button>
            </form>
            <form id="approve-review-form" method="post" action="{{ route('teach.questions.reviews.approve', $question) }}">
                @csrf
                <button type="submit" onclick="return confirm('Xác nhận duyệt chuyên môn câu hỏi này?')"
                    class="inline-flex items-center gap-1 rounded-xl bg-primary px-4 py-2.5 font-semibold text-on-primary hover:bg-primary/90">
                    <span class="material-symbols-outlined text-[18px]">check</span>Duyệt chuyên môn
                </button>
            </form>
        </div>
    </div>
    @else
    <div class="mb-6 rounded-2xl border border-outline-variant bg-surface-container-low p-5 text-sm text-on-surface-variant">
        <p class="font-semibold text-on-surface">Chỉ xem lại — không thể đổi quyết định tại đây.</p>
        @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected && filled($question->rejection_reason))
            <p class="mt-2 text-rose-700">Lý do từ chối: {{ $question->rejection_reason }}</p>
        @endif
        @if ($question->publisher)
            <p class="mt-2">Người xuất bản: <span class="font-semibold text-on-surface">{{ $question->publisher->name }}</span></p>
        @endif
    </div>
    @endif

    @include('questionbank::partials.question-review-comparison', ['comparison' => $comparison])
</x-layouts.teach>
