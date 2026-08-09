@php
    /**
     * @var \Modules\StudyPlan\Models\StudyPlan $plan
     * @var \Modules\StudyPlan\Models\StudyPlanTask $task
     * @var \Modules\QuestionBank\Models\QuestionSession $session
     * @var \Modules\QuestionBank\Models\Question $question
     * @var \Modules\QuestionBank\Models\QuestionAttempt|null $attempt
     */
    $playerConfig = $playerConfig ?? [
        'page_title' => 'Phiên học kế hoạch',
        'header_title' => $task->title(),
        'header_subtitle' => $plan->name . ' · ' . $task->date->format('d/m/Y'),
        'saved_label' => $task->done . '/' . $task->target . ' đã lưu',
        'exit_url' => route('study-plan.detail', $plan),
        'pause_url' => null,
        'finish_url' => null,
        'summary_url' => route('study-plan.session.summary', [$plan, $task]),
        'annotate_url' => route('study-plan.session.annotate', [$plan, $task]),
        'answer_url' => route('study-plan.session.answer', [$plan, $task]),
        'question_route' => 'study-plan.session',
        'question_route_parameters' => ['plan' => $plan, 'task' => $task],
        'incomplete_label' => 'Bạn chưa hoàn thành nhiệm vụ hôm nay',
        'exit_message' => 'Tiến trình đã được lưu — có thể tiếp tục sau từ kế hoạch học tập.',
    ];
    $exitUrl = $playerConfig['exit_url'];
    $summaryUrl = $playerConfig['summary_url'];
    $pauseUrl = $playerConfig['pause_url'];
    $finishUrl = $playerConfig['finish_url'];
    $questionUrl = fn (int $position): string => route(
        $playerConfig['question_route'],
        [...$playerConfig['question_route_parameters'], 'index' => $position],
    );
    $progress = $total > 0 ? (int) round(($index + 1) / $total * 100) : 0;
    $selectedOptionIds = $attempt?->selected_option_ids ?? [];
    $isAnswered = $attempt !== null;
    $nextIndex = $index + 1 < $total ? $index + 1 : null;
    $prevIndex = $index > 0 ? $index - 1 : null;
    $keyInfoRenderer = app(\Modules\QuestionBank\Services\QuestionKeyInfoRenderer::class);
    $keyInfo = $keyInfoRenderer->resolvePhrases(
        (string) $question->stem,
        (array) ($question->key_info ?? []),
    );
    $hasKeyInfo = $keyInfo !== [];
    $keyInfoHtml = $keyInfoRenderer->render((string) $question->stem, $keyInfo);
    $attendingTip = \App\Support\Html\SafeHtml::forDisplay((string) ($question->attending_tip ?? ''));
    if ($attendingTip === '' && $hasKeyInfo) {
        $attendingTip = e('Hãy tập trung vào các dấu hiệu: '.implode('; ', $keyInfo)
            .'. Kết hợp chúng để xác định chẩn đoán hoặc bước xử trí phù hợp nhất.');
    }
    $hasAttendingTip = $attendingTip !== '';

    $tools = [
        ['icon' => 'bookmark', 'label' => 'Lưu câu hỏi'],
        ['icon' => 'flag', 'label' => 'Gắn cờ', 'action' => 'flag'],
        ['icon' => 'description', 'label' => 'Ghi chú', 'action' => 'notes'],
        ['icon' => 'menu_book', 'label' => 'Nghiên cứu', 'action' => 'research'],
        ['icon' => 'drive_file_rename_outline', 'label' => 'Tô màu văn bản', 'action' => 'highlight'],
    ];

    $labReferenceGroups = \Modules\QuestionBank\Support\LabReferenceValues::groups();

    $highlightColors = [
        ['hex' => '#EF4444', 'title' => 'Đỏ'],
        ['hex' => '#F59E0B', 'title' => 'Vàng'],
        ['hex' => '#10B981', 'title' => 'Xanh lá'],
    ];
    $note = $note ?? '';
    $stemHtml = $stemHtml ?? \App\Support\Html\SafeHtml::forDisplay((string) $question->stem);
    $flagged = (bool) ($flagged ?? false);
    $flaggedIds = $flaggedIds ?? [];
    $sessionIncomplete = count($answeredIds) < $total;
