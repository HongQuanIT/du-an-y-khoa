@php
    /** @var \Modules\QuestionBank\Models\Question $question */
    $revealAnswers = $revealAnswers ?? true;
    $keyInfoRenderer = app(\Modules\QuestionBank\Services\QuestionKeyInfoRenderer::class);
    $keyInfo = $keyInfoRenderer->resolvePhrases(
        (string) $question->stem,
        (array) ($question->key_info ?? []),
    );
    $hasHintMarks = str_contains((string) $question->stem, 'data-hint');
    $hasKeyInfo = $keyInfo !== [] || $hasHintMarks;
    $stemHtml = \App\Support\Html\SafeHtml::forDisplay((string) $question->stem);
    $keyInfoHtml = $keyInfoRenderer->render((string) $question->stem, $keyInfo);
    $attendingTip = \App\Support\Html\SafeHtml::forDisplay((string) ($question->attending_tip ?? ''));
    $hasAttendingTip = $attendingTip !== '';
    $hints = $question->relationLoaded('hints')
        ? $question->hints
        : $question->hints()->orderBy('sort_order')->get();
    $stemImageUrl = $question->stemImageUrl();
    $categoryBadge = \Modules\QuestionBank\Support\QuestionCategoryBadge::resolve(
        $question->lessons,
        $question->difficulty,
    );
@endphp

