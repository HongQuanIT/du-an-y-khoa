@php
    /**
     * @var \Modules\StudyPlan\Models\StudyPlan $plan
     * @var \Modules\StudyPlan\Models\StudyPlanTask $task
     * @var array<int, array<string, mixed>> $items
     * @var string $initialFilter
     * @var string $initialTopic
     * @var int $initialActive
     */
    $exitUrl = route('study-plan.detail', $plan);
    $filters = [
        ['key' => 'all', 'label' => 'Tất cả'],
        ['key' => 'needs', 'label' => 'Cần ôn'],
        ['key' => 'correct', 'label' => 'Đúng'],
        ['key' => 'wrong', 'label' => 'Sai'],
        ['key' => 'skipped', 'label' => 'Bỏ qua'],
    ];
    $initialFilter = $initialFilter ?? 'all';
    $initialTopic = $initialTopic ?? '';
    $initialActive = $initialActive ?? 0;
@endphp

<x-layouts.app title="Xem lại câu hỏi">
    <div class="flex h-[calc(100vh-var(--spacing-header-height,64px))] flex-col overflow-hidden md:flex-row"
        x-data="{
            filter: @js($initialFilter),
            topic: @js($initialTopic),
            active: @js($initialActive),
            showDetail: false,
            notesOpen: false,
            items: @js($items),
            get current() { return this.items[this.active] || this.items[0] || null },
            visible() {
                let list = this.items;
                if (this.topic) {
                    list = list.filter((item) => item.topic === this.topic);
                }
                if (this.filter === 'all') return list;
                if (this.filter === 'needs') {
                    return list.filter((item) => item.result === 'wrong' || item.result === 'skipped');
                }
                return list.filter((item) => item.result === this.filter);
            },
            clearTopic() {
                this.topic = '';
                const list = this.visible();
                if (list.length) this.active = list[0].index;
            },
            setFilter(key) {
                this.filter = key;
                const list = this.visible();
                if (list.length && !list.some((item) => item.index === this.active)) {
                    this.active = list[0].index;
                }
            },
            select(index) {
                this.active = index;
                this.notesOpen = false;
                if (window.innerWidth < 768) this.showDetail = true;
            },
            closeDetail() { this.showDetail = false; },
            prev() {
                const list = this.visible();
                const pos = list.findIndex((item) => item.index === this.active);
                if (pos > 0) this.active = list[pos - 1].index;
            },
            next() {
                const list = this.visible();
                const pos = list.findIndex((item) => item.index === this.active);
                if (pos >= 0 && pos < list.length - 1) this.active = list[pos + 1].index;
            },
            optionClass(state) {
                return {
                    correct_selected: 'border-2 border-[#16A34A] bg-[#16A34A]/10',
                    correct: 'border-2 border-[#16A34A] bg-[#16A34A]/5',
                    wrong_selected: 'border-2 border-error bg-error/5',
                    dimmed: 'border border-outline-variant bg-surface-container-low opacity-60',
                }[state] || 'border border-outline-variant bg-surface-container-low';
            },
            badgeClass(state) {
                return {
                    correct_selected: 'bg-[#16A34A] text-white',
                    correct: 'bg-[#16A34A] text-white',
                    wrong_selected: 'bg-error text-white',
                    dimmed: 'border-2 border-outline-variant text-on-surface-variant',
                }[state] || 'border-2 border-outline-variant';
            },
        }" @resize.window="if (window.innerWidth >= 768) showDetail = false"
        @keydown.escape.window="notesOpen = false">

        <section
            class="z-20 flex w-full flex-col border-r border-outline-variant bg-white transition-transform duration-300 md:w-[400px]"
            :class="{ 'hidden md:flex': showDetail }">
            <div class="border-b border-outline-variant bg-surface-container-lowest p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-headline-sm text-headline-sm text-on-surface">Xem lại câu hỏi</h2>
                        <p class="mt-1 text-body-sm text-on-surface-variant">{{ $task->title() }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full bg-primary-container px-2 py-0.5 text-[10px] font-bold text-on-primary-container">
                        {{ $answered }}/{{ $total }} hoàn thành
                    </span>
                </div>
                <div class="mb-3 flex flex-wrap gap-2 text-[11px] text-on-surface-variant">
                    <span class="rounded bg-[#16A34A]/10 px-2 py-0.5 font-semibold text-[#16A34A]">Đúng {{ $correctCount }}</span>
                    <span class="rounded bg-error/10 px-2 py-0.5 font-semibold text-error">Sai {{ $wrongCount }}</span>
                    <span class="rounded bg-surface-container-high px-2 py-0.5 font-semibold">Bỏ qua {{ $skippedCount }}</span>
                </div>
                <div x-show="topic" x-cloak class="mb-3">
                    <button type="button" @click="clearTopic()"
                        class="inline-flex max-w-full items-center gap-1 rounded-full bg-error/10 px-3 py-1 text-[11px] font-semibold text-error transition-colors hover:bg-error/20">
                        <span class="truncate" x-text="'Chủ đề: ' + topic"></span>
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </div>
                <div class="custom-scrollbar flex gap-2 overflow-x-auto pb-1">
                    @foreach ($filters as $filter)
                        <button type="button" @click="setFilter('{{ $filter['key'] }}')"
                            :class="filter === '{{ $filter['key'] }}'
                                ? 'bg-primary text-white shadow-sm'
                                : 'bg-surface-container-high text-on-surface-variant hover:bg-outline-variant'"
                            class="font-label-sm text-label-sm whitespace-nowrap rounded-full px-4 py-1.5 transition-colors">
                            {{ $filter['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="custom-scrollbar flex-1 overflow-y-auto">
                <template x-for="item in visible()" :key="item.id">
                    <button type="button" @click="select(item.index)"
                        class="w-full border-b border-outline-variant p-4 text-left transition-colors hover:bg-surface-container-low"
                        :class="active === item.index && 'border-l-4 border-l-primary bg-surface-container-highest/30'">
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold"
                                    :class="active === item.index ? 'text-primary' : 'text-on-surface'"
                                    x-text="item.id"></span>
                                <span class="material-symbols-outlined text-lg"
                                    :class="[item.iconClass, item.result !== 'skipped' && 'fill-1']"
                                    x-text="item.icon"></span>
                                <span x-show="item.hasNote" x-cloak
                                    class="material-symbols-outlined text-[16px] text-primary"
                                    title="Có ghi chú">description</span>
                                <span x-show="item.flagged" x-cloak
                                    class="material-symbols-outlined text-[16px] text-amber-600"
                                    style="font-variation-settings: 'FILL' 1;"
                                    title="Đã gắn cờ">flag</span>
                            </div>
                            <span
                                class="rounded bg-surface-container-highest px-2 py-0.5 text-[10px] font-semibold uppercase text-on-surface-variant"
                                x-text="item.topic"></span>
                        </div>
                        <p class="mb-2 line-clamp-2 font-body-sm text-body-sm text-on-surface-variant"
                            x-text="item.excerpt"></p>
                        <p class="text-[11px] font-medium text-outline" x-html="item.pick"></p>
                    </button>
                </template>
                <p x-show="visible().length === 0" class="p-6 text-body-sm text-on-surface-variant">
                    Không có câu hỏi trong bộ lọc này.
                </p>
            </div>

            <div class="border-t border-outline-variant p-4">
                <a href="{{ $exitUrl }}"
                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-primary transition-colors hover:bg-surface-container-low">
                    Quay lại kế hoạch
                </a>
            </div>
        </section>

        <section class="custom-scrollbar relative flex-1 overflow-y-auto bg-white"
            :class="{ 'hidden md:block': !showDetail }">
            <div
                class="sticky top-0 z-30 flex items-center justify-between border-b border-outline-variant bg-surface p-4 md:hidden">
                <button type="button" @click="closeDetail()"
                    class="flex items-center gap-2 font-semibold text-primary">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span>Quay lại</span>
                </button>
                <a href="{{ $exitUrl }}" class="font-label-md text-primary">Đóng</a>
            </div>

            <template x-if="current">
                <div class="mx-auto max-w-4xl space-y-8 p-6 md:p-10">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="rounded bg-primary-container px-3 py-1 text-xs font-bold tracking-widest text-white uppercase"
                                x-text="'Câu ' + (current.index + 1)"></span>
                            <span class="text-sm text-outline">•</span>
                            <span class="text-sm font-medium text-outline" x-text="current.topic"></span>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                                :class="{
                                    'bg-[#16A34A]/10 text-[#16A34A]': current.result === 'correct',
                                    'bg-error/10 text-error': current.result === 'wrong',
                                    'bg-surface-container-high text-on-surface-variant': current.result === 'skipped',
                                }"
                                x-text="current.result === 'correct' ? 'Đúng' : (current.result === 'wrong' ? 'Sai' : 'Bỏ qua')"></span>
                            <span x-show="current.flagged" x-cloak
                                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-700">
                                <span class="material-symbols-outlined text-[14px]"
                                    style="font-variation-settings: 'FILL' 1;">flag</span>
                                Gắn cờ
                            </span>
                        </div>
                        <h3 class="session-review-stem font-body-lg text-body-lg leading-relaxed font-semibold text-on-surface"
                            x-html="current.stemHtml || current.stem"></h3>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" @click="notesOpen = true"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-1.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-primary">
                                <span class="material-symbols-outlined text-[18px]">description</span>
                                <span x-text="current.hasNote ? 'Xem ghi chú' : 'Ghi chú'"></span>
                            </button>
                            <span x-show="current.hasNote" x-cloak
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
                                Đã ghi chú khi làm bài
                            </span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <template x-for="option in current.options" :key="option.id">
                            <div class="flex items-start rounded-xl p-4 transition-all"
                                :class="optionClass(option.state)">
                                <div class="mr-4 flex size-8 shrink-0 items-center justify-center rounded-full"
                                    :class="badgeClass(option.state)">
                                    <span class="text-sm font-bold" x-text="option.key"></span>
                                </div>
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p class="text-body-md font-medium text-on-surface" x-text="option.text"></p>
                                    <template x-if="option.state === 'correct_selected' || option.state === 'wrong_selected'">
                                        <span class="inline-block rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                            :class="option.state === 'correct_selected' ? 'bg-[#16A34A] text-white' : 'bg-error text-white'"
                                            x-text="option.state === 'correct_selected' ? 'Lựa chọn của bạn · Đúng' : 'Lựa chọn của bạn'"></span>
                                    </template>
                                    <template x-if="option.state === 'correct'">
                                        <span
                                            class="inline-block rounded bg-[#16A34A] px-2 py-0.5 text-[10px] font-bold text-white uppercase">Đáp
                                            án đúng</span>
                                    </template>
                                    <template x-if="option.explanation && (option.state === 'correct' || option.state === 'correct_selected' || option.state === 'wrong_selected')">
                                        <p class="pt-1 text-body-sm text-on-surface-variant" x-text="option.explanation"></p>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>



                    <div class="flex items-center justify-between border-t border-outline-variant pt-8 pb-20 md:pb-10">
                        <button type="button" @click="prev()"
                            class="flex items-center gap-2 text-outline transition-colors hover:text-primary">
                            <span class="material-symbols-outlined">chevron_left</span>
                            <span class="font-label-md text-label-md">Câu trước</span>
                        </button>
                        <button type="button" @click="next()"
                            class="rounded-full bg-primary px-8 py-2.5 font-bold text-white shadow-md transition-all hover:opacity-90">
                            Câu tiếp theo
                        </button>
                    </div>
                </div>
            </template>
        </section>

        <div x-show="notesOpen && current" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-on-background/40 backdrop-blur-sm" @click="notesOpen = false"></div>
            <div class="relative flex w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-lg"
                @click.outside="notesOpen = false">
                <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Ghi chú cá nhân</h3>
                    <button type="button" @click="notesOpen = false"
                        class="flex size-8 items-center justify-center rounded-full transition-colors hover:bg-surface-container-high">
                        <span class="material-symbols-outlined text-outline">close</span>
                    </button>
                </div>
                <div class="space-y-3 p-6">
                    <p class="text-label-sm text-on-surface-variant"
                        x-text="'Câu ' + ((current?.index ?? 0) + 1)"></p>
                    <div class="min-h-[160px] whitespace-pre-wrap rounded-lg border border-outline-variant bg-surface-container-lowest p-4 text-body-md text-on-surface"
                        x-text="current?.note || 'Chưa có ghi chú cho câu hỏi này.'"></div>
                </div>
                <div class="flex justify-end border-t border-outline-variant px-6 py-4">
                    <button type="button" @click="notesOpen = false"
                        class="rounded-lg bg-primary-container px-4 py-2 font-label-md text-white transition-opacity hover:opacity-90">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
