<x-layouts.teach
    title="Duyệt câu hỏi"
    description="Xem nội dung và duyệt hoặc từ chối câu hỏi do Content Creator gửi.">
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
            Duyệt = chuyển sang <strong class="text-on-surface">chờ Admin xuất bản</strong> (không tăng version).
            Từ chối = trả về Content Creator kèm lý do.
        </p>
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
                <button type="submit" onclick="return confirm('Duyệt câu hỏi này và chuyển chờ xuất bản?')"
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

    <section class="rounded-2xl border border-outline-variant bg-surface p-5">
        <h3 class="mb-4 font-label-lg font-bold text-on-surface">Nội dung câu hỏi</h3>
        <p class="whitespace-pre-wrap text-sm leading-6 text-on-surface">{{ strip_tags((string) $question->stem) }}</p>

        @if ($question->stemImageUrl())
            <img src="{{ $question->stemImageUrl() }}" alt="Hình kèm câu hỏi"
                class="mt-4 max-h-72 rounded-xl border border-outline-variant object-contain">
        @endif

        <div class="mt-5 flex flex-wrap gap-2">
            @foreach ($question->medicalTaxonomyNodes as $node)
                <span class="inline-flex rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-semibold">
                    {{ $node->name }}
                </span>
            @endforeach
            <span class="inline-flex rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-semibold">
                {{ $question->difficulty->label() }}
            </span>
        </div>

        <h4 class="mt-5 text-sm font-bold text-on-surface">Đáp án</h4>
        <div class="mt-2 space-y-2">
            @foreach ($question->options as $index => $option)
                <div class="rounded-xl border px-3 py-2 text-sm {{ $option->is_correct ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-outline-variant bg-surface-container-lowest' }}">
                    <p>
                        <span class="mr-2 font-bold">{{ chr(65 + $index) }}.</span>{{ strip_tags((string) $option->content) }}
                        @if ($option->is_correct)
                            <span class="ml-2 text-xs font-bold">Đáp án đúng</span>
                        @endif
                    </p>
                    @if (filled(strip_tags((string) $option->explanation)))
                        <p class="mt-1 border-t border-current/10 pt-1 text-xs leading-5 text-on-surface-variant">
                            <span class="font-semibold">Giải thích:</span> {{ strip_tags((string) $option->explanation) }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <h4 class="mt-5 text-sm font-bold text-on-surface">Giải thích chung</h4>
        <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-on-surface-variant">
            {{ filled(strip_tags((string) $question->explanation)) ? strip_tags((string) $question->explanation) : 'Chưa nhập.' }}
        </p>

        <h4 class="mt-5 text-sm font-bold text-on-surface">Ý chính cần ghi nhớ</h4>
        @forelse ((array) ($question->key_info ?? []) as $item)
            <p class="mt-1 text-sm text-on-surface-variant">• {{ strip_tags((string) $item) }}</p>
        @empty
            <p class="mt-1 text-sm text-on-surface-variant">Chưa nhập.</p>
        @endforelse

        <h4 class="mt-5 text-sm font-bold text-on-surface">Kiến thức / Gợi ý</h4>
        <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-on-surface-variant">
            {{ filled(strip_tags((string) $question->attending_tip)) ? strip_tags((string) $question->attending_tip) : 'Chưa nhập.' }}
        </p>
    </section>
</x-layouts.teach>
