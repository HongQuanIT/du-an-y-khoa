@php
    // Static port of html/pc-study-session.html. Placeholders until session state lands.
    $tools = [
        ['icon' => 'bookmark', 'label' => 'Lưu câu hỏi'],
        ['icon' => 'flag', 'label' => 'Gắn cờ'],
        ['icon' => 'description', 'label' => 'Ghi chú', 'action' => 'notes'],
        ['icon' => 'science', 'label' => 'Tra cứu Labs'],
        ['icon' => 'drive_file_rename_outline', 'label' => 'Tô màu văn bản', 'action' => 'highlight'],
    ];

    $highlightColors = [
        ['hex' => '#EF4444', 'title' => 'Đỏ'],
        ['hex' => '#F59E0B', 'title' => 'Vàng'],
        ['hex' => '#10B981', 'title' => 'Xanh lá'],
    ];

    $aiTools = [
        ['icon' => 'psychology', 'label' => 'Hỏi Med-AI'],
        ['icon' => 'style', 'label' => 'Tạo thẻ học', 'action' => 'flashcard'],
    ];

    $flashcardDecks = [
        'Dược lý tim mạch',
        'Chẩn đoán phân biệt',
        'Câu sai của tôi',
    ];

    $options = [
        [
            'key' => 'A',
            'text' => 'Viêm màng ngoài tim',
            'state' => 'neutral',
        ],
        [
            'key' => 'B',
            'text' => 'Nhồi máu cơ tim cấp thành trước',
            'state' => 'correct',
        ],
        [
            'key' => 'C',
            'text' => 'Bóc tách động mạch chủ',
            'state' => 'neutral',
        ],
        [
            'key' => 'D',
            'text' => 'Thuyên tắc phổi',
            'state' => 'wrong_detail',
            'feedback' => 'Thuyên tắc phổi thường gây ra đau ngực màng phổi cấp tính và khó thở, tuy nhiên điện tâm đồ điển hình thường là S1Q3T3 hoặc nhịp nhanh xoang, không phải ST chênh lên khu trú từ V1–V4.',
        ],
        [
            'key' => 'E',
            'text' => 'Trào ngược dạ dày thực quản',
            'state' => 'wrong',
        ],
    ];

    $current = 12;
    $total = 40;
    $progress = (int) round(($current / $total) * 100);

    $completed = [1];
    $flagged = [5];
    $navigatorQuestions = collect(range(1, $total))->map(function (int $n) use ($current, $completed, $flagged) {
        return [
            'n' => $n,
            'state' => match (true) {
                $n === $current => 'active',
                in_array($n, $completed, true) => 'completed',
                in_array($n, $flagged, true) => 'flagged',
                default => 'unanswered',
            },
        ];
    })->all();

    $exitUrl ??= route('qbank.create');
@endphp