@endphp

<x-layouts.auth :title="$playerConfig['page_title']">
    <div x-data="{
        notesOpen: false,
        navigatorOpen: false,
        exitOpen: false,
        labQuery: '',
        activeLabTab: 'serum',
        labReferenceGroups: @js($labReferenceGroups),
        researchOpen: false,
        highlightMode: false,
        keyInfoEnabled: @js($isAnswered && $hasKeyInfo),
        keyInfoUsed: false,
        hasKeyInfo: @js($hasKeyInfo),
        attendingTipOpen: @js($isAnswered && $hasAttendingTip),
        attendingTipUsed: false,
        attendingTip: @js($attendingTip),
        hasAttendingTip: @js($hasAttendingTip),
        flagged: @js($flagged),
        sessionIncomplete: @js($sessionIncomplete),
        exitUrl: @js($exitUrl),
        selectionBar: { show: false, x: 0, y: 0 },
        noteText: @js($note),
        noteSaving: false,
        noteSaved: false,
        stemHtml: @js($stemHtml),
        annotateUrl: @js($playerConfig['annotate_url']),
        csrf: @js(csrf_token()),
        questionId: @js($question->id),
        _historyHandler: null,
        init() {
            this.installBrowserExitGuard();
        },
        destroy() {
            if (this._historyHandler) window.removeEventListener('popstate', this._historyHandler);
        },
        installBrowserExitGuard() {
            if (!window.history.state?.sessionExitGuard) {
                window.history.pushState({ sessionExitGuard: true }, '', window.location.href);
            }
            this._historyHandler = () => {
                window.history.pushState({ sessionExitGuard: true }, '', window.location.href);
                if (!this.exitOpen) this.requestExit();
            };
            window.addEventListener('popstate', this._historyHandler);
        },
        requestExit() {
            if (this.sessionIncomplete) {
                this.exitOpen = true;
                return;
            }
            window.location.href = this.exitUrl;
        },
        openResearch() {
            this.notesOpen = false;
            this.navigatorOpen = false;
            this.researchOpen = true;
        },
        filteredLabs() {
            const rows = this.labReferenceGroups[this.activeLabTab]?.rows || [];
            const query = this.labQuery.trim().toLocaleLowerCase('vi');
            if (!query) return rows;
            return rows.filter((item) =>
                !item.section && ((item.test || '') + ' ' + (item.reference || '') + ' ' + (item.si || ''))
                    .toLocaleLowerCase('vi').includes(query)
            );
        },
        toggleHighlight() {
            if (this.keyInfoEnabled) this.keyInfoEnabled = false;
            if (this.attendingTipOpen) this.attendingTipOpen = false;
            this.highlightMode = !this.highlightMode;
            if (!this.highlightMode) this.selectionBar.show = false;
            this.$nextTick(() => {
                const stem = document.getElementById('session-stem');
                if (stem) stem.classList.toggle('cursor-text', this.highlightMode);
            });
        },
        toggleKeyInfo() {
            if (!this.hasKeyInfo) return;
            this.keyInfoEnabled = !this.keyInfoEnabled;
            this.highlightMode = false;
            this.selectionBar.show = false;
            if (this.keyInfoEnabled) {
                this.keyInfoUsed = true;
                this.persistAnnotation({ key_info_used: true });
            }
        },
        toggleAttendingTip() {
            if (!this.hasAttendingTip) return;
            this.attendingTipOpen = !this.attendingTipOpen;
            this.highlightMode = false;
            this.selectionBar.show = false;
            if (this.attendingTipOpen) {
                this.attendingTipUsed = true;
                this.persistAnnotation({ attending_tip_used: true });
            }
        },
        async toggleFlag() {
            this.flagged = !this.flagged;
            await this.persistAnnotation({ flagged: this.flagged });
        },
        onTextSelect() {
            if (!this.highlightMode) { this.selectionBar.show = false; return; }
            setTimeout(() => {
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0 || sel.isCollapsed || !sel.toString().trim()) {
                    this.selectionBar.show = false;
                    return;
                }
                const rect = sel.getRangeAt(0).getBoundingClientRect();
                if (rect.width === 0 && rect.height === 0) { this.selectionBar.show = false; return; }
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
            const stemEl = document.getElementById('session-stem');
            if (!stemEl || !stemEl.contains(range.commonAncestorContainer)) return;
            const mark = document.createElement('mark');
            mark.className = 'rounded-sm';
            mark.setAttribute('data-hl', hex);
            mark.setAttribute('style', 'background-color: ' + hex + '4D');
            try {
                range.surroundContents(mark);
            } catch (e) {
                const fragment = range.extractContents();
                mark.appendChild(fragment);
                range.insertNode(mark);
            }
            sel.removeAllRanges();
            this.selectionBar.show = false;
            this.stemHtml = stemEl.innerHTML;
            this.persistAnnotation({ stem_html: this.stemHtml });
        },
        async persistAnnotation(payload) {
            try {
                await fetch(this.annotateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ question_id: this.questionId, ...payload }),
                });
            } catch (e) {
                // Không chặn làm bài nếu lưu ghi chú/highlight lỗi tạm thời.
            }
        },
        async saveNote() {
            if (this.noteSaving) return;
            this.noteSaving = true;
            this.noteSaved = false;
            await this.persistAnnotation({ note: this.noteText });
            this.noteSaving = false;
            this.noteSaved = true;
            this.notesOpen = false;
        },
    }" @keydown.escape.window="notesOpen = false; navigatorOpen = false; exitOpen = false; researchOpen = false; selectionBar.show = false"
        @mouseup.window="onTextSelect()">

        <div class="flex min-h-screen flex-col bg-white">
            <header
                class="sticky top-0 z-50 flex h-header-height w-full items-center border-b border-outline-variant bg-white px-4 md:px-margin-desktop">
                <div class="flex flex-1 items-center gap-4">
                    <button type="button" @click="requestExit()"
                        class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-surface-container-high"
                        aria-label="Thoát phiên làm bài">
                        <span class="material-symbols-outlined text-outline">close</span>
                    </button>
                    <div class="hidden flex-col sm:flex">
                        <span class="font-label-md text-label-md font-bold text-primary">{{ $playerConfig['header_title'] }}</span>
                        <span class="text-[10px] font-bold tracking-wider text-outline uppercase">
                            {{ $playerConfig['header_subtitle'] }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-1 flex-col items-center gap-1">
                    <span class="font-label-md text-label-md text-on-surface-variant">Câu {{ $index + 1 }} /
                        {{ $total }}</span>
                    <div class="h-1.5 w-40 overflow-hidden rounded-full bg-surface-container-highest md:w-64">
                        <div class="h-full bg-primary transition-all duration-500" style="width: {{ $progress }}%"></div>
                    </div>
                </div>
                <div class="flex flex-1 items-center justify-end gap-3">
                    <span class="hidden items-center gap-1 text-primary sm:flex">
                        <span class="material-symbols-outlined text-[18px]"
                            style="font-variation-settings: 'FILL' 1;">cloud_done</span>
                        <span class="font-label-sm text-label-sm">{{ $playerConfig['saved_label'] }}</span>
                    </span>
                    <button type="button" @click="navigatorOpen = true"
                        class="flex items-center gap-2 rounded-lg border border-outline-variant px-3 py-1.5 transition-colors hover:bg-surface-container-high">
                        <span class="material-symbols-outlined text-[20px]">grid_view</span>
                        <span class="hidden font-label-md text-label-md md:inline">Navigator</span>
                    </button>
                </div>
            </header>

            <main class="flex flex-1 bg-white pb-28">
                <aside x-show="!researchOpen"
                    class="group sticky top-header-height hidden h-[calc(100vh-var(--spacing-header-height))] w-16 shrink-0 overflow-y-auto border-r border-outline-variant bg-white transition-all duration-300 hover:w-56 lg:block">
                    <nav class="space-y-2 p-4">
                        @foreach ($tools as $tool)
                            <button type="button"
                                @if (($tool['action'] ?? null) === 'notes') @click="notesOpen = true"
                                @elseif (($tool['action'] ?? null) === 'research') @click="openResearch()"
                                @elseif (($tool['action'] ?? null) === 'highlight') @click="toggleHighlight()"
                                @elseif (($tool['action'] ?? null) === 'flag') @click="toggleFlag()" @endif
                                @if (($tool['action'] ?? null) === 'research') data-testid="research-reference-toggle" @endif
                                @if (($tool['action'] ?? null) === 'research')
                                    class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 transition-colors group-hover:justify-start group-hover:px-3"
                                    :class="researchOpen ? 'bg-primary/5 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                @elseif (($tool['action'] ?? null) === 'highlight')
                                    class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 transition-colors group-hover:justify-start group-hover:px-3"
                                    :class="highlightMode ? 'bg-primary/5 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                @elseif (($tool['action'] ?? null) === 'flag')
                                    class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 transition-colors group-hover:justify-start group-hover:px-3"
                                    :class="flagged ? 'bg-amber-50 text-amber-600' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                @else
                                    class="flex w-full items-center justify-center gap-3 rounded-lg px-0 py-2.5 text-on-surface-variant transition-colors group-hover:justify-start group-hover:px-3 hover:bg-surface-container-high hover:text-primary"
                                @endif>
                                <span class="material-symbols-outlined text-[20px]"
                                    @if (($tool['action'] ?? null) === 'flag')
                                        :class="flagged && 'fill-1'"
                                    @endif>{{ $tool['icon'] }}</span>
                                <span
                                    class="overflow-hidden text-label-md font-medium whitespace-nowrap opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                    @if (($tool['action'] ?? null) === 'flag')
                                        x-text="flagged ? 'Bỏ gắn cờ' : 'Gắn cờ'"
                                    @endif>{{ $tool['label'] }}</span>
                            </button>
                        @endforeach
                    </nav>
                </aside>

                @php
                    $optionPayload = $question->options->map(fn ($option) => [
                        'id' => (int) $option->id,
                        'label' => $option->label,
                        'content' => $option->content,
                        'correct' => (bool) $option->is_correct,
                        'explanation' => $option->explanation,
                    ])->values();
                @endphp

                <div class="w-full overflow-y-auto"
                    x-data="{
                        options: @js($optionPayload),
                        selected: @js(isset($selectedOptionIds[0]) ? (int) $selectedOptionIds[0] : null),
                        revealed: @js($isAnswered),
                        saving: false,
                        startedAt: Date.now(),
                        elapsed: @js($isAnswered ? (int) ($attempt?->time_spent_seconds ?? 0) : 0),
                        running: @js(! $isAnswered),
                        _timer: null,
                        questionExplanation: @js(\App\Support\Html\SafeHtml::forDisplay((string) ($question->explanation ?? ''))),
                        saveUrl: @js($playerConfig['answer_url']),
                        csrf: @js(csrf_token()),
                        questionId: @js($question->id),
                        index: @js($index),
                        init() {
                            if (!this.running) return;
                            this.startedAt = Date.now();
                            this.elapsed = 0;
                            this._timer = setInterval(() => {
                                if (!this.running) return;
                                this.elapsed = Math.floor((Date.now() - this.startedAt) / 1000);
                            }, 200);
                        },
                        stopTimer() {
                            if (!this.running && this._timer === null) return;
                            this.running = false;
                            this.elapsed = Math.max(0, Math.round((Date.now() - this.startedAt) / 1000));
                            if (this._timer) {
                                clearInterval(this._timer);
                                this._timer = null;
                            }
                        },
                        formatTime() {
                            const total = Math.max(0, this.elapsed | 0);
                            const m = Math.floor(total / 60);
                            const s = total % 60;
                            return m + ':' + String(s).padStart(2, '0');
                        },
                        choose(id) {
                            if (this.revealed) return;
                            this.selected = id;
                            this.stopTimer();
                            this.revealed = true;
                            if (this.hasKeyInfo) this.keyInfoEnabled = true;
                            if (this.hasAttendingTip) this.attendingTipOpen = true;
                            this.persist(id);
                        },
                        isCorrect() {
                            const picked = this.options.find((o) => o.id === this.selected);
                            return picked ? picked.correct : false;
                        },
                        wrapClass(option) {
                            if (!this.revealed) {
                                return 'border-outline-variant bg-white hover:border-primary/50 hover:bg-primary/5 cursor-pointer';
                            }
                            if (option.correct) return 'border-[#16A34A] bg-[#16A34A]/5';
                            return 'border-error bg-error/5';
                        },
                        badgeClass(option) {
                            if (!this.revealed) return 'border border-outline-variant text-on-surface-variant';
                            if (option.correct) return 'bg-[#16A34A] text-white';
                            return 'bg-error text-white';
                        },
                        showDetail(option) {
                            return this.revealed;
                        },
                        detailText(option) {
                            if (option.explanation) return option.explanation;
                            if (option.correct) return 'Đây là đáp án đúng.';
                            return 'Đây không phải đáp án đúng.';
                        },
                        async persist(optionId) {
                            if (this.saving) return;
                            this.saving = true;
                            try {
                                await fetch(this.saveUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': this.csrf,
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: JSON.stringify({
                                        question_id: this.questionId,
                                        option_ids: [optionId],
                                        time_spent_seconds: this.elapsed,
                                        index: this.index,
                                    }),
                                });
                            } catch (e) {
                                // UI đã hiện giải thích; lưu lại không chặn học.
                            } finally {
                                this.saving = false;
                            }
                        },
                    }">
                    <div class="flex min-h-full w-full flex-col items-stretch lg:flex-row">
                        <aside x-show="researchOpen" x-cloak x-transition.opacity
                            class="z-30 w-full shrink-0 overflow-hidden border-r border-outline-variant bg-white lg:sticky lg:top-0 lg:h-[calc(100vh-var(--spacing-header-height)-5rem)] lg:w-1/2"
                            data-testid="research-reference-panel">
                            @include('studyplan::partials.lab-reference-table', [
                                'labPanelPrefix' => 'research-lab',
                                'labCloseAction' => 'researchOpen = false',
                            ])
                        </aside>

                        <div class="w-full flex-1 space-y-6 px-4 py-8 md:px-10"
                            :class="researchOpen ? 'max-w-none' : 'mx-auto max-w-4xl'"
                            data-testid="question-answer-pane">
                        <div x-show="researchOpen" x-cloak
                            class="flex items-center gap-2 border-b border-outline-variant pb-3 text-on-surface-variant">
                            <span class="material-symbols-outlined text-[20px]">quiz</span>
                            <span class="font-label-md text-label-md font-bold">Câu hỏi – câu trả lời</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 rounded-full bg-surface-container-highest px-3 py-1">
                                <span class="size-2 rounded-full bg-primary"></span>
                                <span class="font-label-sm text-label-sm font-bold text-on-surface-variant uppercase">
                                    {{ $question->topic?->name ?? 'Tổng hợp' }} · {{ $question->difficulty->label() }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1.5 rounded-full px-3 py-1 tabular-nums"
                                    :class="running
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-surface-container-highest text-on-surface-variant'">
                                    <span class="material-symbols-outlined text-[18px]"
                                        :class="running && 'fill-1'">timer</span>
                                    <span class="font-label-sm text-label-sm font-bold" x-text="formatTime()"></span>
                                </div>
                                <span x-cloak x-show="revealed"
                                    class="rounded-full px-3 py-1 text-label-sm font-bold"
                                    :class="isCorrect() ? 'bg-[#16A34A]/10 text-[#16A34A]' : 'bg-error/10 text-error'"
                                    x-text="isCorrect() ? 'Đúng' : 'Sai'"></span>
                            </div>
                        </div>

                        <article class="space-y-6">
                            <div x-show="keyInfoUsed" x-cloak
                                class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-700 uppercase"
                                data-testid="key-info-used-badge">
                                <span class="material-symbols-outlined text-[15px] fill-1">check_circle</span>
                                <span>Đã dùng kiến thức</span>
                            </div>
                            <template x-if="!keyInfoEnabled">
                                <div id="session-stem"
                                    class="prose prose-sm max-w-none font-body-lg text-body-lg leading-relaxed text-on-surface select-text">{!! $stemHtml !!}</div>
                            </template>
                            <template x-if="keyInfoEnabled">
                                <div class="prose prose-sm max-w-none font-body-lg text-body-lg leading-relaxed text-on-surface select-text"
                                    data-testid="key-info-stem">{!! $keyInfoHtml !!}</div>
                            </template>
                        </article>

                        <div class="flex min-h-12 items-center border-y border-outline-variant bg-surface-container-lowest px-1"
                            data-testid="question-knowledge-toolbar">
                            <button type="button" @click="toggleKeyInfo()" :disabled="!hasKeyInfo"
                                class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                :class="keyInfoEnabled
                                    ? 'border-amber-600 text-amber-700'
                                    : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                title="{{ $hasKeyInfo ? 'Gạch chân kiến thức chính' : 'Câu này chưa có kiến thức được đánh dấu' }}"
                                :aria-pressed="keyInfoEnabled">
                                <span class="material-symbols-outlined text-[18px]">format_align_left</span>
                                <span>Kiến thức</span>
                            </button>
                            <button type="button" @click="toggleAttendingTip()" :disabled="!hasAttendingTip"
                                class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                :class="attendingTipOpen
                                    ? 'border-amber-600 text-amber-700'
                                    : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                title="{{ $hasAttendingTip ? 'Mở gợi ý cho câu hỏi' : 'Câu này chưa có gợi ý' }}"
                                :aria-pressed="attendingTipOpen"
                                data-testid="attending-tip-toggle">
                                <span class="material-symbols-outlined text-[18px]">help</span>
                                <span>Gợi ý</span>
                            </button>
                        </div>

                        <div x-show="attendingTipOpen" x-cloak x-transition class="space-y-3"
                            data-testid="attending-tip-panel">
                            <div x-show="attendingTipUsed"
                                class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-700 uppercase"
                                data-testid="attending-tip-used-badge">
                                <span class="material-symbols-outlined text-[15px] fill-1">check_circle</span>
                                <span>Đã dùng gợi ý</span>
                            </div>
                            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-on-surface">
                                <span class="material-symbols-outlined mt-0.5 shrink-0 text-amber-700">stethoscope</span>
                                <div class="prose prose-sm max-w-none font-body-md text-body-md leading-relaxed italic" x-html="attendingTip"></div>
                            </div>
                        </div>

                        <section class="space-y-3">
                            <template x-for="option in options" :key="option.id">
                                <div class="flex w-full flex-col overflow-hidden rounded-xl border text-left transition-all"
                                    :class="wrapClass(option)"
                                    @click="choose(option.id)"
                                    role="button"
                                    :tabindex="revealed ? -1 : 0">
                                    <div class="flex items-start gap-4 p-4">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full font-bold"
                                            :class="badgeClass(option)" x-text="option.label"></span>
                                        <div class="min-w-0 flex-1 space-y-1 pt-1">
                                            <span class="block font-body-md text-body-md text-on-surface"
                                                x-text="option.content"></span>
                                            <template x-if="revealed && option.id === selected">
                                                <span class="inline-block rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                                    :class="option.correct ? 'bg-[#16A34A] text-white' : 'bg-error text-white'"
                                                    x-text="option.correct ? 'Lựa chọn của bạn · Đúng' : 'Lựa chọn của bạn'"></span>
                                            </template>
                                        </div>
                                        <template x-if="revealed && option.correct">
                                            <span class="material-symbols-outlined text-[#16A34A]"
                                                style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                        </template>
                                        <template x-if="revealed && !option.correct">
                                            <span class="material-symbols-outlined text-error"
                                                style="font-variation-settings: 'FILL' 1;">cancel</span>
                                        </template>
                                    </div>
                                    <div x-show="showDetail(option)" x-cloak
                                        class="space-y-2 border-t border-outline-variant/40 px-4 pb-4 pl-16">
                                        <p class="text-label-sm font-bold tracking-wide uppercase"
                                            :class="option.correct ? 'text-[#16A34A]' : 'text-error'"
                                            x-text="option.correct ? 'Đáp án đúng' : 'Vì sao sai'"></p>
                                        <p class="text-body-sm leading-relaxed text-on-surface-variant"
                                            x-text="detailText(option)"></p>
                                    </div>
                                </div>
                            </template>
                        </section>

                        <div x-show="revealed && questionExplanation" x-cloak
                            class="rounded-r-lg border-l-4 border-primary bg-primary/5 py-3 pr-4 pl-4">
                            <p class="mb-1 text-label-sm font-bold tracking-wider text-primary uppercase">Giải thích</p>
                            <div class="prose prose-sm max-w-none text-body-md leading-relaxed text-on-surface" x-html="questionExplanation"></div>
                        </div>
                    </div>
                    </div>

                    <footer
                        class="fixed bottom-0 left-0 z-50 flex w-full items-center justify-between border-t border-outline-variant bg-white px-4 py-4 shadow-lg md:px-margin-desktop">
                        @if ($prevIndex !== null)
                            <a href="{{ $questionUrl($prevIndex) }}"
                                class="flex items-center gap-2 px-4 py-2 text-on-surface-variant transition-colors hover:text-on-surface">
                                <span class="material-symbols-outlined">chevron_left</span>
                                <span class="font-bold">Câu trước</span>
                            </a>
                        @else
                            <span></span>
                        @endif

                        <div x-show="!revealed" class="text-body-sm text-on-surface-variant">
                            Chọn một đáp án để xem giải thích
                        </div>

                        <div x-cloak x-show="revealed">
                            @if ($nextIndex !== null)
                                <a href="{{ $questionUrl($nextIndex) }}"
                                    class="flex items-center gap-3 rounded-lg bg-primary px-8 py-3 font-bold text-on-primary transition-all hover:opacity-90 active:scale-95">
                                    <span>Câu tiếp theo</span>
                                    <span class="material-symbols-outlined">arrow_forward</span>
                                </a>
                            @else
                                @if ($finishUrl)
                                    <form method="POST" action="{{ $finishUrl }}">
                                        @csrf
                                        <button type="submit"
                                            class="flex items-center gap-3 rounded-lg bg-primary px-8 py-3 font-bold text-on-primary transition-all hover:opacity-90">
                                            <span>Hoàn thành</span>
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ $summaryUrl }}"
                                        class="flex items-center gap-3 rounded-lg bg-primary px-8 py-3 font-bold text-on-primary transition-all hover:opacity-90">
                                        <span>Hoàn thành</span>
                                        <span class="material-symbols-outlined">check_circle</span>
                                    </a>
                                @endif
                            @endif
                        </div>
                    </footer>
                </div>
            </main>
        </div>

        <!-- Notes -->
        <div x-show="notesOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center p-4">
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
                    <textarea x-model="noteText"
                        class="min-h-[160px] w-full resize-none rounded-lg border border-outline-variant p-4 text-body-md outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="Nhập nội dung ghi chú của bạn tại đây..."></textarea>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-label-sm text-outline-variant italic">
                            Ghi chú gắn với câu hỏi này và hiện lại khi xem kết quả.
                        </p>
                        <button type="button" @click="saveNote()" :disabled="noteSaving"
                            class="shrink-0 rounded-lg bg-primary-container px-4 py-2 font-label-md text-white transition-opacity hover:opacity-90 disabled:opacity-60">
                            <span x-text="noteSaving ? 'Đang lưu…' : 'Lưu ghi chú'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigator -->
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
                    <div class="flex flex-wrap gap-6 border-b border-outline-variant bg-surface-container-low px-6 py-4">
                    <div class="flex items-center gap-2">
                        <div class="size-3 rounded-full bg-[#0F766E]"></div>
                        <span class="text-label-md text-on-surface-variant">Đã làm:
                            <span class="font-bold text-on-surface">{{ count($answeredIds) }}/{{ $total }}</span></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="size-3 rounded-full border border-outline-variant bg-white"></div>
                        <span class="text-label-md text-on-surface-variant">Chưa làm:
                            <span class="font-bold text-on-surface">{{ $total - count($answeredIds) }}/{{ $total }}</span></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-amber-600"
                            style="font-variation-settings: 'FILL' 1;">flag</span>
                        <span class="text-label-md text-on-surface-variant">Gắn cờ:
                            <span class="font-bold text-on-surface">{{ count($flaggedIds) }}</span></span>
                    </div>
                </div>
                <div class="overflow-y-auto p-6">
                    <div class="grid grid-cols-4 gap-3 sm:grid-cols-6 md:grid-cols-8">
                        @foreach ($questionIds as $position => $questionId)
                            @php
                                $isFlaggedCell = in_array($questionId, $flaggedIds, true);
                                $state = match (true) {
                                    $position === $index => 'active',
                                    in_array($questionId, $answeredIds, true) => 'answered',
                                    default => 'unanswered',
                                };
                                $classes = match ($state) {
                                    'active' => 'border-2 border-primary bg-primary/5 font-bold text-primary',
                                    'answered' => 'bg-[#0F766E] text-white',
                                    default => 'border border-outline-variant text-on-surface-variant hover:bg-surface-container-high',
                                };
                            @endphp
                            <a href="{{ $questionUrl($position) }}"
                                class="relative flex aspect-square items-center justify-center rounded-lg {{ $classes }}">
                                {{ $position + 1 }}
                                @if ($isFlaggedCell)
                                    <span class="absolute top-0.5 right-0.5 material-symbols-outlined text-[12px] {{ $state === 'answered' ? 'text-amber-200' : 'text-amber-600' }}"
                                        style="font-variation-settings: 'FILL' 1;">flag</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Highlight toolbar -->
        <div x-show="selectionBar.show" x-cloak
            class="pointer-events-auto fixed z-[90] flex -translate-x-1/2 -translate-y-full items-center gap-2 rounded-lg border border-outline-variant bg-white p-1.5 shadow-lg"
            :style="{ left: selectionBar.x + 'px', top: selectionBar.y + 'px' }">
            @foreach ($highlightColors as $color)
                <button type="button" title="{{ $color['title'] }}" @mousedown.prevent
                    @click="applyColor('{{ $color['hex'] }}')"
                    class="size-5 rounded-full shadow-sm transition-transform hover:scale-110"
                    style="background-color: {{ $color['hex'] }}"></button>
            @endforeach
        </div>

        <!-- Exit confirm (chưa làm xong) -->
        <div x-show="exitOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[110] flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
            <div class="absolute inset-0" @click="exitOpen = false"></div>
            <div class="relative flex w-full max-w-md flex-col items-center rounded-[24px] border border-outline-variant bg-surface-container-lowest p-8 text-center shadow-2xl"
                @click.outside="exitOpen = false">
                <div class="mb-6 flex size-16 items-center justify-center rounded-2xl bg-primary-container/10">
                    <span class="material-symbols-outlined text-4xl text-primary"
                        style="font-variation-settings: 'FILL' 1;">pause</span>
                </div>
                <h3 class="mb-3 font-headline-md text-headline-md text-on-surface">Bạn muốn thoát?</h3>
                <p class="mb-10 font-body-md text-body-md leading-relaxed text-on-surface-variant">
                    {{ $playerConfig['incomplete_label'] }}
                    ({{ count($answeredIds) }}/{{ $total }} câu).
                    {{ $playerConfig['exit_message'] }}
                </p>
                <div class="flex w-full flex-col gap-3">
                    @if ($pauseUrl)
                        <form method="POST" action="{{ $pauseUrl }}" class="w-full">
                            @csrf
                            <input type="hidden" name="current_index" value="{{ $index }}">
                            <button type="submit"
                                class="w-full rounded-xl bg-gradient-to-br from-primary-container to-primary py-3.5 font-label-md text-label-md font-bold text-white shadow-lg transition-all hover:opacity-90 active:scale-[0.98]">
                                Lưu &amp; thoát
                            </button>
                        </form>
                    @else
                        <a href="{{ $exitUrl }}"
                            class="w-full rounded-xl bg-gradient-to-br from-primary-container to-primary py-3.5 font-label-md text-label-md font-bold text-white shadow-lg transition-all hover:opacity-90 active:scale-[0.98]">
                            Lưu &amp; thoát
                        </a>
                    @endif
                    <button type="button" @click="exitOpen = false"
                        class="w-full rounded-xl border border-outline py-3.5 font-label-md text-label-md font-bold text-primary transition-colors hover:bg-surface-container-high">
                        Tiếp tục làm bài
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.auth>
