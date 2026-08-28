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
    $hasHintMarks = str_contains((string) $question->stem, 'data-hint');
    $hasKeyInfo = $keyInfo !== [] || $hasHintMarks;
    $keyInfoHtml = $keyInfoRenderer->render((string) $question->stem, $keyInfo);
    $attendingTip = \App\Support\Html\SafeHtml::forDisplay((string) ($question->attending_tip ?? ''));
    $hasAttendingTip = $attendingTip !== '';

    $tools = [
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
    $bookmarked = (bool) ($bookmarked ?? false);
    $bookmarkUrl = $bookmarkUrl ?? route('bookmarks.questions.set', $question);
    $sessionIncomplete = count($answeredIds) < $total;
    $stemImageUrl = $question->stemImageUrl();
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
        imageViewerOpen: false,
        imageViewerSrc: '',
        flagged: @js($flagged),
        bookmarked: @js($bookmarked),
        bookmarkSaving: false,
        bookmarkError: '',
        bookmarkUrl: @js($bookmarkUrl),
        folderModalOpen: false,
        folderSearchQuery: '',
        folders: [],
        foldersLoading: false,
        foldersError: '',
        async openFolderModal() {
            this.folderModalOpen = true;
            this.folderSearchQuery = '';
            this.foldersLoading = true;
            this.foldersError = '';
            try {
                const response = await fetch(`/bookmarks/folders?question_id=${encodeURIComponent(this.questionId)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) throw new Error('Không thể tải danh sách thư mục.');
                const payload = await response.json();
                this.folders = payload?.data?.folders || [];
                this.bookmarked = Boolean(payload?.data?.bookmarked);
            } catch (error) {
                this.foldersError = error?.message || 'Không thể tải danh sách thư mục.';
            } finally {
                this.foldersLoading = false;
            }
        },
        async toggleFolderItem(folder) {
            this.foldersError = '';
            try {
                const response = await fetch(`/bookmarks/folders/${folder.id}/toggle`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ question_id: String(this.questionId), in_folder: !folder.in_folder }),
                });
                if (!response.ok) {
                    const errData = await response.json().catch(() => null);
                    throw new Error(errData?.message || 'Không thể thực hiện thao tác.');
                }
                const payload = await response.json();
                this.folders = payload?.data?.folders || [];
                this.bookmarked = Boolean(payload?.data?.bookmarked);
            } catch (error) {
                this.foldersError = error?.message || 'Không thể thực hiện thao tác.';
            }
        },
        async createFolderAndAttach() {
            const name = this.folderSearchQuery.trim();
            if (!name) return;
            this.foldersError = '';
            try {
                const response = await fetch('/bookmarks/folders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ name: name, question_id: String(this.questionId) }),
                });
                if (!response.ok) {
                    const errData = await response.json().catch(() => null);
                    throw new Error(errData?.message || 'Không thể tạo thư mục.');
                }
                const payload = await response.json();
                this.folders = payload?.data?.folders || [];
                this.bookmarked = Boolean(payload?.data?.bookmarked);
                this.folderSearchQuery = '';
            } catch (error) {
                this.foldersError = error?.message || 'Không thể tạo thư mục.';
            }
        },
        get inFolders() {
            const q = this.folderSearchQuery.trim().toLowerCase();
            return this.folders.filter(f => f.in_folder && (!q || f.name.toLowerCase().includes(q)));
        },
        get notInFolders() {
            const q = this.folderSearchQuery.trim().toLowerCase();
            return this.folders.filter(f => !f.in_folder && (!q || f.name.toLowerCase().includes(q)));
        },
        get canCreateQueryFolder() {
            const q = this.folderSearchQuery.trim();
            if (!q) return false;
            return !this.folders.some(f => f.name.toLowerCase() === q.toLowerCase());
        },
        sessionIncomplete: @js($sessionIncomplete),
        exitUrl: @js($exitUrl),
        selectionBar: { show: false, x: 0, y: 0 },
        noteText: @js($note),
        noteHtml: @js($noteHtml ?? nl2br(e($note))),
        noteSaving: false,
        noteSaved: false,
        stemHtml: @js($stemHtml),
        annotateUrl: @js($playerConfig['annotate_url']),
        csrf: @js(csrf_token()),
        questionId: @js($question->id),
        feedbackUrl: @js(route('qbank.session.feedback', $session, absolute: false)),
        feedbackOpen: false,
        feedbackTarget: 'question',
        feedbackOptionId: null,
        feedbackCategory: '',
        feedbackMessage: '',
        feedbackSaving: false,
        feedbackError: '',
        feedbackSent: false,
        openFeedback(target, optionId = null) {
            this.feedbackTarget = target;
            this.feedbackOptionId = optionId;
            this.feedbackCategory = '';
            this.feedbackMessage = '';
            this.feedbackError = '';
            this.feedbackSent = false;
            this.feedbackOpen = true;
        },
        closeFeedback() {
            if (!this.feedbackSaving) this.feedbackOpen = false;
        },
        async submitFeedback() {
            if (!this.feedbackCategory || this.feedbackSaving) return;
            this.feedbackSaving = true;
            this.feedbackError = '';
            try {
                const response = await fetch(this.feedbackUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        question_id: this.questionId,
                        target: this.feedbackTarget,
                        option_id: this.feedbackOptionId,
                        category: this.feedbackCategory,
                        message: this.feedbackMessage,
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload?.message || payload?.error?.message || 'Không thể gửi phản hồi.');
                this.feedbackSent = true;
                setTimeout(() => this.feedbackOpen = false, 900);
            } catch (error) {
                this.feedbackError = error?.message || 'Không thể gửi phản hồi.';
            } finally {
                this.feedbackSaving = false;
            }
        },
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
        async setBookmark() {
            if (this.bookmarkSaving) return;
            const previous = this.bookmarked;
            this.bookmarked = !previous;
            this.bookmarkSaving = true;
            this.bookmarkError = '';

            try {
                const response = await fetch(this.bookmarkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ bookmarked: this.bookmarked }),
                });

                if (!response.ok) throw new Error('Không thể lưu câu hỏi.');
                const payload = await response.json();
                this.bookmarked = Boolean(payload?.data?.bookmarked);
            } catch (error) {
                this.bookmarked = previous;
                this.bookmarkError = error?.message || 'Không thể lưu câu hỏi.';
            } finally {
                this.bookmarkSaving = false;
            }
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

            const startMark = range.startContainer.nodeType === Node.TEXT_NODE
                ? range.startContainer.parentElement?.closest('mark')
                : range.startContainer.closest?.('mark');
            const endMark = range.endContainer.nodeType === Node.TEXT_NODE
                ? range.endContainer.parentElement?.closest('mark')
                : range.endContainer.closest?.('mark');

            if (startMark && startMark === endMark) {
                startMark.setAttribute('data-hl', hex);
                startMark.setAttribute('style', 'background-color: ' + hex + '4D');
                sel.removeAllRanges();
                this.selectionBar.show = false;
                this.stemHtml = stemEl.innerHTML;
                this.persistAnnotation({ stem_html: this.stemHtml });
                return;
            }

            const mark = document.createElement('mark');
            mark.className = 'rounded-sm';
            mark.setAttribute('data-hl', hex);
            mark.setAttribute('style', 'background-color: ' + hex + '4D');
            const unwrapMarks = (node) => {
                if (!node) return;
                node.querySelectorAll?.('mark').forEach((existing) => {
                    const parent = existing.parentNode;
                    while (existing.firstChild) parent.insertBefore(existing.firstChild, existing);
                    parent.removeChild(existing);
                });
            };
            const fragment = range.extractContents();
            unwrapMarks(fragment);
            mark.appendChild(fragment);
            range.insertNode(mark);
            sel.removeAllRanges();
            this.selectionBar.show = false;
            this.stemHtml = stemEl.innerHTML;
            this.persistAnnotation({ stem_html: this.stemHtml });
        },
        clearHighlight() {
            const root = document.getElementById('session-stem');
            if (!root) return;
            root.querySelectorAll('mark').forEach((mark) => {
                const parent = mark.parentNode;
                while (mark.firstChild) parent.insertBefore(mark.firstChild, mark);
                parent.removeChild(mark);
                parent.normalize();
            });
            this.selectionBar.show = false;
            this.stemHtml = root.innerHTML;
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
            const editor = this.$refs.noteEditor;
            this.noteHtml = editor ? editor.innerHTML : this.noteHtml;
            this.noteText = editor ? editor.innerText.trim() : this.noteText;
            await this.persistAnnotation({ note: this.noteText, note_html: this.noteHtml });
            this.noteSaving = false;
            this.noteSaved = true;
            this.notesOpen = false;
        },
        formatNote(command, value = null) {
            this.$refs.noteEditor?.focus();
            document.execCommand(command, false, value);
            this.noteHtml = this.$refs.noteEditor?.innerHTML || '';
            this.noteText = this.$refs.noteEditor?.innerText.trim() || '';
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
                        'id'          => (int) $option->id,
                        'label'       => $option->label,
                        'content'     => $option->content,
                        'correct'     => (bool) $option->is_correct,
                        'explanation' => \App\Support\Html\SafeHtml::forDisplay((string) ($option->explanation ?? '')),
                    ])->values();
                @endphp

                <div class="w-full overflow-y-auto"
                    x-data="{
                        options: @js($optionPayload),
                        selected: @js(isset($selectedOptionIds[0]) ? (int) $selectedOptionIds[0] : null),
                        revealed: @js($isAnswered),
                        expandedOptions: [],
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
                            if (this.revealed) {
                                if (this.expandedOptions.includes(id)) {
                                    this.expandedOptions = this.expandedOptions.filter((item) => item !== id);
                                } else {
                                    this.expandedOptions = [...this.expandedOptions, id];
                                }
                                return;
                            }

                            this.selected = id;
                            this.expandedOptions = [id];
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
                            if (!this.revealed || !this.expandedOptions.includes(option.id)) {
                                return 'border-outline-variant bg-white hover:border-primary/50 hover:bg-primary/5 cursor-pointer';
                            }
                            if (option.correct) return 'border-[#16A34A] bg-[#16A34A]/5';
                            return 'border-error bg-error/5';
                        },
                        badgeClass(option) {
                            if (!this.revealed || !this.expandedOptions.includes(option.id)) return 'border border-outline-variant text-on-surface-variant';
                            if (option.correct) return 'bg-[#16A34A] text-white';
                            return 'bg-error text-white';
                        },
                        showDetail(option) {
                            return this.revealed && this.expandedOptions.includes(option.id);
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
                                    {{ $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp' }} · {{ $question->difficulty->label() }}
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

                        <div class="space-y-5">
                            <article class="space-y-6" :class="{ 'key-info-active': keyInfoEnabled }">
                                <div x-show="keyInfoUsed" x-cloak
                                    class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-700 uppercase"
                                    data-testid="key-info-used-badge">
                                    <span class="material-symbols-outlined text-[15px] fill-1">check_circle</span>
                                    <span>Đã dùng gợi ý</span>
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

                            @if ($stemImageUrl)
                                <aside class="overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                                    <div class="group relative bg-white flex justify-center cursor-zoom-in"
                                        @click="imageViewerOpen = true; imageViewerSrc = '{{ $stemImageUrl }}'">
                                        <img src="{{ $stemImageUrl }}" alt="Ảnh minh họa câu hỏi"
                                            class="w-full h-auto max-h-[600px] object-contain">
                                    </div>
                                </aside>
                            @endif
                        </div>

                        <div class="flex min-h-12 items-center border-y border-outline-variant bg-surface-container-lowest px-1"
                            data-testid="question-knowledge-toolbar">
                            <button type="button" @click="toggleKeyInfo()" :disabled="!hasKeyInfo"
                                class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                :class="keyInfoEnabled
                                    ? 'border-amber-600 text-amber-700'
                                    : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                title="{{ $hasKeyInfo ? 'Gạch chân các ý chính' : 'Câu này chưa có gợi ý được đánh dấu' }}"
                                :aria-pressed="keyInfoEnabled">
                                <span class="material-symbols-outlined text-[18px]">format_align_left</span>
                                <span>Gợi ý</span>
                            </button>
                            @if ($hasAttendingTip)
                                <button type="button" @click="toggleAttendingTip()"
                                    class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors"
                                    :class="attendingTipOpen
                                        ? 'border-amber-600 text-amber-700'
                                        : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                    title="Mở kiến thức cho câu hỏi"
                                    :aria-pressed="attendingTipOpen"
                                    data-testid="attending-tip-toggle">
                                    <span class="material-symbols-outlined text-[18px]">help</span>
                                    <span>Kiến thức</span>
                                </button>
                            @endif
                            <button type="button" @click="openFolderModal()" :disabled="bookmarkSaving"
                                class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors disabled:cursor-wait disabled:opacity-60"
                                :class="bookmarked
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                                :title="bookmarked ? 'Bỏ lưu câu hỏi' : 'Lưu câu hỏi'"
                                :aria-label="bookmarked ? 'Bỏ lưu câu hỏi' : 'Lưu câu hỏi'"
                                :aria-pressed="bookmarked"
                                data-testid="question-bookmark-toggle">
                                <span class="material-symbols-outlined text-[18px]"
                                    :class="bookmarked && 'fill-1'">folder_managed</span>
                                <span class="hidden sm:inline" x-text="bookmarked ? 'Đã lưu' : 'Lưu'"></span>
                            </button>
                            <button type="button" @click="openFeedback('question')"
                                class="inline-flex h-12 items-center gap-2 border-b-2 border-transparent px-3 text-label-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                                aria-label="Phản hồi về nội dung câu hỏi">
                                <span class="material-symbols-outlined text-[18px]">feedback</span>
                                <span class="hidden sm:inline">Phản hồi câu hỏi</span>
                                <span class="sm:hidden">Phản hồi</span>
                            </button>
                            <span x-show="bookmarkError" x-cloak
                                class="px-2 text-xs font-medium text-error"
                                x-text="bookmarkError"></span>
                        </div>

                        <!-- Save question in folder modal -->
                        <div x-show="folderModalOpen" x-cloak
                            class="fixed inset-0 z-50 flex items-center justify-center p-4"
                            @keydown.escape.window="folderModalOpen = false">
                            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm transition-opacity"
                                @click="folderModalOpen = false"></div>

                            <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-white p-6 shadow-2xl transition-all"
                                @click.stop>
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-headline-sm font-bold text-on-surface">Save question in folder</h3>
                                    <button type="button" @click="folderModalOpen = false"
                                        class="flex size-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface"
                                        aria-label="Đóng">
                                        <span class="material-symbols-outlined text-[20px]">close</span>
                                    </button>
                                </div>

                                <div class="relative mb-4">
                                    <input type="text" x-model="folderSearchQuery"
                                        @keydown.enter.prevent="canCreateQueryFolder && createFolderAndAttach()"
                                        placeholder="Create or find folder"
                                        class="w-full rounded-xl border border-outline-variant bg-white px-4 py-2.5 pr-10 text-sm font-medium text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[20px] text-outline">search</span>
                                </div>

                                <div x-show="foldersLoading" class="py-6 text-center text-on-surface-variant">
                                    <span class="material-symbols-outlined animate-spin text-[24px]">progress_activity</span>
                                </div>

                                <div x-show="foldersError" x-text="foldersError" class="mb-3 text-xs font-semibold text-error"></div>

                                <div x-show="canCreateQueryFolder && !foldersLoading" class="mb-3">
                                    <button type="button" @click="createFolderAndAttach()"
                                        class="flex w-full items-center justify-between rounded-xl border border-dashed border-primary/40 bg-primary/5 px-3.5 py-2 text-sm font-semibold text-primary transition-colors hover:bg-primary/10">
                                        <span>+ Tạo thư mục "<span x-text="folderSearchQuery"></span>"</span>
                                        <span class="material-symbols-outlined text-[18px]">add</span>
                                    </button>
                                </div>

                                <div x-show="!foldersLoading" class="max-h-64 space-y-4 overflow-y-auto pr-1">
                                    <!-- IN FOLDERS SECTION -->
                                    <div x-show="inFolders.length > 0">
                                        <p class="mb-2 text-[11px] font-bold tracking-wider text-on-surface-variant/80 uppercase">IN FOLDERS</p>
                                        <div class="space-y-1">
                                            <template x-for="folder in inFolders" :key="'in-' + folder.id">
                                                <div class="flex items-center justify-between rounded-lg py-1.5 px-2 hover:bg-surface-container-lowest">
                                                    <span class="text-sm font-medium text-on-surface" x-text="folder.name"></span>
                                                    <button type="button" @click="toggleFolderItem(folder)"
                                                        class="flex size-7 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                                        title="Xóa khỏi thư mục">
                                                        <span class="material-symbols-outlined text-[18px]">close</span>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- NOT IN FOLDERS SECTION -->
                                    <div x-show="notInFolders.length > 0">
                                        <p class="mb-2 text-[11px] font-bold tracking-wider text-on-surface-variant/80 uppercase">NOT IN FOLDERS</p>
                                        <div class="space-y-1">
                                            <template x-for="folder in notInFolders" :key="'not-' + folder.id">
                                                <div class="flex items-center justify-between rounded-lg py-1.5 px-2 hover:bg-surface-container-lowest">
                                                    <span class="text-sm font-medium text-on-surface" x-text="folder.name"></span>
                                                    <button type="button" @click="toggleFolderItem(folder)"
                                                        class="flex size-7 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary"
                                                        title="Thêm vào thư mục">
                                                        <span class="material-symbols-outlined text-[18px]">add</span>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($hasAttendingTip)
                            <div x-show="attendingTipOpen" x-cloak x-transition class="space-y-3"
                                data-testid="attending-tip-panel">
                                <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-on-surface">
                                    <div x-show="attendingTipUsed"
                                        class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-700 uppercase"
                                        data-testid="attending-tip-used-badge">
                                        <span class="material-symbols-outlined text-[15px] fill-1">check_circle</span>
                                        <span>Đã dùng kiến thức</span>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <span class="material-symbols-outlined mt-0.5 shrink-0 text-amber-700">stethoscope</span>
                                        <div class="prose prose-sm max-w-none font-body-md text-body-md leading-relaxed italic" x-html="attendingTip"></div>
                                    </div>
                                    <div class="mt-3 flex justify-end">
                                        <button type="button" @click="openFeedback('knowledge')"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-label-sm font-semibold text-on-surface-variant transition-colors hover:bg-amber-100 hover:text-amber-800"
                                            aria-label="Phản hồi về phần kiến thức">
                                            <span class="material-symbols-outlined text-[18px]">feedback</span>
                                            Phản hồi kiến thức
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <section class="space-y-3">
                            <template x-for="option in options" :key="option.id">
                                <div class="flex w-full flex-col overflow-hidden rounded-xl border text-left transition-all"
                                    :class="wrapClass(option)"
                                    @click="choose(option.id)"
                                    role="button"
                                    tabindex="0">
                                    <div class="flex items-start gap-4 p-4">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full font-bold"
                                            :class="badgeClass(option)" x-text="option.label"></span>
                                        <div class="min-w-0 flex-1 space-y-1 pt-1">
                                            <span class="block font-body-md text-body-md text-on-surface"
                                                x-text="option.content"></span>
                                            <template x-if="revealed && expandedOptions.includes(option.id) && option.id === selected">
                                                <span class="inline-block rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                                    :class="option.correct ? 'bg-[#16A34A] text-white' : 'bg-error text-white'"
                                                    x-text="option.correct ? 'Lựa chọn của bạn · Đúng' : 'Lựa chọn của bạn'"></span>
                                            </template>
                                        </div>
                                        <template x-if="revealed && expandedOptions.includes(option.id) && option.correct">
                                            <span class="material-symbols-outlined text-[#16A34A]"
                                                style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                        </template>
                                        <template x-if="revealed && expandedOptions.includes(option.id) && !option.correct">
                                            <span class="material-symbols-outlined text-error"
                                                style="font-variation-settings: 'FILL' 1;">cancel</span>
                                        </template>
                                    </div>
                                    <div x-show="showDetail(option)" x-cloak
                                        class="space-y-2 border-t border-outline-variant/40 px-4 pb-4 pl-16">
                                        <p class="text-label-sm font-bold tracking-wide uppercase"
                                            :class="option.correct ? 'text-[#16A34A]' : 'text-error'"
                                            x-text="option.correct ? 'Đáp án đúng' : 'Vì sao sai'"></p>
                                        <div class="prose prose-sm max-w-none text-body-sm leading-relaxed text-on-surface-variant"
                                            x-html="detailText(option)"></div>
                                        <button type="button" @click.stop="openFeedback('answer', option.id)"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-label-sm font-semibold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                                            :aria-label="'Phản hồi về đáp án ' + option.label">
                                            <span class="material-symbols-outlined text-[18px]">feedback</span>
                                            Phản hồi đáp án
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </section>


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
                    <div class="overflow-hidden rounded-lg border border-outline-variant bg-white focus-within:border-primary focus-within:ring-1 focus-within:ring-primary">
                        <div class="flex flex-wrap items-center gap-1 border-b border-outline-variant bg-surface-container-lowest p-2">
                            <button type="button" @click="formatNote('bold')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="In đậm">
                                <span class="font-bold">B</span>
                            </button>
                            <button type="button" @click="formatNote('italic')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="In nghiêng">
                                <span class="italic">I</span>
                            </button>
                            <button type="button" @click="formatNote('underline')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Gạch chân">
                                <span class="underline">U</span>
                            </button>
                            <div class="mx-1 h-5 w-px bg-outline-variant"></div>
                            <button type="button" @click="formatNote('formatBlock', 'h3')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Tiêu đề">
                                <span class="material-symbols-outlined text-[18px]">title</span>
                            </button>
                            <button type="button" @click="formatNote('insertUnorderedList')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Danh sách">
                                <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                            </button>
                            <button type="button" @click="formatNote('insertOrderedList')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Danh sách số">
                                <span class="material-symbols-outlined text-[18px]">format_list_numbered</span>
                            </button>
                            <button type="button" @click="formatNote('formatBlock', 'blockquote')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Trích dẫn">
                                <span class="material-symbols-outlined text-[18px]">format_quote</span>
                            </button>
                            <button type="button" @click="formatNote('backColor', '#fef08a')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Highlight">
                                <span class="material-symbols-outlined text-[18px]">ink_highlighter</span>
                            </button>
                        </div>
                        <div x-ref="noteEditor" contenteditable="true" x-html="noteHtml"
                            @input="noteHtml = $refs.noteEditor.innerHTML; noteText = $refs.noteEditor.innerText.trim()"
                            class="prose prose-sm min-h-[190px] max-w-none overflow-y-auto p-4 text-body-md outline-none"
                            data-placeholder="Nhập nội dung ghi chú của bạn tại đây..."></div>
                    </div>
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
            <button type="button" @mousedown.prevent @click="clearHighlight()"
                class="flex size-5 items-center justify-center rounded-full border border-outline-variant bg-white text-[12px] font-bold leading-none text-outline transition-colors hover:border-error hover:text-error"
                title="Xóa tô màu"
                aria-label="Xóa tô màu">x</button>
        </div>

        <div x-show="imageViewerOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[150] flex items-center justify-center bg-black/90 p-4"
            @click="imageViewerOpen = false">
            <button type="button"
                class="absolute right-4 top-4 flex size-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20">
                <span class="material-symbols-outlined text-[24px]">close</span>
            </button>
            <img :src="imageViewerSrc" alt="Ảnh phóng to"
                class="max-h-full max-w-full cursor-zoom-out object-contain"
                @click.stop="imageViewerOpen = false">
        </div>

        <!-- Exit confirm (chưa làm xong) -->
        <div x-show="feedbackOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="question-feedback-title"
            @keydown.escape.window="closeFeedback()">
            <div class="absolute inset-0 bg-black/45" @click="closeFeedback()"></div>
            <section class="relative w-full max-w-xl rounded-2xl bg-white p-5 shadow-2xl md:p-6" @click.stop>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="question-feedback-title" class="font-headline-sm text-headline-sm font-bold text-on-surface">
                            Phản hồi của bạn có nội dung như thế nào?
                        </h2>
                        <p class="mt-1 text-body-sm text-on-surface-variant"
                            x-text="feedbackTarget === 'answer' ? 'Phản hồi về đáp án' : (feedbackTarget === 'knowledge' ? 'Phản hồi về kiến thức' : 'Phản hồi về câu hỏi')"></p>
                    </div>
                    <button type="button" @click="closeFeedback()"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high"
                        aria-label="Đóng cửa sổ phản hồi">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="question-feedback-category" class="mb-1.5 block text-label-md font-bold text-on-surface">
                            Loại phản hồi <span class="text-error">*</span>
                        </label>
                        <select id="question-feedback-category" x-model="feedbackCategory"
                            class="w-full rounded-lg border border-outline bg-white px-3 py-3 text-body-md text-on-surface focus:border-primary focus:ring-primary">
                            <option value="" disabled>Lựa chọn</option>
                            <option value="grammar">Ngữ pháp và chính tả</option>
                            <option value="incorrect">Nội dung không chính xác</option>
                            <option value="missing">Nội dung bị thiếu</option>
                            <option value="improvement">Cải thiện nội dung</option>
                            <option value="technical">Sự cố kỹ thuật</option>
                            <option value="media">Hình ảnh hoặc tệp đính kèm</option>
                            <option value="search">Kết quả tìm kiếm</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div>
                        <label for="question-feedback-message" class="mb-1.5 block text-label-md font-bold text-on-surface">
                            Mô tả chi tiết <span class="font-normal text-on-surface-variant">(không bắt buộc)</span>
                        </label>
                        <textarea id="question-feedback-message" x-model="feedbackMessage" maxlength="2000" rows="6"
                            class="w-full resize-y rounded-lg border border-outline-variant p-3 text-body-md focus:border-primary focus:ring-primary"
                            placeholder="Hãy viết thứ cho chúng tôi cần kiểm tra..."></textarea>
                        <p class="mt-1 text-right text-xs text-on-surface-variant" x-text="feedbackMessage.length + '/2000'"></p>
                    </div>
                    <p x-show="feedbackError" x-text="feedbackError" class="text-body-sm font-semibold text-error" role="alert"></p>
                    <p x-show="feedbackSent" class="rounded-lg bg-success/10 p-3 text-body-sm font-semibold text-success" role="status">
                        Cảm ơn bạn. Phản hồi đã được ghi nhận.
                    </p>
                    <button type="button" @click="submitFeedback()"
                        :disabled="!feedbackCategory || feedbackSaving || feedbackSent"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-bold text-white transition-opacity disabled:cursor-not-allowed disabled:opacity-35">
                        <span x-show="feedbackSaving" class="material-symbols-outlined animate-spin text-[19px]">progress_activity</span>
                        <span x-text="feedbackSaving ? 'Đang gửi...' : 'Gửi phản hồi'"></span>
                    </button>
                </div>
            </section>
        </div>

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

{{-- ── Question Hint: click-to-reveal for students ── --}}
<style>
    /* Student: hint hidden by default (looks like normal text) */
    #session-stem mark[data-hint],
    [data-testid="key-info-stem"] mark[data-hint] {
        cursor: pointer;
        transition: text-decoration 0.2s ease;
        background-color: transparent;
        color: inherit;
        text-decoration: none;
    }
    /* Student: revealed individually on click */
    #session-stem mark[data-hint].revealed,
    [data-testid="key-info-stem"] mark[data-hint].revealed {
        text-decoration: underline #ea580c;
        text-decoration-style: solid;
        text-decoration-thickness: 2px;
        text-underline-offset: 4px;
        background-color: transparent;
    }
    /* Student: ALL hints revealed when clicking the "Gợi ý" toolbar button */
    .key-info-active #session-stem mark[data-hint],
    .key-info-active [data-testid="key-info-stem"] mark[data-hint] {
        text-decoration: underline #ea580c;
        text-decoration-style: solid;
        text-decoration-thickness: 2px;
        text-underline-offset: 4px;
        background-color: transparent;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            const hint = e.target.closest('mark[data-hint]');
            if (!hint) return;

            // Only toggle within the question stem areas
            const stemContainer = hint.closest('#session-stem, [data-testid="key-info-stem"]');
            if (!stemContainer) return;

            hint.classList.toggle('revealed');
        });
    });
</script>