<x-layouts.auth title="Phiên học tập">
    <div x-data="{
            notesOpen: false,
            flashcardOpen: false,
            navigatorOpen: false,
            highlightMode: false,
            selectionBar: { show: false, x: 0, y: 0 },
            toggleHighlight() {
                this.highlightMode = !this.highlightMode;
                if (!this.highlightMode) this.selectionBar.show = false;
            },
            onTextSelect() {
                if (!this.highlightMode) {
                    this.selectionBar.show = false;
                    return;
                }
                // Đợi selection ổn định sau mouseup
                setTimeout(() => {
                    const sel = window.getSelection();
                    if (!sel || sel.rangeCount === 0 || sel.isCollapsed || !sel.toString().trim()) {
                        this.selectionBar.show = false;
                        return;
                    }
                    const range = sel.getRangeAt(0);
                    const root = this.$refs.vignette;
                    if (root && !root.contains(range.commonAncestorContainer) && range.commonAncestorContainer !== root) {
                        // Cho phép nếu selection nằm trong vignette (text node parent)
                        const node = range.commonAncestorContainer.nodeType === 3
                            ? range.commonAncestorContainer.parentElement
                            : range.commonAncestorContainer;
                        if (!root.contains(node)) {
                            this.selectionBar.show = false;
                            return;
                        }
                    }
                    const rect = range.getBoundingClientRect();
                    if (rect.width === 0 && rect.height === 0) {
                        this.selectionBar.show = false;
                        return;
                    }
                    this.selectionBar = {
                        show: true,
                        x: Math.max(80, Math.min(window.innerWidth - 80, rect.left + rect.width / 2)),
                        y: Math.max(48, rect.top - 8),
                    };
                }, 10);
            },
            applyColor(hex) {
                const sel = window.getSelection();
                if (!sel || sel.isCollapsed || !sel.rangeCount) return;
                const range = sel.getRangeAt(0);
                if (!range.toString().trim()) return;
                const mark = document.createElement('mark');
                mark.className = 'rounded-sm';
                mark.style.backgroundColor = hex + '4D';
                try {
                    range.surroundContents(mark);
                } catch (e) {
                    const fragment = range.extractContents();
                    mark.appendChild(fragment);
                    range.insertNode(mark);
                }
                sel.removeAllRanges();
                this.selectionBar.show = false;
            },
            clearHighlight() {
                const root = this.$refs.vignette;
                if (!root) return;
                root.querySelectorAll('mark').forEach((mark) => {
                    const parent = mark.parentNode;
                    while (mark.firstChild) parent.insertBefore(mark.firstChild, mark);
                    parent.removeChild(mark);
                    parent.normalize();
                });
                this.selectionBar.show = false;
            },
        }"
        @keydown.escape.window="notesOpen = false; flashcardOpen = false; navigatorOpen = false; selectionBar.show = false"
        @mouseup.window="onTextSelect()">
    {{-- Desktop --}}
    <div class="hidden min-h-screen flex-col bg-white lg:flex">
        <header
            class="fixed top-0 z-50 flex h-header-height w-full items-center border-b border-outline-variant bg-white px-margin-desktop">
            <div class="flex flex-1 items-center gap-4">
                <a href="{{ $exitUrl }}"
                    class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined text-outline">close</span>
                </a>
                <div class="flex flex-col">
                    <span class="font-label-md text-label-md font-bold text-primary">{{ config('app.name') }}</span>
                    <span class="text-[10px] font-bold tracking-wider text-outline uppercase">Chế độ Study</span>
                </div>
            </div>
            <div class="flex flex-1 flex-col items-center gap-1">
                <span class="font-label-md text-label-md text-on-surface-variant">Question {{ $current }} of
                    {{ $total }}</span>
                <div class="h-1.5 w-64 overflow-hidden rounded-full bg-surface-container-highest">
                    <div class="h-full bg-primary transition-all duration-500" style="width: {{ $progress }}%"></div>
                </div>
            </div>
            <div class="flex flex-1 items-center justify-end gap-3">
                <div class="flex items-center gap-1 text-primary">
                    <span class="material-symbols-outlined text-[18px]"
                        style="font-variation-settings: 'FILL' 1;">cloud_done</span>
                    <span class="font-label-sm text-label-sm">Đã lưu</span>
                </div>
                <button type="button" @click="navigatorOpen = true"
                    class="flex items-center gap-2 rounded-lg border border-outline-variant px-3 py-1.5 transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                    <span class="font-label-md text-label-md">Navigator</span>
                </button>
            </div>
        </header>

        <main class="flex flex-1 bg-white pt-header-height pb-24">
            <aside
                class="group sticky top-header-height h-[calc(100vh-var(--spacing-header-height))] w-16 shrink-0 overflow-y-auto border-r border-outline-variant bg-white transition-all duration-300 hover:w-56">
                <nav class="space-y-2 p-4">
                    @foreach ($tools as $tool)
                        <button type="button"
                            @if (($tool['action'] ?? null) === 'notes') @click="notesOpen = true"
                            @elseif (($tool['action'] ?? null) === 'highlight') @click="toggleHighlight()" @endif
                            @if (($tool['action'] ?? null) === 'highlight')
                                class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 transition-colors group-hover:justify-start group-hover:px-3"
                                :class="highlightMode ? 'bg-primary/5 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                            @else
                                class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 text-on-surface-variant transition-colors group-hover:justify-start group-hover:px-3 hover:bg-surface-container-high hover:text-primary"
                            @endif>
                            <span class="material-symbols-outlined text-[20px]">{{ $tool['icon'] }}</span>
                            <span
                                class="overflow-hidden text-label-md font-medium whitespace-nowrap opacity-0 transition-opacity duration-300 group-hover:opacity-100">{{ $tool['label'] }}</span>
                        </button>
                    @endforeach
                    <div class="mx-3 my-4 border-t border-outline-variant"></div>
                    @foreach ($aiTools as $tool)
                        <button type="button"
                            @if (($tool['action'] ?? null) === 'flashcard') @click="flashcardOpen = true" @endif
                            class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 text-on-surface-variant transition-colors group-hover:justify-start group-hover:px-3 hover:bg-surface-container-high hover:text-primary">
                            <span class="material-symbols-outlined text-[20px]">{{ $tool['icon'] }}</span>
                            <span
                                class="overflow-hidden text-label-md font-medium whitespace-nowrap opacity-0 transition-opacity duration-300 group-hover:opacity-100">{{ $tool['label'] }}</span>
                        </button>
                    @endforeach
                    <div class="mt-8">
                        <button type="button"
                            class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 text-error transition-colors group-hover:justify-start group-hover:px-3 hover:bg-error/5">
                            <span class="material-symbols-outlined text-[20px]">report</span>
                            <span
                                class="overflow-hidden text-label-md font-medium whitespace-nowrap opacity-0 transition-opacity duration-300 group-hover:opacity-100">Báo
                                lỗi câu hỏi</span>
                        </button>
                    </div>
                </nav>
            </aside>

            <div class="w-full overflow-y-auto transition-all duration-300">
                <div class="mx-auto max-w-4xl space-y-6 px-12 py-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 rounded-full bg-surface-container-highest px-3 py-1">
                            <span class="size-2 rounded-full bg-primary"></span>
                            <span
                                class="font-label-sm text-label-sm font-bold text-on-surface-variant uppercase">Câu hỏi
                                {{ $current }}</span>
                        </div>
                        <button type="button" class="text-outline hover:text-on-surface">
                            <span class="material-symbols-outlined">more_horiz</span>
                        </button>
                    </div>

                    <article class="space-y-6">
                        <p x-ref="vignette"
                            class="font-body-lg text-body-lg leading-relaxed text-on-surface select-text"
                            :class="highlightMode && 'cursor-text'"
                            @mouseup="onTextSelect()">
                            Bệnh nhân nam 58 tuổi, tiền sử tăng huyết áp và đái tháo đường type 2, nhập viện vì đau ngực
                            sau xương ức lan vai trái 40 phút. Khám lâm sàng ghi nhận vã mồ hôi, nhịp tim 105 lần/phút,
                            huyết áp 145/90 mmHg. ECG ghi nhận nhịp xoang, đoạn ST chênh lên từ V1–V4.
                        </p>
                        <div class="rounded-r-lg border-l-4 border-primary bg-primary/5 py-3 pr-4 pl-4">
                            <p class="font-headline-sm text-headline-sm font-bold text-on-surface">
                                Chẩn đoán phù hợp nhất là gì?
                            </p>
                        </div>
                    </article>

                    <section class="space-y-3">
                        @foreach ($options as $option)
                            @if ($option['state'] === 'wrong_detail')
                                <div
                                    class="flex w-full flex-col overflow-hidden rounded-xl border border-error bg-error/5 text-left">
                                    <div class="flex items-start gap-4 p-4">
                                        <span
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-error font-bold text-white">{{ $option['key'] }}</span>
                                        <span
                                            class="pt-1 font-body-md text-body-md text-on-surface">{{ $option['text'] }}</span>
                                        <span class="material-symbols-outlined ml-auto text-error">remove</span>
                                    </div>
                                    <div class="px-4 pb-4 pl-16">
                                        <p class="text-body-sm leading-relaxed text-on-surface-variant">
                                            {{ $option['feedback'] }}
                                        </p>
                                        <button type="button"
                                            class="mt-2 flex items-center gap-1 text-[11px] font-bold text-primary uppercase">
                                            <span class="material-symbols-outlined text-[16px]">chat_bubble</span>
                                            Phản hồi
                                        </button>
                                    </div>
                                </div>
                            @else
                                @php
                                    $wrap = match ($option['state']) {
                                        'correct' => 'border-[#16A34A] bg-[#16A34A]/5',
                                        'wrong' => 'border-error bg-error/5',
                                        default => 'border-outline-variant bg-white',
                                    };
                                    $badge = match ($option['state']) {
                                        'correct' => 'bg-[#16A34A] text-white',
                                        'wrong' => 'bg-error text-white',
                                        default => 'border border-outline-variant text-on-surface-variant',
                                    };
                                    $icon = match ($option['state']) {
                                        'correct' => ['check', 'text-[#16A34A]', ''],
                                        'wrong' => ['close', 'text-error', ''],
                                        default => ['close', 'text-outline-variant', 'opacity-0'],
                                    };
                                @endphp
                                <button type="button"
                                    class="flex w-full items-start gap-4 rounded-xl border p-4 text-left transition-all {{ $wrap }}">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full font-bold {{ $badge }}">{{ $option['key'] }}</span>
                                    <span
                                        class="pt-1 font-body-md text-body-md text-on-surface">{{ $option['text'] }}</span>
                                    <span
                                        class="material-symbols-outlined ml-auto {{ $icon[1] }} {{ $icon[2] }}">{{ $icon[0] }}</span>
                                </button>
                            @endif
                        @endforeach
                    </section>
                </div>
            </div>
        </main>

        <footer
            class="fixed bottom-0 left-0 z-50 flex w-full items-center justify-between border-t border-outline-variant bg-white px-margin-desktop py-4 shadow-lg">
            <button type="button"
                class="flex items-center gap-2 px-4 py-2 text-on-surface-variant transition-colors hover:text-on-surface">
                <span class="material-symbols-outlined">chevron_left</span>
                <span class="font-bold">Câu trước</span>
            </button>
            <button type="button"
                class="flex items-center gap-3 rounded-lg bg-primary px-8 py-3 font-bold text-on-primary transition-all hover:opacity-90 active:scale-95">
                <span>Câu tiếp theo</span>
                <span class="material-symbols-outlined">arrow_forward</span>
            </button>
        </footer>
    </div>

    {{-- Mobile --}}
    <div class="flex min-h-screen flex-col bg-white lg:hidden">
        <header
            class="sticky top-0 z-50 flex w-full items-center justify-between border-b border-outline-variant bg-white px-4 py-3">
            <a href="{{ $exitUrl }}" class="material-symbols-outlined text-outline">close</a>
            <div class="flex flex-col items-center">
                <span class="text-[10px] font-bold tracking-wider text-primary uppercase">Chế độ Study</span>
                <span class="font-label-md text-label-md font-bold">{{ $current }} / {{ $total }}</span>
            </div>
            <button type="button" @click="navigatorOpen = true" class="material-symbols-outlined text-outline"
                aria-label="Navigator">grid_view</button>
        </header>
        <main class="flex-1 space-y-6 p-4 pb-24">
            <div class="h-1 w-full overflow-hidden rounded-full bg-surface-container-highest">
                <div class="h-full bg-primary" style="width: {{ $progress }}%"></div>
            </div>
            <div class="space-y-4">
                <p class="text-body-md text-on-surface">
                    Bệnh nhân nam 58 tuổi, tiền sử THA, ĐTĐ 2, đau ngực 40p. ECG ST chênh lên V1–V4.
                </p>
                <div class="border-l-4 border-primary bg-primary/5 py-2 pl-3">
                    <p class="font-bold">Chẩn đoán phù hợp nhất?</p>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex items-center gap-3 rounded-lg border border-[#16A34A] bg-[#16A34A]/5 p-3">
                    <span
                        class="flex size-6 items-center justify-center rounded-full bg-[#16A34A] text-xs font-bold text-white">B</span>
                    <span class="text-sm font-medium">Nhồi máu cơ tim cấp</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg border border-error bg-error/5 p-3">
                    <span
                        class="flex size-6 items-center justify-center rounded-full bg-error text-xs font-bold text-white">E</span>
                    <span class="text-sm">Trào ngược dạ dày</span>
                </div>
            </div>
        </main>
        <footer
            class="fixed bottom-0 left-0 z-50 flex w-full items-center justify-between border-t border-outline-variant bg-white px-4 py-4">
            <span class="material-symbols-outlined text-outline">folder_managed</span>
            <button type="button" @click="notesOpen = true" class="material-symbols-outlined text-outline"
                aria-label="Ghi chú">description</button>
            <button type="button"
                class="flex items-center gap-2 rounded-lg bg-primary px-6 py-3 font-bold text-on-primary">
                <span>Tiếp theo</span>
                <span class="material-symbols-outlined">arrow_forward</span>
            </button>
        </footer>
    </div>

    <!-- Notes Modal (pc-study-session-note.html) -->
    <div x-show="notesOpen" x-cloak x-transition.opacity
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
            <div class="space-y-4 p-6">
                <div class="space-y-2">
                    <label class="text-label-sm font-bold tracking-wider text-outline uppercase">Nội dung</label>
                    <textarea
                        class="min-h-[160px] w-full resize-none rounded-lg border border-outline-variant p-4 text-body-md outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="Nhập nội dung ghi chú của bạn tại đây..."></textarea>
                </div>
                <div class="space-y-2">
                    <label class="text-label-sm font-bold tracking-wider text-outline uppercase">Tags</label>
                    <div class="flex flex-wrap gap-2">
                        <span
                            class="rounded-full bg-primary/5 px-3 py-1 text-label-md font-medium text-primary">#TimMach</span>
                        <span
                            class="rounded-full bg-primary/5 px-3 py-1 text-label-md font-medium text-primary">#DuocLy</span>
                        <button type="button"
                            class="rounded-full border border-dashed border-outline-variant px-3 py-1 text-label-md text-outline transition-colors hover:bg-surface-container-low">+
                            Thêm tag</button>
                    </div>
                </div>
                <div class="pt-2">
                    <p class="text-label-sm text-outline-variant italic">Nguồn: Câu hỏi {{ $current }} - Nội khoa</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 bg-surface-container-low px-6 py-4">
                <button type="button" @click="notesOpen = false"
                    class="rounded-lg px-6 py-2 font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high">
                    Hủy
                </button>
                <button type="button" @click="notesOpen = false"
                    class="rounded-lg bg-primary-container px-6 py-2 font-bold text-on-primary transition-opacity hover:opacity-90">
                    Lưu ghi chú
                </button>
            </div>
        </div>
    </div>

    <!-- Flashcard Modal (pc-study-session-add-flashcard.html) -->
    <div x-show="flashcardOpen" x-cloak x-transition.opacity
        class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-on-background/40 backdrop-blur-sm" @click="flashcardOpen = false"></div>
        <div class="relative w-full max-w-xl overflow-hidden rounded-xl bg-white shadow-2xl"
            @click.outside="flashcardOpen = false">
            <div class="flex items-center justify-between px-6 pt-6 pb-2">
                <h2 class="text-xl font-bold text-on-surface">Flashcards</h2>
                <button type="button" @click="flashcardOpen = false"
                    class="text-on-surface-variant transition-colors hover:text-on-surface">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="px-6 pb-6">
                <div class="mb-6">
                    <label
                        class="mb-2 block text-label-sm font-bold tracking-widest text-on-surface-variant uppercase">Chọn
                        bộ thẻ</label>
                    <div class="relative">
                        <select
                            class="w-full appearance-none rounded-lg border border-outline-variant bg-surface-container-low px-4 py-2.5 text-body-md ring-2 ring-primary focus:ring-2 focus:ring-primary">
                            @foreach ($flashcardDecks as $deck)
                                <option>{{ $deck }}</option>
                            @endforeach
                        </select>
                        <span
                            class="material-symbols-outlined pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-on-surface-variant">expand_more</span>
                    </div>
                </div>

                <div class="mb-6 space-y-3">
                    <div class="flex items-start gap-4 rounded-xl border border-primary bg-primary/5 p-4 transition-all">
                        <div class="flex-1">
                            <div class="mb-2 flex items-start justify-between">
                                <span
                                    class="block text-[10px] font-bold tracking-widest text-primary uppercase">Mặt
                                    trước</span>
                            </div>
                            <p class="text-body-md font-medium text-on-surface">câu hỏi {{ $current }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 rounded-xl border border-primary bg-primary/5 p-4 transition-all">
                        <div class="flex-1 space-y-3">
                            <div>
                                <div class="mb-1 flex items-start justify-between">
                                    <span
                                        class="block text-[10px] font-bold tracking-widest text-primary uppercase">Mặt
                                        Sau</span>
                                </div>
                                <p class="text-body-md font-medium text-on-surface">Nhồi máu cơ tim cấp thành trước</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="button" @click="flashcardOpen = false"
                        class="w-full rounded-lg bg-primary py-3 font-bold text-on-primary transition-colors hover:bg-primary-container">
                        Thêm vào thẻ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigator Modal (pc-study-session-navigator.html) -->
    <div x-show="navigatorOpen" x-cloak x-transition.opacity
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
        <div class="absolute inset-0" @click="navigatorOpen = false"></div>
        <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-lg"
            @click.outside="navigatorOpen = false">
            <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                <h2 class="text-headline-sm font-bold text-on-surface">Bản đồ câu hỏi</h2>
                <button type="button" @click="navigatorOpen = false"
                    class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined text-outline">close</span>
                </button>
            </div>

            <div
                class="flex flex-wrap gap-6 border-b border-outline-variant bg-surface-container-low px-6 py-4">
                <div class="flex items-center gap-2">
                    <div class="size-3 rounded-full bg-[#0F766E]"></div>
                    <span class="text-label-md text-on-surface-variant">Đã làm:
                        <span class="font-bold text-on-surface">{{ count($completed) }}/{{ $total }}</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="size-3 rounded-full border border-outline-variant bg-white"></div>
                    <span class="text-label-md text-on-surface-variant">Chưa làm:
                        <span
                            class="font-bold text-on-surface">{{ $total - count($completed) }}/{{ $total }}</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-[#F59E0B]"
                        style="font-variation-settings: 'FILL' 1;">flag</span>
                    <span class="text-label-md text-on-surface-variant">Đã gắn cờ:
                        <span class="font-bold text-on-surface">{{ count($flagged) }}</span></span>
                </div>
            </div>

            <div class="overflow-y-auto p-6">
                <div class="grid grid-cols-4 gap-3 sm:grid-cols-6 md:grid-cols-8">
                    @foreach ($navigatorQuestions as $q)
                        @if ($q['state'] === 'completed')
                            <button type="button" @click="navigatorOpen = false"
                                class="relative flex aspect-square flex-col items-center justify-center rounded-lg bg-[#0F766E] text-white">
                                <span class="text-label-md font-bold">{{ $q['n'] }}</span>
                                <span
                                    class="material-symbols-outlined absolute bottom-1 text-[12px]">check</span>
                            </button>
                        @elseif ($q['state'] === 'flagged')
                            <button type="button" @click="navigatorOpen = false"
                                class="relative flex aspect-square items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-high">
                                <span class="text-label-md">{{ $q['n'] }}</span>
                                <span
                                    class="material-symbols-outlined absolute top-0.5 right-0.5 text-[14px] text-[#F59E0B]"
                                    style="font-variation-settings: 'FILL' 1;">flag</span>
                            </button>
                        @elseif ($q['state'] === 'active')
                            <button type="button" @click="navigatorOpen = false"
                                class="flex aspect-square items-center justify-center rounded-lg border-2 border-primary bg-primary/5 font-bold text-primary">
                                {{ $q['n'] }}
                            </button>
                        @else
                            <button type="button" @click="navigatorOpen = false"
                                class="flex aspect-square items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-high">
                                {{ $q['n'] }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end border-t border-outline-variant px-6 py-4">
                <button type="button" @click="navigatorOpen = false"
                    class="rounded-lg bg-primary px-6 py-2 font-bold text-on-primary transition-opacity hover:opacity-90">
                    Đóng
                </button>
            </div>
        </div>
    </div>

    <!-- Selection highlight toolbar — chỉ hiện khi bôi đen text -->
    <div x-show="selectionBar.show" x-cloak
        class="pointer-events-auto fixed z-[90] flex -translate-x-1/2 -translate-y-full items-center gap-2 rounded-lg border border-outline-variant bg-white p-1.5 shadow-lg"
        :style="{ left: selectionBar.x + 'px', top: selectionBar.y + 'px' }">
        @foreach ($highlightColors as $color)
            <button type="button" title="{{ $color['title'] }}"
                @mousedown.prevent
                @click="applyColor('{{ $color['hex'] }}')"
                class="size-5 rounded-full shadow-sm transition-transform hover:scale-110"
                style="background-color: {{ $color['hex'] }}"></button>
        @endforeach
        <div class="mx-0.5 h-3 w-px bg-outline-variant"></div>
        <button type="button" @mousedown.prevent @click="clearHighlight()"
            class="material-symbols-outlined text-[16px] text-outline transition-colors hover:text-error"
            title="Xóa tô màu">delete</button>
    </div>
    </div>
</x-layouts.auth>
