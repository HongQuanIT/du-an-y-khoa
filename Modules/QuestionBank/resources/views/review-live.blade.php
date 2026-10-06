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
            hintReveal: {},
            knowledgeReveal: {},
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
            isKeyInfoOn() {
                const item = this.current;
                if (!item) return false;
                if (Object.prototype.hasOwnProperty.call(this.hintReveal, item.question_id)) {
                    return Boolean(this.hintReveal[item.question_id]);
                }
                return Boolean(item.hint_used && item.has_key_info);
            },
            toggleKeyInfo() {
                if (!this.current) return;
                const questionId = this.current.question_id;
                this.hintReveal = { ...this.hintReveal, [questionId]: !this.isKeyInfoOn() };
            },
            isKnowledgeOn() {
                const item = this.current;
                if (!item || !item.knowledge_html) return false;
                if (Object.prototype.hasOwnProperty.call(this.knowledgeReveal, item.question_id)) {
                    return Boolean(this.knowledgeReveal[item.question_id]);
                }
                return Boolean(item.knowledge_used);
            },
            toggleKnowledge() {
                if (!this.current?.knowledge_html) return;
                const questionId = this.current.question_id;
                this.knowledgeReveal = { ...this.knowledgeReveal, [questionId]: !this.isKnowledgeOn() };
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
                                <span x-show="item.hint_used" x-cloak
                                    class="inline-flex items-center gap-0.5 rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:text-amber-400"
                                    title="Đã dùng gợi ý khi làm bài"
                                    data-testid="review-list-hint-used">
                                    <span class="material-symbols-outlined text-[13px]">lightbulb</span>
                                    Đã dùng gợi ý
                                </span>
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
                                :class="isKeyInfoOn() ? 'key-info-active' : ''">
                                <div class="space-y-3 rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm md:p-6">
                                    <div x-show="current.hint_used" x-cloak
                                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-700 uppercase"
                                        data-testid="review-hint-used-badge">
                                        <span class="material-symbols-outlined text-[15px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                        <span>Đã dùng gợi ý</span>
                                    </div>
                                    <div x-show="!isKeyInfoOn()"
                                        class="prose prose-lg max-w-none text-body-lg leading-relaxed text-on-surface dark:prose-invert"
                                        data-testid="review-stem"
                                        x-html="current.stem_html"></div>
                                    <div x-show="isKeyInfoOn()" x-cloak
                                        class="prose prose-lg max-w-none text-body-lg leading-relaxed text-on-surface dark:prose-invert"
                                        data-testid="review-key-info-stem"
                                        x-html="current.stem_key_info_html"></div>
                                </div>

                            </div>

                            <div class="flex min-h-12 items-center border-y border-outline-variant bg-surface-container-lowest px-1"
                                data-testid="review-knowledge-toolbar">
                                <button type="button" @click="toggleKeyInfo()"
                                    class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors"
                                    :class="isKeyInfoOn()
                                        ? 'border-amber-600 text-amber-700'
                                        : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                    title="Gạch chân các đoạn gợi ý khớp trong câu hỏi"
                                    :aria-pressed="isKeyInfoOn()"
                                    data-testid="review-hint-toggle">
                                    <span class="material-symbols-outlined text-[18px]">format_align_left</span>
                                    <span>Gợi ý</span>
                                </button>
                                <button type="button" x-show="current.knowledge_html" @click="toggleKnowledge()"
                                    class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors"
                                    :class="isKnowledgeOn()
                                        ? 'border-amber-600 text-amber-700'
                                        : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                    title="Mở kiến thức cho câu hỏi"
                                    :aria-pressed="isKnowledgeOn()"
                                    data-testid="review-knowledge-toggle">
                                    <span class="material-symbols-outlined text-[18px]">help</span>
                                    <span>Kiến thức</span>
                                </button>
                            </div>

                            <div x-show="isKnowledgeOn()" x-cloak
                                class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-on-surface"
                                data-testid="review-knowledge-panel">
                                <div x-show="current.knowledge_used"
                                    class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-700 uppercase"
                                    data-testid="review-knowledge-used">
                                    <span class="material-symbols-outlined text-[15px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                    <span>Đã dùng kiến thức</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined mt-0.5 shrink-0 text-amber-700">stethoscope</span>
                                    <div class="prose prose-sm max-w-none font-body-md text-body-md leading-relaxed italic dark:prose-invert"
                                        x-html="current.knowledge_html"></div>
                                </div>
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

    </div>
    <style>
        [data-testid="review-stem"] mark[data-hint],
        [data-testid="review-key-info-stem"] mark[data-hint] {
            cursor: pointer;
            background-color: transparent;
            color: inherit;
            text-decoration: none;
        }
        [data-testid="review-stem"] mark[data-hint].revealed,
        [data-testid="review-key-info-stem"] mark[data-hint].revealed,
        .key-info-active [data-testid="review-stem"] mark[data-hint],
        .key-info-active [data-testid="review-key-info-stem"] mark[data-hint] {
            text-decoration: underline #ea580c;
            text-decoration-style: solid;
            text-decoration-thickness: 2px;
            text-underline-offset: 4px;
            background-color: transparent;
        }
    </style>
    <script>
        document.addEventListener('click', function (event) {
            const hint = event.target.closest('mark[data-hint]');
            if (!hint) return;
            if (!hint.closest('[data-testid="review-stem"], [data-testid="review-key-info-stem"]')) return;
            hint.classList.toggle('revealed');
        });
    </script>
</x-layouts.app>
