@php
    /** @var array{current_cycle:int,pipeline_reject_count:int,total_rejects:int,cycles:list<array{cycle:int,rejects:int,entries:list<array<string,mixed>}>}|null $reviewTimeline */
    $reviewTimeline = $reviewTimeline ?? null;
@endphp

@if (is_array($reviewTimeline) && $reviewTimeline['cycles'] !== [])
    <section aria-labelledby="review-timeline-title"
        class="mb-5 rounded-2xl border border-outline-variant bg-surface px-4 py-4"
        data-testid="question-review-timeline"
        x-data="{ open: {{ $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected || $question->hasRedReviewerFlag() ? 'true' : 'false' }} }">
        <button type="button" class="flex w-full items-start justify-between gap-3 text-left"
            @click="open = !open" :aria-expanded="open.toString()">
            <div class="min-w-0">
                <h2 id="review-timeline-title" class="font-semibold text-on-surface">Lịch sử duyệt</h2>
                <p class="mt-0.5 text-sm text-on-surface-variant">
                    Vòng hiện tại {{ $reviewTimeline['current_cycle'] }}
                    @if ($reviewTimeline['pipeline_reject_count'] > 0)
                        · {{ $reviewTimeline['pipeline_reject_count'] }} lần trả về trước khi xuất bản
                    @endif
                    · {{ count($reviewTimeline['cycles']) }} vòng đã ghi nhận
                </p>
            </div>
            <span class="material-symbols-outlined shrink-0 text-on-surface-variant transition"
                :class="open ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
        </button>

        <div class="mt-4 space-y-4" x-show="open" x-cloak>
            @foreach ($reviewTimeline['cycles'] as $cycle)
                <article class="rounded-xl border border-outline-variant/80 bg-surface-container-lowest px-3 py-3"
                    data-testid="review-timeline-cycle-{{ $cycle['cycle'] }}">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-on-surface">Vòng {{ $cycle['cycle'] }}</p>
                        @if ($cycle['rejects'] > 0)
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-800">
                                {{ $cycle['rejects'] }} lần từ chối
                            </span>
                        @endif
                        @if ((int) $cycle['cycle'] === (int) $reviewTimeline['current_cycle'])
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">Hiện tại</span>
                        @endif
                    </div>
                    <ol class="space-y-2 border-l border-outline-variant pl-3">
                        @foreach ($cycle['entries'] as $entry)
                            @php
                                $dot = match ($entry['tone'] ?? 'neutral') {
                                    'red' => 'bg-rose-500',
                                    'green' => 'bg-emerald-500',
                                    'primary' => 'bg-primary',
                                    default => 'bg-outline-variant',
                                };
                                $badge = match ($entry['tone'] ?? 'neutral') {
                                    'red' => 'bg-rose-100 text-rose-800',
                                    'green' => 'bg-emerald-100 text-emerald-800',
                                    'primary' => 'bg-primary/10 text-primary',
                                    default => 'bg-surface-container text-on-surface-variant',
                                };
                            @endphp
                            <li class="relative pl-3" data-testid="review-timeline-entry-{{ $entry['type'] }}">
                                <span class="absolute -left-[0.45rem] top-1.5 size-2 rounded-full {{ $dot }}" aria-hidden="true"></span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $badge }}">{{ $entry['label'] }}</span>
                                    @if (filled($entry['actor_name']))
                                        <span class="text-sm font-medium text-on-surface">{{ $entry['actor_name'] }}</span>
                                    @endif
                                    @if (filled($entry['outcome_label'] ?? null))
                                        <span class="rounded-full bg-surface-container px-2 py-0.5 text-xs text-on-surface-variant">
                                            {{ $entry['outcome_label'] }}
                                        </span>
                                    @endif
                                    @if ($entry['occurred_at'])
                                        <span class="text-xs text-on-surface-variant">
                                            {{ $entry['occurred_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                        </span>
                                    @endif
                                </div>
                                @if (filled($entry['note']))
                                    <p class="mt-1 rounded-lg border border-outline-variant/60 bg-surface px-2.5 py-1.5 text-sm leading-5 text-on-surface">
                                        {{ $entry['note'] }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </article>
            @endforeach
        </div>
    </section>
@endif
