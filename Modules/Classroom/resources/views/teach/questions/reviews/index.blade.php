<x-layouts.teach
    title="Duyệt câu hỏi"
    description="Hàng đợi giảng viên duyệt nội dung câu hỏi trước khi Admin xuất bản.">
    <header class="mb-6 rounded-xl border border-outline-variant bg-surface px-5 py-6 md:px-6">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary">Kiểm duyệt nội dung</p>
            <h2 class="mt-1 font-headline-sm text-headline-sm font-bold text-on-surface">Danh sách duyệt câu hỏi</h2>
            <p class="mt-2 text-sm leading-6 text-on-surface-variant">
                Bạn duyệt chuyên môn (lớp 1). Admin có quyền xuất bản mới publish và tăng version — không duyệt thay bạn.
            </p>
        </div>
    </header>

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

    @php
        $tabs = [
            'pending' => ['label' => 'Chờ duyệt', 'count' => $stats['pending']],
            'approved' => ['label' => 'Đã duyệt', 'count' => $stats['approved']],
            'rejected' => ['label' => 'Đã từ chối', 'count' => $stats['rejected']],
        ];
    @endphp

    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Lọc trạng thái duyệt">
        @foreach ($tabs as $key => $meta)
            <a href="{{ route('teach.questions.reviews.index', array_filter(['tab' => $key, 'q' => request('q')])) }}"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition-colors
                    {{ $tab === $key
                        ? 'border-primary bg-primary text-on-primary'
                        : 'border-outline-variant bg-surface text-on-surface-variant hover:bg-surface-container-low' }}">
                {{ $meta['label'] }}
                <span class="tabular-nums {{ $tab === $key ? 'text-on-primary/80' : 'text-on-surface-variant' }}">{{ $meta['count'] }}</span>
            </a>
        @endforeach
    </nav>

    <form method="get" action="{{ route('teach.questions.reviews.index') }}" class="mb-6">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label for="review-search" class="sr-only">Tìm câu hỏi</label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <input id="review-search" type="search" name="q" value="{{ request('q') }}"
                placeholder="Tìm theo mã hoặc nội dung stem…"
                class="min-h-11 w-full flex-1 rounded-lg border border-outline-variant bg-surface px-4 text-sm text-on-surface placeholder:text-on-surface-variant focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
            <button type="submit"
                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-on-primary hover:opacity-90">
                Tìm kiếm
            </button>
        </div>
    </form>

    <section aria-labelledby="review-list-title">
        <div class="mb-4">
            <h2 id="review-list-title" class="font-title-lg text-title-lg font-bold text-on-surface">
                {{ $tabs[$tab]['label'] ?? 'Danh sách' }}
            </h2>
            @if (! $questions->isEmpty())
                <p class="mt-1 text-sm text-on-surface-variant">
                    Hiển thị {{ $questions->firstItem() }}–{{ $questions->lastItem() }} trong {{ $questions->total() }} câu.
                </p>
            @endif
        </div>

        @if ($questions->isEmpty())
            <div class="rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-14 text-center">
                <span class="material-symbols-outlined text-[44px] text-on-surface-variant" aria-hidden="true">fact_check</span>
                <h3 class="mt-3 font-title-md text-title-md font-semibold text-on-surface">
                    @if ($tab === 'approved')
                        Chưa có câu bạn đã duyệt
                    @elseif ($tab === 'rejected')
                        Chưa có câu bạn đã từ chối
                    @else
                        Không có câu chờ duyệt
                    @endif
                </h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-on-surface-variant">
                    @if ($tab === 'pending')
                        Khi Content Creator gửi câu hỏi, chúng sẽ xuất hiện tại đây.
                    @else
                        Các quyết định duyệt của bạn sẽ được lưu tại tab này để xem lại.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <ul class="divide-y divide-outline-variant" role="list">
                    @foreach ($questions as $question)
                        <li>
                            <a href="{{ route('teach.questions.reviews.show', $question) }}"
                                class="flex flex-col gap-3 px-4 py-4 transition-colors hover:bg-surface-container-low sm:flex-row sm:items-start sm:justify-between sm:px-5">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($question->code)
                                            <span class="rounded-md bg-surface-container-high px-2 py-0.5 text-xs font-semibold text-on-surface">{{ $question->code }}</span>
                                        @endif
                                        <span @class([
                                            'rounded-md px-2 py-0.5 text-xs font-semibold',
                                            'bg-amber-100 text-amber-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview,
                                            'bg-sky-100 text-sky-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::PendingPublish,
                                            'bg-emerald-100 text-emerald-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Published,
                                            'bg-red-100 text-red-800' => $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected,
                                        ])>{{ $question->status->label() }}</span>
                                        <span class="text-xs text-on-surface-variant">{{ $question->difficulty->label() }}</span>
                                    </div>
                                    <p class="mt-2 line-clamp-2 text-sm leading-6 text-on-surface">
                                        {{ \Illuminate\Support\Str::limit(strip_tags((string) $question->stem), 180) }}
                                    </p>
                                    <p class="mt-2 text-xs text-on-surface-variant">
                                        Người gửi:
                                        <span class="font-semibold text-on-surface">{{ $question->pendingReviewRequest?->requester?->name ?? $question->creator?->name ?? '—' }}</span>
                                        · {{ $question->updated_at?->format('d/m/Y H:i') }}
                                        @if ($question->publisher)
                                            · XB: <span class="font-semibold text-on-surface">{{ $question->publisher->name }}</span>
                                        @endif
                                    </p>
                                    @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected && filled($question->rejection_reason))
                                        <p class="mt-2 text-xs text-rose-700">Lý do: {{ \Illuminate\Support\Str::limit($question->rejection_reason, 120) }}</p>
                                    @endif
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary">
                                    {{ $tab === 'pending' ? 'Duyệt' : 'Xem' }}
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-6">
                {{ $questions->links() }}
            </div>
        @endif
    </section>
</x-layouts.teach>