<div class="overflow-hidden rounded-2xl border border-outline-variant bg-white"
    data-testid="reviewer-question-preview"
    x-data="{
        keyInfoEnabled: {{ $hasKeyInfo && $revealAnswers ? 'true' : 'false' }},
        attendingTipOpen: {{ $hasAttendingTip && $revealAnswers ? 'true' : 'false' }},
        hasKeyInfo: @js($hasKeyInfo),
        hasAttendingTip: @js($hasAttendingTip),
        toggleKeyInfo() {
            if (!this.hasKeyInfo) return;
            this.keyInfoEnabled = !this.keyInfoEnabled;
        },
        toggleAttendingTip() {
            if (!this.hasAttendingTip) return;
            this.attendingTipOpen = !this.attendingTipOpen;
        },
    }">
    <div class="space-y-6 px-4 py-6 md:px-8" :class="{ 'key-info-active': keyInfoEnabled }">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <span class="inline-flex max-w-[min(100%,16rem)] items-center gap-1.5 truncate rounded-full bg-surface-container-highest px-3 py-1 font-label-sm text-label-sm font-bold text-on-surface-variant"
                title="{{ $categoryBadge['category'] }}">
                <span class="size-2 shrink-0 rounded-full bg-primary"></span>
                <span class="truncate">{{ $categoryBadge['category'] }}</span>
            </span>
            <span class="shrink-0 rounded-full px-3 py-1 font-label-sm text-label-sm font-bold {{ $categoryBadge['difficulty_tone'] }}">
                {{ $categoryBadge['difficulty'] }}
            </span>
            @if ($revealAnswers)
                <span class="rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-bold text-primary">
                    Xem như học viên
                </span>
            @endif
        </div>

        <article class="space-y-5">
            <div x-show="!keyInfoEnabled">
                <div id="reviewer-stem" class="question-rich-content prose prose-sm max-w-none font-body-lg text-body-lg leading-relaxed text-on-surface select-text">{!! $stemHtml !!}</div>
            </div>
            <div x-cloak x-show="keyInfoEnabled">
                <div class="question-rich-content prose prose-sm max-w-none font-body-lg text-body-lg leading-relaxed text-on-surface select-text"
                    data-testid="reviewer-key-info-stem">{!! $keyInfoHtml !!}</div>
            </div>

            @if ($stemImageUrl)
                <aside class="overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                    <div class="flex justify-center bg-white">
                        <img src="{{ $stemImageUrl }}" alt="Ảnh minh họa câu hỏi"
                            class="h-auto max-h-[480px] w-full object-contain">
                    </div>
                </aside>
            @endif
        </article>

        <div class="flex min-h-12 flex-wrap items-center border-y border-outline-variant bg-surface-container-lowest px-1"
            data-testid="reviewer-knowledge-toolbar">
            <button type="button" @click="toggleKeyInfo()" :disabled="!hasKeyInfo"
                class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                :class="keyInfoEnabled
                    ? 'border-amber-600 text-amber-700'
                    : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                title="{{ $hasKeyInfo ? 'Gạch chân các ý chính như học viên' : 'Câu này chưa có gợi ý được đánh dấu' }}"
                :aria-pressed="keyInfoEnabled">
                <span class="material-symbols-outlined text-[18px]">format_align_left</span>
                <span>Gợi ý</span>
            </button>
            <button type="button" @click="toggleAttendingTip()" :disabled="!hasAttendingTip"
                class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                :class="attendingTipOpen
                    ? 'border-amber-600 text-amber-700'
                    : 'border-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-primary'"
                title="{{ $hasAttendingTip ? 'Mở kiến thức cho câu hỏi' : 'Câu này chưa có phần kiến thức' }}"
                :aria-pressed="attendingTipOpen">
                <span class="material-symbols-outlined text-[18px]">help</span>
                <span>Kiến thức</span>
            </button>
        </div>

        @if ($hasAttendingTip)
            <div x-show="attendingTipOpen" class="space-y-3"
                data-testid="reviewer-attending-tip">
                <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-on-surface">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 shrink-0 text-amber-700">stethoscope</span>
                        <div>
                            <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-amber-700">Kiến thức</p>
                            <div class="question-rich-content prose prose-sm max-w-none font-body-md text-body-md leading-relaxed italic">{!! $attendingTip !!}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($hints->isNotEmpty())
            <div class="space-y-2 rounded-xl border border-outline-variant bg-surface-container-lowest p-4"
                data-testid="reviewer-hints">
                <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Gợi ý</p>
                <ul class="list-disc space-y-1 pl-5 text-sm text-on-surface">
                    @foreach ($hints as $hint)
                        <li class="question-rich-content">{!! \App\Support\Html\SafeHtml::forDisplay((string) $hint->content) !!}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="space-y-3" data-testid="reviewer-options">
            @foreach ($question->options as $option)
                @php
                    $optionContent = \App\Support\Html\SafeHtml::forDisplay((string) $option->content);
                    $optionExplanation = \App\Support\Html\SafeHtml::forDisplay((string) ($option->explanation ?? ''));
                    if ($optionExplanation === '') {
                        $optionExplanation = $option->is_correct
                            ? 'Đây là đáp án đúng.'
                            : 'Đây không phải đáp án đúng.';
                    }
                @endphp
                <div @class([
                    'flex w-full flex-col overflow-hidden rounded-xl border text-left',
                    'border-[#16A34A] bg-[#16A34A]/5' => $revealAnswers && $option->is_correct,
                    'border-outline-variant bg-white' => ! ($revealAnswers && $option->is_correct),
                ])>
                    <div class="flex items-start gap-4 p-4">
                        <span @class([
                            'flex size-8 shrink-0 items-center justify-center rounded-full font-bold',
                            'bg-[#16A34A] text-white' => $revealAnswers && $option->is_correct,
                            'border border-outline-variant text-on-surface-variant' => ! ($revealAnswers && $option->is_correct),
                        ])>{{ $option->label }}</span>
                        <div class="min-w-0 flex-1 space-y-1 pt-1">
                            <div class="question-rich-content prose prose-sm max-w-none font-body-md text-body-md text-on-surface">{!! $optionContent !!}</div>
                        </div>
                        @if ($revealAnswers && $option->is_correct)
                            <span class="material-symbols-outlined text-[#16A34A]"
                                style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        @endif
                    </div>
                    @if ($revealAnswers)
                        <div class="space-y-2 border-t border-outline-variant/40 px-4 pb-4 pl-16">
                            <p @class([
                                'text-label-sm font-bold tracking-wide uppercase',
                                'text-[#16A34A]' => $option->is_correct,
                                'text-error' => ! $option->is_correct,
                            ])>{{ $option->is_correct ? 'Đáp án đúng' : 'Vì sao sai' }}</p>
                            <div class="question-rich-content prose prose-sm max-w-none text-body-sm leading-relaxed text-on-surface-variant">
                                {!! $optionExplanation !!}
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    </div>
</div>

<style>
    #reviewer-stem mark[data-hint],
    [data-testid="reviewer-key-info-stem"] mark[data-hint] {
        cursor: default;
        background-color: transparent;
        color: inherit;
        text-decoration: none;
    }
    .key-info-active #reviewer-stem mark[data-hint],
    .key-info-active [data-testid="reviewer-key-info-stem"] mark[data-hint] {
        text-decoration: underline #ea580c;
        text-decoration-style: solid;
        text-decoration-thickness: 2px;
        text-underline-offset: 4px;
    }
</style>
