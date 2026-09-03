@php
    /**
     * @var \Modules\QuestionBank\Models\QuestionSession $session
     * @var list<array<string, mixed>> $items
     * @var string $initialFilter
     */
    $filters = [
        ['value' => 'all', 'label' => 'Tất cả'],
        ['value' => 'correct', 'label' => 'Đúng'],
        ['value' => 'wrong', 'label' => 'Sai'],
        ['value' => 'skipped', 'label' => 'Bỏ qua'],
        ['value' => 'flagged', 'label' => 'Đã gắn cờ'],
        ['value' => 'needs', 'label' => 'Cần ôn'],
    ];
    $summaryUrl = $summaryUrl ?? route('qbank.summary', $session);
@endphp

<x-layouts.app title="Xem lại câu hỏi">
    <div class="flex h-[calc(100vh-var(--spacing-header-height))] overflow-hidden bg-surface"
        x-data="{
            items: @js($items),
            filter: '{{ $initialFilter }}',
            activeKey: @js($items[0]['question_id'] ?? null),
            detailOpen: false,
            notesOpen: false,
            imageViewerOpen: false,
            imageViewerSrc: null,
            get filtered() {
                return this.items.filter((item) => this.matches(item, this.filter));
            },
            get current() {
                return this.filtered.find((item) => item.question_id === this.activeKey)
                    || this.filtered[0]
                    || null;
            },
            matches(item, filter) {
                if (filter === 'all') return true;
                if (filter === 'flagged') return Boolean(item.flagged);
                if (filter === 'needs') return item.result === 'wrong' || item.result === 'skipped' || Boolean(item.flagged);
                return item.result === filter;
            },
            count(filter) {
                return this.items.filter((item) => this.matches(item, filter)).length;
            },
            setFilter(filter) {
                this.filter = filter;
                const first = this.filtered[0];
                this.activeKey = first?.question_id ?? null;
                this.detailOpen = false;
            },
            select(item) {
                this.activeKey = item.question_id;
                this.detailOpen = true;
            },
            move(offset) {
                if (!this.current) return;
                const position = this.filtered.findIndex((item) => item.question_id === this.current.question_id);
                const target = this.filtered[position + offset];
                if (target) this.activeKey = target.question_id;
            },
            canMove(offset) {
                if (!this.current) return false;
                const position = this.filtered.findIndex((item) => item.question_id === this.current.question_id);
                return Boolean(this.filtered[position + offset]);
            },
            resultLabel(result) {
                return { correct: 'Đúng', wrong: 'Sai', skipped: 'Bỏ qua' }[result] || result;
            },
            resultIcon(result) {
                return { correct: 'check_circle', wrong: 'cancel', skipped: 'remove_circle' }[result] || 'help';
            },
            resultClass(result) {
                return {
                    correct: 'bg-success/10 text-success',
                    wrong: 'bg-error/10 text-error',
                    skipped: 'bg-surface-container-high text-on-surface-variant',
                }[result] || 'bg-surface-container-high text-on-surface-variant';
            },
            optionClass(option) {
                if (option.state === 'correct_selected' || option.state === 'correct') return 'border-success bg-success/5';
                if (option.state === 'wrong_selected') return 'border-error bg-error/5';
                return 'border-outline-variant bg-surface-container-low opacity-80';
            },
            optionBadgeClass(option) {
                if (option.state === 'correct_selected' || option.state === 'correct') return 'border-success bg-success text-on-primary';
                if (option.state === 'wrong_selected') return 'border-error bg-error text-on-primary';
                return 'border-outline-variant bg-surface text-on-surface-variant';
            },
        }" @keydown.escape.window="detailOpen = false; notesOpen = false">
        <aside class="z-10 w-full shrink-0 flex-col border-r border-outline-variant bg-surface md:flex md:w-[400px] lg:w-[440px]"
            :class="detailOpen ? 'hidden md:flex' : 'flex'">
            <div class="border-b border-outline-variant bg-surface-container-lowest p-4 md:p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <nav class="mb-1 flex items-center gap-1 text-[11px] text-on-surface-variant">
                            <a href="{{ $summaryUrl }}" class="hover:text-primary">Tổng kết</a>
                            <span>/</span>
                            <span class="font-bold text-primary">Xem lại</span>
                        </nav>
                        <h1 class="font-headline-sm text-headline-sm text-on-surface">Danh sách câu hỏi</h1>
                    </div>
                    <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                        {{ count($items) }} câu
                    </span>
                </div>
                <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
                    @foreach ($filters as $filter)
                        <button type="button" @click="setFilter('{{ $filter['value'] }}')"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold transition-colors"
                            :class="filter === '{{ $filter['value'] }}'
                                ? 'bg-primary text-on-primary shadow-sm'
                                : 'bg-surface-container-high text-on-surface-variant hover:bg-outline-variant/50'">
                            {{ $filter['label'] }}
                            <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                                :class="filter === '{{ $filter['value'] }}' ? 'bg-on-primary/20' : 'bg-surface-container-lowest'"
                                x-text="count('{{ $filter['value'] }}')"></span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="custom-scrollbar flex-1 divide-y divide-outline-variant/60 overflow-y-auto">
                <template x-for="item in filtered" :key="item.question_id">
                    <button type="button" @click="select(item)"
                        class="w-full p-4 text-left transition-colors"
                        :class="activeKey === item.question_id
                            ? 'bg-primary/5 border-l-4 border-primary pl-3'
                            : 'hover:bg-surface-container-lowest'">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-on-surface" x-text="item.id"></span>
                                <span x-show="item.flagged" class="material-symbols-outlined text-[16px] text-amber-500" style="font-variation-settings: 'FILL' 1;">flag</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]" :class="resultClass(item.result)"
                                    x-text="resultIcon(item.result)"></span>
                                <span class="text-xs font-bold capitalize"
                                    :class="resultClass(item.result)" x-text="resultLabel(item.result)"></span>
                            </div>
                        </div>
                        <p class="line-clamp-2 text-body-sm leading-relaxed text-on-surface" x-text="item.excerpt"></p>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="truncate text-[11px] font-semibold text-on-surface-variant" x-text="item.topic"></span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span x-show="item.stem_image_url" class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-primary/10 text-primary" title="Có hình ảnh minh họa">
                                    <span class="material-symbols-outlined text-[13px]">image</span> Ảnh
                                </span>
                                <span x-show="item.note" class="inline-flex items-center gap-1 text-[11px] text-primary">
                                    <span class="material-symbols-outlined text-[14px]">description</span> Có ghi chú
                                </span>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </aside>

        <main class="custom-scrollbar min-w-0 flex-1 flex-col overflow-y-auto bg-surface-container-lowest"
            :class="detailOpen ? 'flex' : 'hidden md:flex'">
            <template x-if="current">
                <article class="flex min-h-full flex-col">
                    <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-outline-variant bg-surface/95 px-4 py-3 backdrop-blur md:px-6">
                        <button type="button" @click="detailOpen = false"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-2 font-bold text-primary hover:bg-primary/5 md:hidden">
                            <span class="material-symbols-outlined">arrow_back</span> Danh sách
                        </button>
                        <div class="hidden items-center gap-2 md:flex">
                            <span class="font-bold text-on-surface" x-text="current.id"></span>
                            <span class="text-on-surface-variant">·</span>
                            <span class="text-sm font-semibold text-on-surface-variant" x-text="current.topic"></span>
                        </div>
                        <div class="ml-auto flex items-center gap-2">
                            <span x-show="current.flagged" class="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-3 py-1 text-xs font-bold text-amber-600 dark:text-amber-400">
                                <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">flag</span>
                                Gắn cờ
                            </span>
                            <span class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="resultClass(current.result)" x-text="resultLabel(current.result)"></span>
                        </div>
                    </header>

                    <div class="mx-auto w-full max-w-4xl flex-1 space-y-8 p-4 pb-24 md:p-8 lg:p-10">
                        <section class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2 md:hidden">
                                <span class="font-bold text-primary" x-text="current.id"></span>
                                <span class="text-sm text-on-surface-variant" x-text="current.topic"></span>
                            </div>

                            <div class="grid gap-5"
                                :class="current.stem_image_url ? 'lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)] lg:items-start' : ''">
                                <div class="prose prose-lg max-w-none rounded-2xl border border-outline-variant bg-surface p-5 text-body-lg leading-relaxed text-on-surface shadow-sm md:p-6 dark:prose-invert"
                                    x-html="current.stem_html"></div>

                                <template x-if="current.stem_image_url">
                                    <aside class="overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                                        <div class="flex cursor-zoom-in justify-center bg-white p-3"
                                            @click="imageViewerOpen = true; imageViewerSrc = current.stem_image_url">
                                            <img :src="current.stem_image_url" alt="Ảnh minh họa câu hỏi"
                                                class="h-auto max-h-[460px] w-full object-contain transition-transform hover:scale-[1.02]">
                                        </div>
                                        <div class="flex items-center justify-between border-t border-outline-variant/60 bg-surface-container-low px-3.5 py-2 text-[11px] font-medium text-on-surface-variant">
                                            <span class="inline-flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[15px]">zoom_in</span>
                                                Nhấp để phóng to ảnh
                                            </span>
                                            <span class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-primary border border-outline-variant">Hình ảnh</span>
                                        </div>
                                    </aside>
                                </template>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="notesOpen = true"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-primary">
                                    <span class="material-symbols-outlined text-[18px]">description</span>
                                    <span x-text="current.note ? 'Xem ghi chú' : 'Ghi chú'"></span>
                                </button>
                                <span x-show="current.note" x-cloak
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
                                    Đã ghi chú khi làm bài
                                </span>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-headline-sm text-headline-sm text-on-surface">Đáp án</h2>
                            <template x-for="option in current.options" :key="option.id">
                                <div class="overflow-hidden rounded-xl border" :class="optionClass(option)">
                                    <div class="flex items-start gap-4 p-4">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border font-bold"
                                            :class="optionBadgeClass(option)" x-text="option.key"></span>
                                        <div class="min-w-0 flex-1 pt-1">
                                            <p class="text-body-md text-on-surface" x-text="option.text"></p>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <span x-show="option.correct"
                                                    class="rounded bg-success px-2 py-0.5 text-[10px] font-bold text-on-primary uppercase">Đáp án đúng</span>
                                                <span x-show="option.selected"
                                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                                    :class="option.correct ? 'bg-primary/10 text-primary' : 'bg-error text-on-primary'">
                                                    Bạn đã chọn
                                                </span>
                                            </div>
                                            <div x-show="option.explanation"
                                                class="prose prose-sm mt-3 max-w-none text-body-sm leading-relaxed text-on-surface-variant dark:prose-invert"
                                                x-html="option.explanation"></div>
                                        </div>
                                        <span x-show="option.correct" class="material-symbols-outlined text-success">check_circle</span>
                                        <span x-show="option.selected && !option.correct" class="material-symbols-outlined text-error">cancel</span>
                                    </div>
                                </div>
                            </template>
                        </section>
                    </div>

                    <footer class="sticky bottom-0 z-20 flex items-center justify-between gap-3 border-t border-outline-variant bg-surface/95 px-4 py-3 backdrop-blur md:px-6">
                        <button type="button" @click="move(-1)" :disabled="!canMove(-1)"
                            class="inline-flex items-center gap-1 rounded-lg px-3 py-2 font-bold text-on-surface-variant hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-30">
                            <span class="material-symbols-outlined">chevron_left</span>
                            <span class="hidden sm:inline">Câu trước</span>
                        </button>
                        <a href="{{ $summaryUrl }}"
                            class="hidden rounded-lg border border-outline-variant bg-surface px-4 py-2 text-sm font-bold text-primary hover:bg-primary/5 sm:inline-flex">
                            Về tổng kết
                        </a>
                        <button type="button" @click="move(1)" :disabled="!canMove(1)"
                            class="inline-flex items-center gap-1 rounded-lg bg-primary px-4 py-2 font-bold text-on-primary disabled:cursor-not-allowed disabled:opacity-30">
                            <span class="hidden sm:inline">Câu tiếp theo</span>
                            <span class="material-symbols-outlined">chevron_right</span>
                        </button>
                    </footer>
                </article>
            </template>

            <template x-if="!current">
                <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <span class="material-symbols-outlined text-6xl text-outline-variant">quiz</span>
                    <h2 class="mt-4 font-headline-sm text-headline-sm text-on-surface">Không có câu để xem lại</h2>
                    <a href="{{ $summaryUrl }}" class="mt-5 rounded-lg bg-primary px-5 py-2.5 font-bold text-on-primary">Quay lại tổng kết</a>
                </div>
            </template>
        </main>

        <div x-show="notesOpen && current" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-on-background/40 backdrop-blur-sm" @click="notesOpen = false"></div>
            <div class="relative flex w-full max-w-lg flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-lg"
                @click.outside="notesOpen = false">
                <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Ghi chú cá nhân</h3>
                    <button type="button" @click="notesOpen = false"
                        class="flex size-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="space-y-3 p-6">
                    <p class="text-label-sm text-on-surface-variant"
                        x-text="'Câu ' + ((current?.index ?? 0) + 1)"></p>
                    <div class="prose prose-sm min-h-[160px] max-w-none rounded-lg border border-outline-variant bg-surface-container-lowest p-4 text-body-md text-on-surface dark:prose-invert"
                        x-html="current?.note_html || current?.note || 'Chưa có ghi chú cho câu hỏi này.'"></div>
                </div>
                <div class="flex justify-end border-t border-outline-variant px-6 py-4">
                    <button type="button" @click="notesOpen = false"
                        class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary transition-opacity hover:opacity-90">
                        Đóng
                    </button>
                </div>
            </div>
        </div>

        <div x-show="imageViewerOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[150] flex items-center justify-center bg-black/90 p-4"
            @click="imageViewerOpen = false">
            <button type="button"
                class="absolute right-4 top-4 flex size-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
                @click="imageViewerOpen = false" aria-label="Đóng ảnh">
                <span class="material-symbols-outlined text-[24px]">close</span>
            </button>
            <img :src="imageViewerSrc" alt="Ảnh phóng to"
                class="max-h-full max-w-full cursor-zoom-out object-contain"
                @click.stop="imageViewerOpen = false">
        </div>
    </div>
</x-layouts.app>
