@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, array{id: string, preview: string, stem_html: string, explanation: string, options: list<array{label: string, content: string, correct: bool, explanation: string}>, topic: ?string, difficulty: ?string, saved_at: string, available: bool}> $bookmarks */
@endphp

<x-layouts.app title="Câu hỏi đã lưu">
    <section class="mx-auto max-w-container-max p-4 sm:p-6 md:p-10" x-data="{
        selected: [],
        openId: null,
        toggleOpen(id, available) {
            if (!available) return;
            this.openId = this.openId === id ? null : id;
        },
        toggle(id, checked) {
            if (checked) {
                if (!this.selected.includes(id)) this.selected.push(id);
                return;
            }
            this.selected = this.selected.filter((item) => item !== id);
        },
        toggleAll(checked) {
            const boxes = this.$refs.list.querySelectorAll('[data-bookmark-id]');
            this.selected = checked ? Array.from(boxes).map((box) => box.dataset.bookmarkId) : [];
            boxes.forEach((box) => { box.checked = checked; });
        },
    }">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="mb-2 font-headline-md text-headline-md font-bold text-on-surface">Câu hỏi đã lưu</h1>
                <nav class="flex items-center gap-2 text-label-sm text-on-surface-variant">
                    <a class="transition-colors hover:text-primary" href="{{ route('qbank.index') }}">Ngân hàng câu hỏi</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="font-bold text-primary">Câu hỏi đã lưu</span>
                </nav>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('qbank.create', ['saved_only' => 1]) }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-outline-variant px-5 py-3 text-label-md font-bold text-on-surface transition-colors hover:bg-surface-container-low">
                    Tạo phiên từ tất cả câu đã lưu
                </a>
                <a href="{{ route('qbank.create') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-label-md font-bold text-white shadow-md transition-all hover:bg-primary/90 active:scale-95">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tạo phiên luyện tập
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-primary"
                role="status">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                <p class="font-medium">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-error/30 bg-error-container px-4 py-3 text-sm text-on-error-container">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($bookmarks->isEmpty())
            <div class="rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-16 text-center">
                <span class="material-symbols-outlined mb-3 text-[40px] text-outline">bookmark</span>
                <p class="mb-2 font-title-md text-title-md text-on-surface">Chưa có câu hỏi nào được lưu</p>
                <p class="mx-auto mb-6 max-w-md text-body-md text-on-surface-variant">
                    Khi làm bài, nhấn biểu tượng đánh dấu cạnh “Kiến thức” để lưu câu hỏi vào đây.
                </p>
                <a href="{{ route('qbank.create') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary/90">
                    Bắt đầu luyện tập
                </a>
            </div>
        @else
            <form method="post" action="{{ route('qbank.bookmarks.session') }}" class="space-y-4">
                @csrf
                <div class="flex flex-col gap-3 rounded-2xl border border-outline-variant bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-on-surface">
                        <input type="checkbox" class="size-4 rounded border-outline text-primary focus:ring-primary"
                            @change="toggleAll($event.target.checked)">
                        Chọn tất cả trên trang này
                    </label>
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition-opacity hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="selected.length === 0">
                        <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                        Tạo phiên từ câu đã chọn
                        <span x-text="selected.length ? '(' + selected.length + ')' : ''"></span>
                    </button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white" x-ref="list">
                    <ul class="divide-y divide-outline-variant">
                        @foreach ($bookmarks as $item)
                            <li class="p-4 md:p-5">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <input type="checkbox" name="question_ids[]" value="{{ $item['id'] }}"
                                            data-bookmark-id="{{ $item['id'] }}"
                                            class="mt-1 size-4 shrink-0 rounded border-outline text-primary focus:ring-primary"
                                            @click.stop
                                            @change="toggle('{{ $item['id'] }}', $event.target.checked)">
                                        <button type="button"
                                            class="min-w-0 flex-1 text-left {{ $item['available'] ? 'cursor-pointer' : 'cursor-default' }}"
                                            @click="toggleOpen('{{ $item['id'] }}', {{ $item['available'] ? 'true' : 'false' }})"
                                            @if ($item['available'])
                                                :aria-expanded="openId === '{{ $item['id'] }}'"
                                            @endif>
                                            <span class="mb-2 flex flex-wrap items-center gap-2">
                                                @if ($item['topic'])
                                                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">{{ $item['topic'] }}</span>
                                                @endif
                                                @if ($item['difficulty'])
                                                    <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 text-xs font-semibold text-on-surface-variant">{{ $item['difficulty'] }}</span>
                                                @endif
                                                @unless ($item['available'])
                                                    <span class="rounded-full bg-error/10 px-2.5 py-0.5 text-xs font-bold text-error">Không còn khả dụng</span>
                                                @endunless
                                                @if ($item['available'])
                                                    <span class="text-xs font-medium text-primary"
                                                        x-text="openId === '{{ $item['id'] }}' ? 'Thu gọn' : 'Xem câu hỏi & đáp án'"></span>
                                                @endif
                                            </span>
                                            <span class="block font-body-md text-body-md text-on-surface">{{ $item['preview'] }}</span>
                                            <span class="mt-2 block text-xs text-on-surface-variant">Lưu lúc {{ $item['saved_at'] }}</span>
                                        </button>
                                    </div>
                                    <button type="submit" form="unbookmark-{{ $item['id'] }}"
                                        class="inline-flex shrink-0 items-center gap-1 self-start rounded-lg border border-outline-variant px-3 py-2 text-xs font-bold text-on-surface-variant transition-colors hover:border-error/40 hover:bg-error-container hover:text-on-error-container">
                                        <span class="material-symbols-outlined text-[16px]">bookmark_remove</span>
                                        Bỏ lưu
                                    </button>
                                </div>

                                @if ($item['available'])
                                    <div x-show="openId === '{{ $item['id'] }}'" x-cloak
                                        class="mt-4 space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 md:p-5">
                                        <div class="prose prose-sm max-w-none font-body-md text-body-md leading-relaxed text-on-surface">
                                            {!! $item['stem_html'] !!}
                                        </div>
                                        <ul class="space-y-2">
                                            @foreach ($item['options'] as $option)
                                                <li class="rounded-xl border p-3 {{ $option['correct'] ? 'border-primary/30 bg-primary/5' : 'border-outline-variant bg-white' }}">
                                                    <div class="flex items-start gap-3">
                                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $option['correct'] ? 'bg-primary text-white' : 'border border-outline-variant text-on-surface-variant' }}">
                                                            {{ $option['label'] }}
                                                        </span>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="prose prose-sm max-w-none font-body-md text-body-md text-on-surface">
                                                                {!! $option['content'] !!}
                                                            </div>
                                                            @if ($option['correct'])
                                                                <p class="mt-1 text-xs font-bold text-primary">Đáp án đúng</p>
                                                            @endif
                                                            @if ($option['explanation'] !== '')
                                                                <div class="mt-2 prose prose-sm max-w-none text-sm text-on-surface-variant">
                                                                    {!! $option['explanation'] !!}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if ($item['explanation'] !== '')
                                            <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
                                                <h3 class="mb-2 flex items-center gap-2 font-label-md text-label-md font-bold text-on-surface">
                                                    <span class="material-symbols-outlined text-[18px] text-primary">lightbulb</span>
                                                    Giải thích
                                                </h3>
                                                <div class="prose prose-sm max-w-none font-body-md text-body-md text-on-surface">
                                                    {!! $item['explanation'] !!}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </form>

            @foreach ($bookmarks as $item)
                <form id="unbookmark-{{ $item['id'] }}" method="post" action="{{ route('qbank.bookmarks.destroy', $item['id']) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            <div class="mt-6">
                {{ $bookmarks->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
