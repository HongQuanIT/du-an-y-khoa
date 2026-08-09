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
    $isExam = $session->mode->value === 'exam';
    $isAnswered = $attempt !== null;
    $selectedOptionIds = array_map('intval', $attempt?->selected_option_ids ?? []);
    $selectedOptionId = $selectedOptionIds[0] ?? null;
    $progress = $total > 0 ? (int) round(($index + 1) / $total * 100) : 0;
    $previousIndex = $index > 0 ? $index - 1 : null;
    $nextIndex = $index + 1 < $total ? $index + 1 : null;
    $answeredLookup = array_fill_keys(array_map('strval', $answeredIds), true);
    $flaggedLookup = array_fill_keys(array_map('strval', $flaggedIds), true);
    $difficultyLabel = $question->difficulty->label();
@endphp

<x-layouts.auth :title="$isExam ? 'Phiên thi' : 'Phiên học tập'">
    <div class="min-h-screen bg-white" x-data="{
        navigatorOpen: false,
        notesOpen: false,
        exitOpen: false,
        finishOpen: false,
        selected: @js($selectedOptionId),
        answering: false,
        flagged: @js((bool) $flagged),
        noteText: @js($note),
        annotationSaving: false,
        annotationSaved: false,
        annotationError: '',
        annotateUrl: @js(route('qbank.session.annotate', $session, absolute: false)),
        csrf: @js(csrf_token()),
        questionId: @js((string) $question->id),
        isExam: @js($isExam),
        remaining: @js($remainingSeconds),
        elapsed: @js((int) ($attempt?->time_spent_seconds ?? 0)),
        _clock: null,
        init() {
            this._clock = setInterval(() => {
                this.elapsed++;
                if (!this.isExam || this.remaining === null) return;
                this.remaining = Math.max(0, this.remaining - 1);
                if (this.remaining === 0) {
                    clearInterval(this._clock);
                    this.$nextTick(() => this.$refs.finishForm?.submit());
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
        async persistAnnotation(payload) {
            this.annotationSaving = true;
            this.annotationSaved = false;
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
                this.annotationSaved = true;
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
    }" @keydown.escape.window="navigatorOpen = false; notesOpen = false; exitOpen = false; finishOpen = false">
        <header class="sticky top-0 z-40 border-b border-outline-variant bg-white/95 backdrop-blur">
            <div class="flex h-header-height items-center justify-between gap-3 px-4 md:px-8">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <button type="button" @click="exitOpen = true"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full text-outline transition-colors hover:bg-surface-container-high"
                        aria-label="Tạm dừng và thoát">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                    <div class="hidden min-w-0 sm:block">
                        <p class="truncate font-label-md font-bold text-primary">{{ config('app.name') }}</p>
                        <p class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase">
                            {{ $isExam ? 'Exam mode' : 'Study mode' }}
                        </p>
                    </div>
                </div>

                <div class="flex min-w-32 flex-col items-center gap-1 sm:min-w-48 md:min-w-72">
                    <span class="font-label-sm font-bold text-on-surface-variant">Câu {{ $index + 1 }} / {{ $total }}</span>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-container-highest">
                        <div class="h-full bg-primary transition-all" style="width: {{ $progress }}%"></div>
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-end gap-2">
                    @if ($isExam)
                        <div class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 tabular-nums"
                            :class="remaining !== null && remaining <= 300 ? 'bg-error/10 text-error' : 'bg-primary/10 text-primary'"
                            aria-live="polite">
                            <span class="material-symbols-outlined text-[18px]">timer</span>
                            <span class="text-sm font-extrabold" x-text="formatTime(remaining)"></span>
                        </div>
                    @else
                        <span class="hidden items-center gap-1 text-primary md:flex">
                            <span class="material-symbols-outlined text-[18px]">cloud_done</span>
                            <span class="text-xs font-semibold">Tự động lưu</span>
                        </span>
                    @endif
                    <button type="button" @click="navigatorOpen = true"
                        class="flex size-10 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant transition-colors hover:bg-surface-container-high"
                        aria-label="Mở bản đồ câu hỏi">
                        <span class="material-symbols-outlined text-[21px]">grid_view</span>
                    </button>
                </div>
            </div>
        </header>

        @if ($errors->any())
            <div class="mx-auto mt-4 max-w-5xl px-4 md:px-8">
                <div class="rounded-xl border border-error/30 bg-error-container/40 p-4 text-body-sm text-on-error-container" role="alert">
                    {{ $errors->first() }}
                </div>
            </div>
        @endif

        <main class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 py-6 pb-32 md:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.82fr)] lg:py-10">
            <section class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                            {{ $question->topic?->name ?? 'Tổng hợp' }}
                        </span>
                        <span class="rounded-full bg-surface-container-high px-3 py-1 text-xs font-semibold text-on-surface-variant">
                            {{ $difficultyLabel }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="toggleFlag()"
                            class="flex size-10 items-center justify-center rounded-lg border transition-colors"
                            :class="flagged ? 'border-amber-300 bg-amber-50 text-amber-600' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-low'"
                            :aria-label="flagged ? 'Bỏ gắn cờ' : 'Gắn cờ'">
                            <span class="material-symbols-outlined text-[21px]"
                                :style="flagged ? &quot;font-variation-settings: 'FILL' 1&quot; : null">flag</span>
                        </button>
                        <button type="button" @click="notesOpen = true"
                            class="flex size-10 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant transition-colors hover:bg-surface-container-low"
                            aria-label="Mở ghi chú">
                            <span class="material-symbols-outlined text-[21px]">description</span>
                        </button>
                    </div>
                </div>

                <article class="space-y-5 rounded-2xl border border-outline-variant bg-white p-5 shadow-sm md:p-7">
                    <div class="flex items-center gap-2 text-label-sm font-bold tracking-wider text-primary uppercase">
                        <span class="material-symbols-outlined text-[18px]">clinical_notes</span>
                        Tình huống lâm sàng
                    </div>
                    <div class="prose prose-sm max-w-none font-body-lg text-body-lg leading-relaxed text-on-surface">{!! $stemHtml !!}</div>
                </article>

                @if (! $isExam && $isAnswered && filled($question->explanation))
                    <section class="space-y-3 rounded-2xl border border-primary/20 bg-primary/5 p-5 md:p-6">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">lightbulb</span>
                            <h2 class="font-headline-sm text-headline-sm text-on-surface">Giải thích</h2>
                        </div>
                        <div class="prose prose-sm max-w-none font-body-md text-body-md leading-relaxed text-on-surface">{!! \App\Support\Html\SafeHtml::forDisplay($question->explanation) !!}</div>
                    </section>
                @endif
            </section>

            <section class="space-y-4 lg:pt-14">
                <div class="flex items-center justify-between">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Chọn đáp án đúng nhất</h2>
                    @if (! $isExam && $isAnswered)
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $attempt?->is_correct ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                            {{ $attempt?->is_correct ? 'Trả lời đúng' : 'Trả lời sai' }}
                        </span>
                    @elseif ($isExam && $isAnswered)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-primary">
                            <span class="material-symbols-outlined text-[16px]">cloud_done</span> Đã lưu
                        </span>
                    @endif
                </div>

                <form method="POST" action="{{ route('qbank.session.answer', $session) }}" class="space-y-3"
                    @submit="answering = true">
                    @csrf
                    <input type="hidden" name="question_id" value="{{ $question->id }}">
                    <input type="hidden" name="index" value="{{ $index }}">
                    <input type="hidden" name="time_spent_seconds" :value="elapsed">

                    @foreach ($question->options as $option)
                        @php
                            $optionSelected = in_array((int) $option->id, $selectedOptionIds, true);
                            $showResult = ! $isExam && $isAnswered;
                            $isCorrectOption = $showResult && (bool) $option->is_correct;
                            $isWrongSelection = $showResult && $optionSelected && ! $option->is_correct;
                        @endphp
                        <label @class([
                            'group block overflow-hidden rounded-xl border bg-white transition-all',
                            'cursor-default border-success bg-success/5' => $isCorrectOption,
                            'cursor-default border-error bg-error/5' => $isWrongSelection,
                            'cursor-pointer border-outline-variant hover:border-primary/60 hover:bg-primary/5' => !$showResult,
                            'border-outline-variant opacity-70' => $showResult && !$isCorrectOption && !$isWrongSelection,
                        ])>
                            <input type="radio" name="option_ids[]" value="{{ $option->id }}"
                                x-model.number="selected" @checked($optionSelected)
                                @disabled(! $isExam && $isAnswered) required class="peer sr-only">
                            <span class="flex items-start gap-4 p-4 md:p-5">
                                <span @class([
                                    'flex size-9 shrink-0 items-center justify-center rounded-lg border font-bold transition-colors',
                                    'border-success bg-success text-white' => $isCorrectOption,
                                    'border-error bg-error text-white' => $isWrongSelection,
                                    'border-outline-variant text-on-surface-variant peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white' => !$showResult,
                                    'border-outline-variant text-outline' => $showResult && !$isCorrectOption && !$isWrongSelection,
                                ])>{{ $option->label }}</span>
                                <span class="min-w-0 flex-1 pt-1 text-body-md text-on-surface">{{ $option->content }}</span>
                                @if ($isCorrectOption)
                                    <span class="material-symbols-outlined text-success">check_circle</span>
                                @elseif ($isWrongSelection)
                                    <span class="material-symbols-outlined text-error">cancel</span>
                                @endif
                            </span>
                            @if ($showResult && ($isCorrectOption || $isWrongSelection) && filled($option->explanation))
                                <p class="border-t border-current/10 px-4 py-3 pl-16 text-body-sm leading-relaxed text-on-surface-variant md:px-5 md:pl-[76px]">
                                    {{ $option->explanation }}
                                </p>
                            @endif
                        </label>
                    @endforeach

                    @if (! $isAnswered || $isExam)
                        <button type="submit" :disabled="selected === null || answering"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-md transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40">
                            <span x-show="answering" class="material-symbols-outlined animate-spin text-[20px]">progress_activity</span>
                            <span>
                                @if ($isExam)
                                    {{ $nextIndex !== null ? 'Lưu & câu tiếp theo' : 'Lưu câu trả lời' }}
                                @else
                                    Kiểm tra đáp án
                                @endif
                            </span>
                            <span x-show="!answering" class="material-symbols-outlined text-[20px]">
                                {{ $isExam ? 'arrow_forward' : 'fact_check' }}
                            </span>
                        </button>
                    @endif
                </form>

                @if (! $isExam && $isAnswered)
                    @if ($nextIndex !== null)
                        <a href="{{ route('qbank.session', [$session, 'index' => $nextIndex]) }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-md transition-all hover:brightness-110">
                            Câu tiếp theo <span class="material-symbols-outlined">arrow_forward</span>
                        </a>
                    @else
                        <form method="POST" action="{{ route('qbank.session.finish', $session) }}">
                            @csrf
                            <button type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-md transition-all hover:brightness-110">
                                Xem tổng kết <span class="material-symbols-outlined">analytics</span>
                            </button>
                        </form>
                    @endif
                @endif
            </section>
        </main>

        <footer class="fixed inset-x-0 bottom-0 z-30 border-t border-outline-variant bg-white/95 px-4 py-3 shadow-[0_-6px_20px_rgba(19,27,46,0.06)] backdrop-blur md:px-8">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                @if ($previousIndex !== null)
                    <a href="{{ route('qbank.session', [$session, 'index' => $previousIndex]) }}"
                        class="inline-flex items-center gap-1 rounded-lg px-3 py-2 font-bold text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-primary">
                        <span class="material-symbols-outlined">chevron_left</span>
                        <span class="hidden sm:inline">Câu trước</span>
                    </a>
                @else
                    <span></span>
                @endif

                <div class="flex items-center gap-2">
                    <button type="button" @click="navigatorOpen = true"
                        class="inline-flex items-center gap-2 rounded-lg border border-outline-variant px-3 py-2 font-bold text-on-surface-variant hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[20px]">grid_view</span>
                        <span class="hidden sm:inline">Bản đồ</span>
                    </button>
                    @if ($isExam)
                        <button type="button" @click="finishOpen = true"
                            class="inline-flex items-center gap-2 rounded-lg bg-error px-4 py-2 font-bold text-white transition-opacity hover:opacity-90">
                            <span class="material-symbols-outlined text-[20px]">task_alt</span>
                            Nộp bài
                        </button>
                    @elseif ($nextIndex !== null && ! $isAnswered)
                        <a href="{{ route('qbank.session', [$session, 'index' => $nextIndex]) }}"
                            class="inline-flex items-center gap-1 rounded-lg px-3 py-2 font-bold text-on-surface-variant hover:bg-surface-container-low hover:text-primary">
                            Bỏ qua <span class="material-symbols-outlined">chevron_right</span>
                        </a>
                    @endif
                </div>
            </div>
        </footer>

        <div x-show="navigatorOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[80] bg-black/40 backdrop-blur-sm">
            <div class="absolute inset-0" @click="navigatorOpen = false"></div>
            <aside x-show="navigatorOpen" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                class="absolute top-0 right-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-outline-variant p-5">
                    <div>
                        <h2 class="font-headline-sm text-headline-sm">Bản đồ câu hỏi</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">{{ count($answeredIds) }}/{{ $total }} câu đã trả lời</p>
                    </div>
                    <button type="button" @click="navigatorOpen = false" class="flex size-10 items-center justify-center rounded-full hover:bg-surface-container-low">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-5">
                    <div class="mb-5 flex flex-wrap gap-4 text-xs text-on-surface-variant">
                        <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-primary"></span>Đã làm</span>
                        <span class="flex items-center gap-1.5"><span class="size-3 rounded border border-outline-variant bg-white"></span>Chưa làm</span>
                        <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[15px] text-amber-500">flag</span>Gắn cờ</span>
                    </div>
                    <div class="grid grid-cols-5 gap-3 sm:grid-cols-6">
                        @foreach ($questionIds as $position => $questionId)
                            @php
                                $wasAnswered = isset($answeredLookup[(string) $questionId]);
                                $wasFlagged = isset($flaggedLookup[(string) $questionId]);
                            @endphp
                            <a href="{{ route('qbank.session', [$session, 'index' => $position]) }}"
                                @class([
                                    'relative flex aspect-square items-center justify-center rounded-lg border text-sm font-bold transition-colors',
                                    'border-primary bg-primary text-white' => $wasAnswered && $position !== $index,
                                    'border-2 border-primary bg-primary/5 text-primary' => $position === $index,
                                    'border-outline-variant bg-white text-on-surface-variant hover:border-primary/50' => !$wasAnswered && $position !== $index,
                                ])>
                                {{ $position + 1 }}
                                @if ($wasFlagged)
                                    <span class="material-symbols-outlined absolute top-0.5 right-0.5 text-[13px] text-amber-500"
                                        style="font-variation-settings: 'FILL' 1;">flag</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
                @if ($isExam)
                    <div class="border-t border-outline-variant p-5">
                        <button type="button" @click="navigatorOpen = false; finishOpen = true"
                            class="w-full rounded-xl bg-error px-5 py-3 font-bold text-white">Nộp bài thi</button>
                    </div>
                @endif
            </aside>
        </div>

        <div x-show="notesOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="notesOpen = false"></div>
            <section class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="notesOpen = false">
                <div class="flex items-center justify-between border-b border-outline-variant px-5 py-4">
                    <div>
                        <h2 class="font-headline-sm text-headline-sm">Ghi chú cá nhân</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">Câu {{ $index + 1 }}</p>
                    </div>
                    <button type="button" @click="notesOpen = false" class="flex size-9 items-center justify-center rounded-full hover:bg-surface-container-low">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-5">
                    <label for="session-note" class="sr-only">Nội dung ghi chú</label>
                    <textarea id="session-note" x-model="noteText" rows="7" maxlength="5000"
                        class="w-full resize-none rounded-xl border border-outline-variant p-4 text-body-md focus:border-primary focus:ring-primary"
                        placeholder="Ghi lại điều cần nhớ về câu hỏi này..."></textarea>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span x-show="annotationError" class="text-error" x-text="annotationError"></span>
                        <span class="ml-auto text-on-surface-variant" x-text="noteText.length + '/5000'"></span>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-outline-variant bg-surface-container-lowest px-5 py-4">
                    <button type="button" @click="notesOpen = false" class="rounded-lg px-4 py-2 font-bold text-on-surface-variant">Hủy</button>
                    <button type="button" @click="saveNote()" :disabled="annotationSaving"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2 font-bold text-white disabled:opacity-50">
                        <span x-show="annotationSaving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                        Lưu ghi chú
                    </button>
                </div>
            </section>
        </div>

        <div x-show="exitOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="exitOpen = false"></div>
            <section class="relative w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl">
                <span class="material-symbols-outlined mb-3 text-5xl text-primary">pause_circle</span>
                <h2 class="font-headline-md text-headline-md">Tạm dừng phiên?</h2>
                <p class="mt-2 text-body-sm leading-relaxed text-on-surface-variant">Tiến trình đã làm và ghi chú sẽ được lưu để bạn tiếp tục sau.</p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <button type="button" @click="exitOpen = false" class="rounded-xl border border-outline-variant px-4 py-3 font-bold text-on-surface-variant">Tiếp tục làm</button>
                    <form method="POST" action="{{ route('qbank.session.pause', $session) }}">
                        @csrf
                        <input type="hidden" name="current_index" value="{{ $index }}">
                        <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 font-bold text-white">Lưu & thoát</button>
                    </form>
                </div>
            </section>
        </div>

        <div x-show="finishOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="finishOpen = false"></div>
            <section class="relative w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl">
                <span class="material-symbols-outlined mb-3 text-5xl text-error">assignment_turned_in</span>
                <h2 class="font-headline-md text-headline-md">Xác nhận nộp bài?</h2>
                <p class="mt-2 text-body-sm leading-relaxed text-on-surface-variant">
                    Bạn đã trả lời <strong class="text-on-surface">{{ count($answeredIds) }}/{{ $total }}</strong> câu.
                    Sau khi nộp, bạn không thể thay đổi đáp án.
                </p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <button type="button" @click="finishOpen = false" class="rounded-xl border border-outline-variant px-4 py-3 font-bold text-on-surface-variant">Kiểm tra lại</button>
                    <form x-ref="finishForm" method="POST" action="{{ route('qbank.session.finish', $session) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-error px-4 py-3 font-bold text-white">Nộp bài</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.auth>
