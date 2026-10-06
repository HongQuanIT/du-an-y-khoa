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

    @if ($canDecide && ($canApprove || $canReject))
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
            @if ($canReject)
            <form id="reject-review-form" method="post" action="{{ route('teach.questions.reviews.reject', $question) }}">
                @csrf
                <input type="hidden" name="review_note" id="reject-review-note">
                <button type="button" data-question-confirm-form="reject-review-form"
                    data-question-confirm-title="Từ chối câu hỏi?"
                    data-question-confirm-message="Câu hỏi sẽ được trả về để Content Editor chỉnh sửa."
                    data-question-confirm-copy-from="review_note"
                    data-question-confirm-copy-to="reject-review-note"
                    class="inline-flex items-center gap-1 rounded-xl border border-rose-300 px-4 py-2.5 font-semibold text-rose-700 hover:bg-rose-50">
                    <span class="material-symbols-outlined text-[18px]">close</span>Từ chối
                </button>
            </form>
            @endif
            @if ($canApprove)
            <form id="approve-review-form" method="post" action="{{ route('teach.questions.reviews.approve', $question) }}">
                @csrf
                <button type="button" data-question-confirm-form="approve-review-form"
                    data-question-confirm-title="Duyệt chuyên môn câu hỏi?"
                    data-question-confirm-message="Xác nhận nội dung đã đạt yêu cầu chuyên môn."
                    class="inline-flex items-center gap-1 rounded-xl bg-primary px-4 py-2.5 font-semibold text-on-primary hover:bg-primary/90">
                    <span class="material-symbols-outlined text-[18px]">check</span>Duyệt chuyên môn
                </button>
            </form>
            @endif
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

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-2" data-testid="instructor-review-two-pane">
        <section class="min-w-0 rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm xl:sticky xl:top-4"
            aria-labelledby="instructor-published-title">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-outline-variant pb-4">
                <h3 id="instructor-published-title" class="font-label-lg font-bold text-on-surface">Bản đang dùng</h3>
                @if ($comparison['published_version'])
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                        v{{ $comparison['published_version'] }}
                    </span>
                @endif
            </div>

            @if ($comparison['can_compare'])
                @include('questionbank::partials.question-review-comparison-pane', [
                    'side' => 'published',
                    'comparison' => $comparison,
                    'empty' => 'Chưa nhập.',
                    'preserveRichText' => true,
                    'reviewerStyle' => true,
                    'highlightOptionChanges' => false,
                ])
            @else
                <div class="rounded-xl border border-dashed border-outline-variant bg-surface-container-low px-4 py-6 text-center text-sm text-on-surface-variant">
                    Câu mới — chưa có bản đang dùng.
                </div>
            @endif
        </section>

        <section class="min-w-0 rounded-2xl border border-primary/40 bg-primary/5 p-5 shadow-sm"
            aria-labelledby="instructor-proposed-title">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-primary/20 pb-4">
                <h3 id="instructor-proposed-title" class="font-label-lg font-bold text-on-surface">Bản cần duyệt</h3>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Hiện tại</span>
            </div>

            @include('questionbank::partials.question-review-comparison-pane', [
                'side' => 'proposed',
                'comparison' => $comparison,
                'empty' => 'Chưa nhập.',
                'preserveRichText' => true,
                'reviewerStyle' => true,
                'highlightOptionChanges' => false,
            ])
        </section>
    </div>

    <div x-data="{ open: false, formId: '', title: '', message: '' }"
        @question-confirm.window="
            formId = $event.detail.formId;
            title = $event.detail.title;
            message = $event.detail.message;
            open = true;
        ">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @keydown.escape.window="open = false">
                <div class="absolute inset-0 bg-on-surface/40" @click="open = false"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl"
                    role="alertdialog" aria-modal="true" aria-labelledby="question-action-confirm-title">
                    <h2 id="question-action-confirm-title" class="font-headline-sm font-bold text-on-surface" x-text="title"></h2>
                    <p class="mt-2 text-sm leading-6 text-on-surface-variant" x-text="message"></p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="open = false"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-outline-variant px-4 font-semibold text-on-surface-variant hover:bg-surface-container-low">
                            Hủy
                        </button>
                        <button type="button" @click="document.getElementById(formId)?.requestSubmit()"
                            class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 font-semibold text-on-primary hover:bg-primary/90">
                            Xác nhận
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
        if (! window.__questionActionConfirmBound) {
            window.__questionActionConfirmBound = true;

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-question-confirm-form]');
                if (! button) return;

                const source = document.getElementById(button.dataset.questionConfirmCopyFrom);
                const target = document.getElementById(button.dataset.questionConfirmCopyTo);
                if (source && target) target.value = source.value;

                event.preventDefault();
                window.dispatchEvent(new CustomEvent('question-confirm', {
                    detail: {
                        formId: button.dataset.questionConfirmForm,
                        title: button.dataset.questionConfirmTitle,
                        message: button.dataset.questionConfirmMessage,
                    },
                }));
            });
        }
    </script>
</x-layouts.teach>
