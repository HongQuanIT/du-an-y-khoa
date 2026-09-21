@php
    /** @var array{current_cycle:int,pipeline_reject_count:int,total_rejects:int,cycles:list<array>,segments?:list<array>}|null $reviewTimeline */
    $reviewTimeline = $reviewTimeline ?? null;
    $canAdjudicateQa = (bool) ($canAdjudicateQa ?? false);
    $qaSaveUrl = $question->exists ? route('admin.questions.review-outcomes', $question) : null;
    $segments = is_array($reviewTimeline) ? ($reviewTimeline['segments'] ?? []) : [];
    $hasTimeline = $segments !== [] || (is_array($reviewTimeline) && ($reviewTimeline['cycles'] ?? []) !== []);
@endphp

@if ($hasTimeline)
    @php
        $defaultOpen = collect($segments)->contains(fn (array $s): bool => (bool) ($s['is_open_default'] ?? false))
            || $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected
            || $question->hasRedReviewerFlag();
        $publishedCount = collect($segments)->where('kind', 'published')->count();
        $currentSegment = collect($segments)->firstWhere('kind', 'current');
        $qbankSegment = collect($segments)->firstWhere('is_qbank_live', true);
    @endphp

    <section aria-labelledby="review-timeline-title"
        class="mb-5 rounded-2xl border border-outline-variant bg-surface px-4 py-4"
        data-testid="question-review-timeline"
        x-data="{ open: {{ $defaultOpen ? 'true' : 'false' }} }">
        <button type="button" class="flex w-full items-start justify-between gap-3 text-left"
            @click="open = !open" :aria-expanded="open.toString()">
            <div class="min-w-0">
                <h2 id="review-timeline-title" class="font-semibold text-on-surface">Lịch sử duyệt</h2>
                <p class="mt-0.5 text-sm text-on-surface-variant">
                    @if ($qbankSegment)
                        Bản đang dùng: <span class="font-medium text-on-surface">v{{ $qbankSegment['version'] }}</span>
                        @if ($currentSegment)
                            · Bản làm việc: <span class="font-medium text-on-surface">{{ $currentSegment['status_label'] }}</span>
                        @endif
                    @elseif ($currentSegment)
                        Bản hiện tại: <span class="font-medium text-on-surface">{{ $currentSegment['status_label'] }}</span>
                        · {{ $currentSegment['summary'] }}
                    @elseif ($publishedCount > 0)
                        {{ $publishedCount }} phiên bản đã xuất bản
                    @endif
                    @if ($publishedCount > 0 && ($qbankSegment || $currentSegment))
                        · {{ $publishedCount }} phiên bản đã XB
                    @endif
                </p>
            </div>
            <span class="material-symbols-outlined shrink-0 text-on-surface-variant transition"
                :class="open ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
        </button>

        <div class="mt-4 space-y-3" x-show="open" x-cloak>
            @foreach ($segments as $segment)
                @php
                    $statusToneClass = match ($segment['status_tone'] ?? 'neutral') {
                        'green' => 'bg-emerald-100 text-emerald-800',
                        'red' => 'bg-rose-100 text-rose-800',
                        'amber' => 'bg-amber-100 text-amber-900',
                        'sky' => 'bg-sky-100 text-sky-800',
                        'violet' => 'bg-violet-100 text-violet-800',
                        default => 'bg-surface-container text-on-surface-variant',
                    };
                    $borderClass = ($segment['kind'] ?? '') === 'current'
                        ? 'border-primary/35 bg-primary/[0.03]'
                        : 'border-outline-variant/80 bg-surface-container-lowest';
                @endphp

                <article
                    class="overflow-hidden rounded-xl border {{ $borderClass }}"
                    data-testid="review-timeline-segment-{{ $segment['key'] }}"
                    x-data="{ expanded: {{ ($segment['is_open_default'] ?? false) ? 'true' : 'false' }} }"
                >
                    <button type="button"
                        class="flex w-full items-start gap-3 px-3.5 py-3 text-left hover:bg-surface/60"
                        @click="expanded = !expanded"
                        :aria-expanded="expanded.toString()">
                        <span class="material-symbols-outlined mt-0.5 shrink-0 text-[20px] text-on-surface-variant transition"
                            :class="expanded ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-on-surface">{{ $segment['title'] }}</p>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $statusToneClass }}">
                                    {{ $segment['status_label'] }}
                                </span>
                                @if (($segment['kind'] ?? '') === 'current')
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">Đang làm việc</span>
                                @elseif (! empty($segment['version']))
                                    <span class="rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-bold text-on-surface-variant">v{{ $segment['version'] }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                {{ $segment['summary'] }}
                                @if (! empty($segment['published_at']))
                                    · XB {{ $segment['published_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @endif
                                @if (filled($segment['publisher_name'] ?? null))
                                    · {{ $segment['publisher_name'] }}
                                @endif
                            </p>
                        </div>
                    </button>

                    <div class="space-y-2.5 border-t border-outline-variant/60 px-3.5 py-3" x-show="expanded" x-cloak>
                        @if (($segment['cycles'] ?? []) === [])
                            <p class="rounded-lg border border-dashed border-outline-variant bg-surface px-3 py-4 text-center text-sm text-on-surface-variant">
                                @if (($segment['kind'] ?? '') === 'current')
                                    Chưa có hoạt động duyệt trên bản làm việc hiện tại.
                                @else
                                    Không có vòng duyệt được ghi nhận cho phiên bản này.
                                @endif
                            </p>
                        @else
                            @foreach ($segment['cycles'] as $cycle)
                                <div class="rounded-lg border border-outline-variant/70 bg-surface px-3 py-2.5"
                                    data-testid="review-timeline-cycle-{{ $cycle['cycle'] }}">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-on-surface">Vòng {{ $cycle['display_cycle'] ?? $cycle['cycle'] }}</p>
                                        @if ($cycle['rejects'] > 0)
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-800">
                                                {{ $cycle['rejects'] }} lần từ chối
                                            </span>
                                        @endif
                                        @if ((int) $cycle['cycle'] === (int) ($reviewTimeline['current_cycle'] ?? 0)
                                            && ($segment['kind'] ?? '') === 'current')
                                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">Vòng hiện tại</span>
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
                                                $qa = $entry['qa'] ?? null;
                                            @endphp
                                            <li class="relative pl-3" data-testid="review-timeline-entry-{{ $entry['type'] }}">
                                                <span class="absolute -left-[0.45rem] top-1.5 size-2 rounded-full {{ $dot }}" aria-hidden="true"></span>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $badge }}">{{ $entry['label'] }}</span>
                                                    @if (filled($entry['actor_name']))
                                                        <span class="text-sm font-medium text-on-surface">{{ $entry['actor_name'] }}</span>
                                                    @endif
                                                    @if ($entry['occurred_at'])
                                                        <span class="text-xs text-on-surface-variant">
                                                            {{ $entry['occurred_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if (filled($entry['note']))
                                                    <p class="mt-1 text-sm leading-5 text-on-surface-variant">{{ $entry['note'] }}</p>
                                                @endif

                                                @if ($canAdjudicateQa && is_array($qa) && $qaSaveUrl)
                                                    <div
                                                        class="mt-1.5"
                                                        data-testid="review-qa-form-{{ $qa['kind'] }}-{{ $qa['id'] }}"
                                                        x-data="reviewQaMarker({
                                                            url: @js($qaSaveUrl),
                                                            csrf: @js(csrf_token()),
                                                            kind: @js($qa['kind']),
                                                            id: {{ (int) $qa['id'] }},
                                                            outcome: @js($qa['current'] ?? 'pending'),
                                                            outcomeLabel: @js($qa['current_label'] ?? null),
                                                            note: @js($qa['note'] ?? ''),
                                                            locked: @js((bool) ($qa['locked'] ?? false)),
                                                            options: @js($qa['options']),
                                                        })"
                                                    >
                                                        <div class="flex flex-wrap items-center gap-2" x-show="locked" x-cloak>
                                                            <span
                                                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                                                :class="badgeClass"
                                                                x-text="outcomeLabel || 'Chưa đánh giá'"
                                                            ></span>
                                                            <span
                                                                class="max-w-md truncate text-xs text-on-surface-variant"
                                                                x-show="note"
                                                                x-text="note"
                                                                :title="note"
                                                            ></span>
                                                            <button type="button"
                                                                class="h-7 rounded-md border border-outline-variant px-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                                                                @click="openEdit()"
                                                                :disabled="saving">
                                                                Mở QA
                                                            </button>
                                                        </div>

                                                        <div class="flex flex-wrap items-center gap-1.5" x-show="!locked" x-cloak>
                                                            <select
                                                                x-model="outcome"
                                                                :disabled="saving"
                                                                class="h-7 min-w-[7.5rem] rounded-md border border-outline-variant bg-surface px-1.5 text-xs text-on-surface outline-none focus:border-primary disabled:opacity-60"
                                                            >
                                                                @foreach ($qa['options'] as $option)
                                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                                @endforeach
                                                            </select>
                                                            <input
                                                                type="text"
                                                                x-model="note"
                                                                maxlength="500"
                                                                :disabled="saving"
                                                                placeholder="Ghi chú QA…"
                                                                class="h-7 min-w-[8rem] flex-1 rounded-md border border-outline-variant bg-surface px-2 text-xs text-on-surface outline-none focus:border-primary disabled:opacity-60"
                                                            >
                                                            <button type="button"
                                                                class="h-7 rounded-md bg-on-surface px-2.5 text-xs font-semibold text-surface hover:opacity-90 disabled:opacity-60"
                                                                @click="save()"
                                                                :disabled="saving">
                                                                <span x-text="saving ? 'Đang lưu…' : 'Lưu QA'"></span>
                                                            </button>
                                                            <span class="text-xs text-rose-700" x-show="error" x-text="error"></span>
                                                        </div>
                                                    </div>
                                                @elseif (filled($entry['outcome_label'] ?? null))
                                                    <span class="mt-1 inline-flex rounded-full bg-surface-container px-2 py-0.5 text-xs text-on-surface-variant">
                                                        {{ $entry['outcome_label'] }}
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <script>
        document.addEventListener('alpine:init', () => {
            if (window.__reviewQaMarkerRegistered) return;
            window.__reviewQaMarkerRegistered = true;

            Alpine.data('reviewQaMarker', (config) => ({
                url: config.url,
                csrf: config.csrf,
                kind: config.kind,
                id: config.id,
                options: config.options || [],
                outcome: config.outcome || 'pending',
                outcomeLabel: config.outcomeLabel || null,
                note: config.note || '',
                locked: Boolean(config.locked),
                saving: false,
                error: '',

                get badgeClass() {
                    if (['miss', 'over_reject', 'false_positive'].includes(this.outcome)) {
                        return 'bg-amber-100 text-amber-900';
                    }
                    if (this.outcome === 'confirmed') {
                        return 'bg-emerald-100 text-emerald-800';
                    }
                    return 'bg-surface-container text-on-surface-variant';
                },

                openEdit() {
                    this.error = '';
                    this.locked = false;
                },

                async save() {
                    if (this.saving) return;
                    this.saving = true;
                    this.error = '';

                    try {
                        const response = await fetch(this.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                kind: this.kind,
                                id: this.id,
                                outcome: this.outcome,
                                outcome_note: this.note || null,
                            }),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (! response.ok) {
                            const firstError = payload?.errors
                                ? Object.values(payload.errors).flat()[0]
                                : null;
                            throw new Error(firstError || payload?.message || 'Không lưu được QA.');
                        }

                        this.outcome = payload.outcome || this.outcome;
                        this.outcomeLabel = payload.outcome_label || null;
                        this.note = payload.outcome_note || '';
                        this.locked = payload.locked !== false && this.outcome !== 'pending';
                    } catch (e) {
                        this.error = e?.message || 'Không lưu được QA.';
                    } finally {
                        this.saving = false;
                    }
                },
            }));
        });
    </script>
@endif
