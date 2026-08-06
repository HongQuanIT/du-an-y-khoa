@php
    /**
     * @var \Modules\QuestionBank\Models\QuestionSession $session
     * @var \Modules\QuestionBank\Models\Question $question
     * @var \Modules\QuestionBank\Models\QuestionAttempt|null $attempt
     * @var int $index
     * @var int $total
     * @var array<int, string> $questionIds
     * @var array<int, string> $answeredIds
     * @var array<int, string> $flaggedIds
     * @var string $note
     * @var string $stemHtml
     * @var bool $flagged
     * @var int|null $remainingSeconds
     */
    $selectedOptionIds = array_map('intval', $attempt?->selected_option_ids ?? []);
    $selectedOptionId = $selectedOptionIds[0] ?? null;
    $previousIndex = $index > 0 ? $index - 1 : null;
    $nextIndex = $index + 1 < $total ? $index + 1 : null;
    $answeredLookup = array_fill_keys(array_map('strval', $answeredIds), true);
    $flaggedLookup = array_fill_keys(array_map('strval', $flaggedIds), true);
    $answeredCount = count($answeredIds);
    $flaggedCount = count($flaggedIds);
@endphp

<x-layouts.auth title="Chế độ thi">
    <div class="h-screen overflow-hidden bg-[#f7faf8] text-on-background" x-data="{
        selected: @js($selectedOptionId),
        saved: @js($attempt !== null),
        answering: false,
        answerError: '',
        exitOpen: false,
        finishOpen: false,
        notesOpen: false,
        calculatorOpen: false,
        mobileNav: false,
        toastDismissed: false,
        flagged: @js((bool) $flagged),
        noteText: @js($note),
        annotationSaving: false,
        annotationError: '',
        remaining: @js($remainingSeconds),
        elapsed: @js((int) ($attempt?->time_spent_seconds ?? 0)),
        answeredCount: @js($answeredCount),
        answerUrl: @js(route('qbank.session.answer', $session, absolute: false)),
        annotateUrl: @js(route('qbank.session.annotate', $session, absolute: false)),
        questionId: @js((string) $question->getKey()),
        index: @js($index),
        csrf: @js(csrf_token()),
        calculatorDisplay: '0',
        calculatorStored: null,
        calculatorOperator: null,
        calculatorWaiting: false,
        calculatorError: false,
        _clock: null,
        init() {
            this._clock = setInterval(() => {
                this.elapsed++;
                if (this.remaining === null) return;
                this.remaining = Math.max(0, this.remaining - 1);
                if (this.remaining === 0) {
                    clearInterval(this._clock);
                    this.saveCurrent().finally(() => this.$refs.finishForm?.submit());
                }
            }, 1000);
        },
        formatTime(seconds) {
            const value = Math.max(0, Number(seconds || 0));
            const hours = Math.floor(value / 3600);
            const minutes = Math.floor((value % 3600) / 60);
            const secs = value % 60;
            return hours > 0
                ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
                : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },
        async saveCurrent(redirectUrl = null) {
            if (this.selected === null) {
                if (redirectUrl) window.location.assign(redirectUrl);
                return true;
            }
            this.answering = true;
            this.answerError = '';
            try {
                const response = await fetch(this.answerUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        question_id: this.questionId,
                        option_ids: [Number(this.selected)],
                        time_spent_seconds: this.elapsed,
                        index: this.index,
                    }),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload?.message || 'Không thể lưu câu trả lời.');
                this.saved = true;
                this.answeredCount = Number(payload?.data?.answered_count ?? this.answeredCount);
                if (redirectUrl) window.location.assign(redirectUrl);
                return true;
            } catch (error) {
                this.answerError = error?.message || 'Không thể lưu câu trả lời.';
                return false;
            } finally {
                this.answering = false;
            }
        },
        async navigate(url) {
            await this.saveCurrent(url);
        },
        async requestFinish() {
            if (await this.saveCurrent()) this.finishOpen = true;
        },
        async requestExit() {
            if (await this.saveCurrent()) this.exitOpen = true;
        },
        async persistAnnotation(payload) {
            this.annotationSaving = true;
            this.annotationError = '';
            try {
                const response = await fetch(this.annotateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ question_id: this.questionId, ...payload }),
                });
                if (!response.ok) throw new Error('Không thể lưu thay đổi.');
                return true;
            } catch (error) {
                this.annotationError = error?.message || 'Không thể lưu thay đổi.';
                return false;
            } finally {
                this.annotationSaving = false;
            }
        },
        async toggleFlag() {
            const previous = this.flagged;
            this.flagged = !this.flagged;
            if (!await this.persistAnnotation({ flagged: this.flagged })) this.flagged = previous;
        },
        async saveNote() {
            if (await this.persistAnnotation({ note: this.noteText })) this.notesOpen = false;
        },
        calculatorDigit(digit) {
            if (this.calculatorError || this.calculatorWaiting) {
                this.calculatorDisplay = digit;
                this.calculatorWaiting = false;
                this.calculatorError = false;
                return;
            }
            if (this.calculatorDisplay.replace(/[-.]/g, '').length >= 12) return;
            this.calculatorDisplay = this.calculatorDisplay === '0'
                ? digit
                : this.calculatorDisplay + digit;
        },
        calculatorDecimal() {
            if (this.calculatorError || this.calculatorWaiting) {
                this.calculatorDisplay = '0.';
                this.calculatorWaiting = false;
                this.calculatorError = false;
                return;
            }
            if (!this.calculatorDisplay.includes('.')) this.calculatorDisplay += '.';
        },
        calculatorClear() {
            this.calculatorDisplay = '0';
            this.calculatorStored = null;
            this.calculatorOperator = null;
            this.calculatorWaiting = false;
            this.calculatorError = false;
        },
        calculatorBackspace() {
            if (this.calculatorError) return this.calculatorClear();
            if (this.calculatorWaiting) return;
            this.calculatorDisplay = this.calculatorDisplay.length > 1
                ? this.calculatorDisplay.slice(0, -1)
                : '0';
            if (this.calculatorDisplay === '-') this.calculatorDisplay = '0';
        },
        calculatorToggleSign() {
            if (this.calculatorError || Number(this.calculatorDisplay) === 0) return;
            this.calculatorDisplay = this.calculatorDisplay.startsWith('-')
                ? this.calculatorDisplay.slice(1)
                : '-' + this.calculatorDisplay;
        },
        calculatorPercent() {
            if (this.calculatorError) return;
            this.calculatorDisplay = this.calculatorFormat(Number(this.calculatorDisplay) / 100);
        },
        calculatorChoose(operator) {
            if (this.calculatorError) return;
            const current = Number(this.calculatorDisplay);
            if (this.calculatorStored !== null && this.calculatorOperator && !this.calculatorWaiting) {
                const result = this.calculatorResolve(this.calculatorStored, current, this.calculatorOperator);
                if (result === null) return;
                this.calculatorDisplay = result;
                this.calculatorStored = Number(result);
            } else {
                this.calculatorStored = current;
            }
            this.calculatorOperator = operator;
            this.calculatorWaiting = true;
        },
        calculatorEquals() {
            if (this.calculatorError || this.calculatorStored === null || !this.calculatorOperator) return;
            const result = this.calculatorResolve(
                this.calculatorStored,
                Number(this.calculatorDisplay),
                this.calculatorOperator,
            );
            if (result === null) return;
            this.calculatorDisplay = result;
            this.calculatorStored = null;
            this.calculatorOperator = null;
            this.calculatorWaiting = true;
        },
        calculatorResolve(left, right, operator) {
            let result;
            if (operator === '+') result = left + right;
            if (operator === '−') result = left - right;
            if (operator === '×') result = left * right;
            if (operator === '÷') result = right === 0 ? Number.NaN : left / right;
            if (!Number.isFinite(result)) {
                this.calculatorDisplay = 'Lỗi';
                this.calculatorStored = null;
                this.calculatorOperator = null;
                this.calculatorWaiting = true;
                this.calculatorError = true;
                return null;
            }
            return this.calculatorFormat(result);
        },
        calculatorFormat(value) {
            const rounded = Number(Number(value).toPrecision(12));
            const text = String(rounded);
            return text.length > 14 ? rounded.toExponential(8) : text;
        },
        handleCalculatorKey(event) {
            if (!this.calculatorOpen) return;
            const key = event.key;
            if (/^[0-9]$/.test(key)) this.calculatorDigit(key);
            else if (key === '.' || key === ',') this.calculatorDecimal();
            else if (key === '+') this.calculatorChoose('+');
            else if (key === '-') this.calculatorChoose('−');
            else if (key === '*') this.calculatorChoose('×');
            else if (key === '/') this.calculatorChoose('÷');
            else if (key === '%' ) this.calculatorPercent();
            else if (key === 'Enter' || key === '=') this.calculatorEquals();
            else if (key === 'Backspace') this.calculatorBackspace();
            else if (key === 'Delete') this.calculatorClear();
            else return;
            event.preventDefault();
        },
    }" @keydown.window="handleCalculatorKey($event)"
        @keydown.escape.window="exitOpen = false; finishOpen = false; notesOpen = false; calculatorOpen = false; mobileNav = false">
        <header
            class="fixed inset-x-0 top-0 z-50 flex h-16 items-center justify-between border-b border-outline-variant bg-surface px-4 md:px-8">
            <div class="flex items-center gap-3 md:gap-6">
                <h1 class="hidden font-headline-sm text-headline-sm font-bold text-primary sm:block">{{ config('app.name') }}</h1>
                <div class="hidden items-center gap-2 rounded-full bg-secondary-fixed/30 px-3 py-1 md:flex"
                    :class="remaining !== null && remaining <= 300 ? 'text-error' : 'text-primary'">
                    <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">timer</span>
                    <span class="font-headline-sm tabular-nums" x-text="formatTime(remaining)"></span>
                </div>
                <span class="font-label-md whitespace-nowrap text-on-surface-variant">Câu {{ $index + 1 }}/{{ $total }}</span>
            </div>
            <div class="flex items-center gap-1 sm:gap-3">
                <button type="button" @click="toggleFlag()" title="Đánh dấu câu hỏi"
                    class="group rounded-full p-2 transition-colors hover:bg-surface-variant"
                    :class="flagged ? 'text-tertiary' : 'text-on-surface-variant'">
                    <span class="material-symbols-outlined"
                        :style="flagged ? &quot;font-variation-settings: 'FILL' 1&quot; : null">flag</span>
                </button>
                <button type="button" @click="calculatorOpen = true" title="Máy tính" aria-label="Mở máy tính"
                    data-testid="exam-calculator-trigger"
                    class="rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-variant hover:text-primary">
                    <span class="material-symbols-outlined">calculate</span>
                </button>
                <button type="button" @click="notesOpen = true" title="Ghi chú"
                    class="rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-variant">
                    <span class="material-symbols-outlined">more_vert</span>
                </button>
                <button type="button" @click="requestExit()"
                    class="ml-1 rounded-lg bg-error px-3 py-2 font-label-md text-white transition-colors hover:bg-red-700 sm:ml-2 sm:px-5">
                    Thoát
                </button>
            </div>
        </header>

        <main class="flex h-screen flex-col overflow-hidden pt-16 md:flex-row">
            <div class="relative flex h-full min-w-0 flex-1 flex-col bg-white">
                <div class="custom-scrollbar flex-1 overflow-y-auto p-6 pb-32 md:p-10">
                    <div class="mx-auto flex max-w-5xl flex-col gap-12">
                        @if ($errors->any())
                            <div class="rounded-xl border border-error/30 bg-error-container/40 p-4 text-sm text-on-error-container">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <section class="space-y-6">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded bg-primary px-3 py-1 text-label-sm tracking-wider text-white uppercase">Lâm sàng</span>
                                <span class="font-label-sm text-on-surface-variant">
                                    {{ $question->topic?->name ?? 'Tổng hợp' }} / {{ $question->difficulty->label() }}
                                </span>
                            </div>
                            <article class="max-w-none">
                                <h2 class="mb-4 font-headline-md text-headline-md text-on-surface">Trường hợp lâm sàng</h2>
                                <div class="text-body-md leading-relaxed whitespace-pre-line text-on-surface">{!! $stemHtml !!}</div>
                            </article>
                        </section>

                        <section class="space-y-4">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 font-label-md text-on-surface-variant">
                                    <span class="material-symbols-outlined text-sm">edit_note</span>
                                    Chọn đáp án đúng nhất
                                </h3>
                                <span x-show="saved" x-cloak class="inline-flex items-center gap-1 text-xs font-semibold text-primary">
                                    <span class="material-symbols-outlined text-[16px]">cloud_done</span> Đã lưu
                                </span>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($question->options as $option)
                                    <button type="button" @click="selected = @js((int) $option->id); saved = false"
                                        class="option-button group flex items-center gap-4 rounded-xl border p-5 text-left transition-all"
                                        :class="selected === @js((int) $option->id)
                                            ? 'selected border-primary'
                                            : 'border-outline-variant'">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg font-bold transition-colors"
                                            :class="selected === @js((int) $option->id)
                                                ? 'bg-primary text-white'
                                                : 'bg-surface-container-high group-hover:bg-primary-fixed'">
                                            {{ $option->label }}
                                        </span>
                                        <span class="text-body-md font-medium"
                                            :class="selected === @js((int) $option->id) ? 'text-primary' : 'text-on-surface'">
                                            {{ $option->content }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <p x-show="answerError" x-cloak class="text-sm font-medium text-error" x-text="answerError"></p>
                        </section>
                    </div>
                </div>

                <footer
                    class="absolute inset-x-0 bottom-0 z-40 flex items-center justify-between border-t border-outline-variant bg-white px-4 py-4 sm:px-6">
                    @if ($previousIndex !== null)
                        <a href="{{ route('qbank.session', [$session, 'index' => $previousIndex]) }}"
                            @click.prevent="navigate(@js(route('qbank.session', [$session, 'index' => $previousIndex], absolute: false)))"
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-on-surface-variant transition-all hover:bg-surface-variant active:scale-95">
                            <span class="material-symbols-outlined">arrow_back</span>
                            <span class="hidden font-label-md sm:inline">Câu trước</span>
                        </a>
                    @else
                        <span></span>
                    @endif
                    <div class="flex gap-2">
                        <button type="button" @click="mobileNav = !mobileNav"
                            class="flex size-10 items-center justify-center rounded-full bg-surface-container-high md:hidden">
                            <span class="material-symbols-outlined">grid_view</span>
                        </button>
                        @if ($nextIndex !== null)
                            <button type="button"
                                @click="navigate(@js(route('qbank.session', [$session, 'index' => $nextIndex], absolute: false)))"
                                :disabled="answering"
                                class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-white shadow-sm transition-all hover:bg-primary-container active:scale-95 disabled:opacity-50 sm:px-6">
                                <span class="font-label-md">Câu tiếp theo</span>
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </button>
                        @endif
                    </div>
                </footer>
            </div>

            <aside class="z-40 h-full w-full flex-col border-l border-outline-variant bg-surface-container-lowest md:flex md:w-80"
                :class="mobileNav ? 'flex' : 'hidden md:flex'">
                <div class="border-b border-outline-variant p-6">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Tiến độ bài thi</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-4">
                        <span class="flex items-center gap-1 text-[10px] font-medium text-on-surface-variant">
                            <span class="size-3 rounded-sm bg-primary"></span>Đã làm ({{ $answeredCount }})
                        </span>
                        <span class="flex items-center gap-1 text-[10px] font-medium text-on-surface-variant">
                            <span class="size-3 rounded-sm border border-outline"></span>Chưa làm ({{ max(0, $total - $answeredCount) }})
                        </span>
                        <span class="flex items-center gap-1 text-[10px] font-medium text-on-surface-variant">
                            <span class="size-3 rounded-sm bg-tertiary-container"></span>Flag ({{ $flaggedCount }})
                        </span>
                    </div>
                </div>

                <div class="custom-scrollbar flex-1 overflow-y-auto p-4">
                    <div class="grid grid-cols-5 gap-3 md:grid-cols-4 lg:grid-cols-5">
                        @foreach ($questionIds as $position => $questionId)
                            @php
                                $wasAnswered = isset($answeredLookup[(string) $questionId]);
                                $wasFlagged = isset($flaggedLookup[(string) $questionId]);
                                $questionUrl = route('qbank.session', [$session, 'index' => $position]);
                            @endphp
                            <a href="{{ $questionUrl }}" @click.prevent="navigate(@js(route('qbank.session', [$session, 'index' => $position], absolute: false)))"
                                @class([
                                    'relative flex aspect-square items-center justify-center overflow-hidden rounded-lg border text-sm transition-colors',
                                    'border-primary bg-primary font-bold text-white hover:opacity-90' => $wasAnswered && $position !== $index,
                                    'border-2 border-primary bg-primary/5 font-extrabold text-primary shadow-inner' => $position === $index,
                                    'border-outline-variant text-on-surface-variant hover:bg-surface' => !$wasAnswered && $position !== $index,
                                ])>
                                @if ($wasFlagged)
                                    <span class="flag-corner absolute top-0 right-0 size-4 bg-tertiary-container"></span>
                                @endif
                                {{ $position + 1 }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-outline-variant bg-surface-container-low p-6">
                    <button type="button" @click="requestFinish()"
                        class="w-full rounded-xl bg-secondary py-3 font-headline-sm text-white shadow-md transition-all hover:bg-blue-700 active:scale-[0.98]">
                        Nộp Bài Ngay
                    </button>
                </div>
            </aside>
        </main>

        <div x-show="calculatorOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="calculatorOpen = false"></div>
            <section role="dialog" aria-modal="true" aria-labelledby="calculator-title"
                class="relative w-full max-w-sm overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-2xl"
                data-testid="exam-calculator">
                <div class="flex items-center justify-between border-b border-outline-variant px-5 py-4">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">calculate</span>
                        <h3 id="calculator-title" class="font-headline-sm text-headline-sm">Máy tính</h3>
                    </div>
                    <button type="button" @click="calculatorOpen = false" aria-label="Đóng máy tính"
                        class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-low">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="bg-surface-container-low p-4">
                    <div class="rounded-xl border border-outline-variant bg-white px-4 py-3 text-right shadow-inner">
                        <div class="h-5 text-xs font-medium text-on-surface-variant">
                            <span x-show="calculatorStored !== null && calculatorOperator"
                                x-text="calculatorFormat(calculatorStored) + ' ' + calculatorOperator"></span>
                        </div>
                        <output class="block min-h-10 overflow-hidden text-3xl font-bold tracking-tight text-on-surface tabular-nums"
                            aria-live="polite" x-text="calculatorDisplay"></output>
                    </div>

                    <div class="mt-4 grid grid-cols-4 gap-2">
                        <button type="button" @click="calculatorClear()"
                            class="rounded-xl bg-error-container py-3 font-bold text-on-error-container hover:brightness-95">AC</button>
                        <button type="button" @click="calculatorToggleSign()"
                            class="rounded-xl bg-surface-container-high py-3 font-bold hover:brightness-95">±</button>
                        <button type="button" @click="calculatorPercent()"
                            class="rounded-xl bg-surface-container-high py-3 font-bold hover:brightness-95">%</button>
                        <button type="button" @click="calculatorChoose('÷')"
                            class="rounded-xl bg-primary-fixed py-3 text-xl font-bold text-on-primary-fixed-variant hover:brightness-95">÷</button>

                        @foreach ([['7', '7'], ['8', '8'], ['9', '9']] as [$label, $value])
                            <button type="button" @click="calculatorDigit('{{ $value }}')"
                                class="rounded-xl border border-outline-variant bg-white py-3 text-lg font-bold hover:bg-surface-container-low">{{ $label }}</button>
                        @endforeach
                        <button type="button" @click="calculatorChoose('×')"
                            class="rounded-xl bg-primary-fixed py-3 text-xl font-bold text-on-primary-fixed-variant hover:brightness-95">×</button>

                        @foreach ([['4', '4'], ['5', '5'], ['6', '6']] as [$label, $value])
                            <button type="button" @click="calculatorDigit('{{ $value }}')"
                                class="rounded-xl border border-outline-variant bg-white py-3 text-lg font-bold hover:bg-surface-container-low">{{ $label }}</button>
                        @endforeach
                        <button type="button" @click="calculatorChoose('−')"
                            class="rounded-xl bg-primary-fixed py-3 text-xl font-bold text-on-primary-fixed-variant hover:brightness-95">−</button>

                        @foreach ([['1', '1'], ['2', '2'], ['3', '3']] as [$label, $value])
                            <button type="button" @click="calculatorDigit('{{ $value }}')"
                                class="rounded-xl border border-outline-variant bg-white py-3 text-lg font-bold hover:bg-surface-container-low">{{ $label }}</button>
                        @endforeach
                        <button type="button" @click="calculatorChoose('+')"
                            class="rounded-xl bg-primary-fixed py-3 text-xl font-bold text-on-primary-fixed-variant hover:brightness-95">+</button>

                        <button type="button" @click="calculatorBackspace()" aria-label="Xoá một chữ số"
                            class="rounded-xl border border-outline-variant bg-white py-3 hover:bg-surface-container-low">
                            <span class="material-symbols-outlined align-middle">backspace</span>
                        </button>
                        <button type="button" @click="calculatorDigit('0')"
                            class="rounded-xl border border-outline-variant bg-white py-3 text-lg font-bold hover:bg-surface-container-low">0</button>
                        <button type="button" @click="calculatorDecimal()"
                            class="rounded-xl border border-outline-variant bg-white py-3 text-lg font-bold hover:bg-surface-container-low">.</button>
                        <button type="button" @click="calculatorEquals()"
                            class="rounded-xl bg-primary py-3 text-xl font-bold text-white shadow-sm hover:bg-primary-container">=</button>
                    </div>
                    <p class="mt-3 text-center text-[11px] text-on-surface-variant">
                        Có thể sử dụng bàn phím số, Enter và Backspace.
                    </p>
                </div>
            </section>
        </div>

        <div x-show="remaining !== null && remaining > 0 && remaining <= 300 && !toastDismissed" x-cloak
            class="animate-toast fixed bottom-20 left-4 z-[60] md:right-80 md:left-auto md:mr-8">
            <div class="flex items-center gap-3 rounded-xl border border-tertiary/20 bg-tertiary-container px-5 py-3 text-on-tertiary-container shadow-lg">
                <span class="material-symbols-outlined text-tertiary">warning</span>
                <div>
                    <p class="font-label-md font-bold">Còn 5 phút!</p>
                    <p class="text-xs opacity-90">Vui lòng kiểm tra lại các câu chưa trả lời.</p>
                </div>
                <button type="button" class="ml-2 hover:opacity-70" @click="toastDismissed = true">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </div>

        <div x-show="finishOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="finishOpen = false"></div>
            <section class="animate-toast relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-outline-variant p-6 text-center">
                    <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-error-container text-error">
                        <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">assignment_turned_in</span>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Xác nhận nộp bài</h3>
                    <p class="mt-2 px-4 text-body-md text-on-surface-variant">
                        Bạn đã trả lời <strong class="text-primary" x-text="answeredCount + '/{{ $total }}'"></strong> câu,
                        còn <strong class="text-error" x-text="Math.max(0, {{ $total }} - answeredCount)"></strong> câu bỏ trống.
                        Bạn có chắc chắn muốn kết thúc bài thi?
                    </p>
                </div>
                <div class="flex flex-col gap-2 bg-surface-container-low p-4">
                    <form x-ref="finishForm" method="POST" action="{{ route('qbank.session.finish', $session) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-error py-3 font-bold text-white hover:bg-red-700">
                            Nộp bài
                        </button>
                    </form>
                    <button type="button" @click="finishOpen = false"
                        class="w-full rounded-xl border border-outline-variant bg-white py-3 font-bold text-on-surface-variant hover:bg-surface">
                        Tiếp tục làm
                    </button>
                </div>
            </section>
        </div>

        <div x-show="exitOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="exitOpen = false"></div>
            <section class="animate-toast relative w-full max-w-md rounded-2xl border border-outline-variant bg-white p-8 text-center shadow-2xl">
                <div class="mx-auto mb-5 flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-4xl">pause</span>
                </div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Bạn muốn thoát?</h3>
                <p class="mt-3 text-body-md leading-relaxed text-on-surface-variant">
                    Tiến trình đã được lưu và bạn có thể tiếp tục phiên thi sau.
                </p>
                <div class="mt-7 flex flex-col gap-3">
                    <form method="POST" action="{{ route('qbank.session.pause', $session) }}">
                        @csrf
                        <input type="hidden" name="current_index" value="{{ $index }}">
                        <button type="submit" class="w-full rounded-xl bg-primary py-3.5 font-bold text-white shadow-lg">
                            Lưu &amp; thoát
                        </button>
                    </form>
                    <button type="button" @click="exitOpen = false"
                        class="w-full rounded-xl border border-outline py-3.5 font-bold text-primary hover:bg-surface-container-high">
                        Tiếp tục làm bài
                    </button>
                </div>
            </section>
        </div>

        <div x-show="notesOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="notesOpen = false"></div>
            <section class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-outline-variant px-5 py-4">
                    <h3 class="font-headline-sm text-headline-sm">Ghi chú câu hỏi</h3>
                    <button type="button" @click="notesOpen = false" class="flex size-9 items-center justify-center rounded-full hover:bg-surface-container-low">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-5">
                    <textarea x-model="noteText" maxlength="5000" rows="7"
                        class="w-full rounded-xl border border-outline-variant p-4 focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="Nhập ghi chú của bạn..."></textarea>
                    <div class="mt-2 flex text-xs">
                        <span x-show="annotationError" class="text-error" x-text="annotationError"></span>
                        <span class="ml-auto text-on-surface-variant" x-text="noteText.length + '/5000'"></span>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-outline-variant bg-surface-container-lowest px-5 py-4">
                    <button type="button" @click="notesOpen = false" class="rounded-lg px-4 py-2 font-bold text-on-surface-variant">Hủy</button>
                    <button type="button" @click="saveNote()" :disabled="annotationSaving"
                        class="rounded-lg bg-primary px-5 py-2 font-bold text-white disabled:opacity-50">
                        Lưu ghi chú
                    </button>
                </div>
            </section>
        </div>
    </div>
</x-layouts.auth>
