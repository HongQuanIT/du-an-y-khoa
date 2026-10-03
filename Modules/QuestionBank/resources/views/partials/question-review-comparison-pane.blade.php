@php
    $isPublished = $side === 'published';
    $textKey = $isPublished ? 'published_html' : 'proposed_html';
    $chipKey = $isPublished ? 'published' : 'proposed';
    $keyInfoItems = $comparison['key_info'][$chipKey] ?? [];
    $preserveRichText = (bool) ($preserveRichText ?? false);
    $highlightChanges = (bool) ($highlightChanges ?? true);
    $reviewerStyle = (bool) ($reviewerStyle ?? false);
    $preserveRawRichText = $preserveRichText;
    $attendingHtml = $preserveRawRichText
        ? ($comparison['raw_attending_tip'][$textKey] ?? '')
        : ($comparison['attending_tip'][$textKey] ?? '');
    $hasAttendingTip = filled(strip_tags((string) $attendingHtml));
    $stemHtml = $preserveRawRichText
        ? ($comparison['raw_stem'][$textKey] ?? '')
        : ($comparison['stem'][$textKey] ?? '');
    $hintPhrases = collect($keyInfoItems)
        ->map(fn (array $item): string => trim(strip_tags((string) ($item['html'] ?? ''))))
        ->filter()
        ->values()
        ->all();
    $stemWithHints = $reviewerStyle
        ? app(\Modules\QuestionBank\Services\QuestionKeyInfoRenderer::class)->render((string) $stemHtml, $hintPhrases)
        : $stemHtml;
@endphp

<div class="space-y-5"
    @if ($reviewerStyle)
        data-learner-image-viewer
        x-data="{ hintOpen: true, knowledgeOpen: true }"
        :class="{ 'key-info-active': hintOpen }"
    @endif>
    <div>
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Câu hỏi</h4>
            @if ($highlightChanges && $comparison['stem']['changed'])
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        <div @class([
            'question-rich-content instructor-image-preview prose prose-sm max-w-none text-sm leading-6 text-on-surface',
            'instructor-key-info-stem' => $reviewerStyle,
        ])>
            @if (filled($stemWithHints))
                {!! $stemWithHints !!}
            @endif
            @if ($preserveRawRichText && $comparison['can_compare'] && filled($comparison['stem'][$textKey] ?? null))
                <span class="sr-only" aria-hidden="true">{!! $comparison['stem'][$textKey] !!}</span>
            @endif
        </div>
    </div>

    @if ($comparison['stem_image']['published_url'] || $comparison['stem_image']['proposed_url'])
        <div>
            <div class="mb-2 flex items-center gap-2">
                <h4 class="text-sm font-bold text-on-surface">Hình kèm câu hỏi</h4>
                @if ($highlightChanges && $comparison['stem_image']['changed'])
                    <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
                @endif
            </div>
            @php $imageUrl = $comparison['stem_image'][$isPublished ? 'published_url' : 'proposed_url']; @endphp
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $isPublished ? 'Hình bản đang xuất bản' : 'Hình bản cần duyệt' }}"
                    class="max-h-72 rounded-xl border {{ $highlightChanges && $comparison['stem_image']['changed'] ? ($isPublished ? 'border-rose-300' : 'border-emerald-300') : 'border-outline-variant' }} object-contain">
            @else
                <p class="rounded-xl border border-dashed {{ $isPublished ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-emerald-300 bg-emerald-50 text-emerald-900' }} px-3 py-2 text-sm">
                    {{ $isPublished ? 'Hình đã bị gỡ ở bản gửi duyệt.' : 'Hình mới được thêm.' }}
                </p>
            @endif
        </div>
    @endif

    <div>
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Đáp án</h4>
            @if ($highlightChanges && collect($comparison['options'])->contains(fn (array $row): bool => $row['change'] !== 'same'))
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        <div class="space-y-2">
            @foreach ($comparison['options'] as $option)
                @php
                    $sideOption = $option[$chipKey];
                    $change = $highlightChanges ? $option['change'] : 'same';
                    $correctChanged = $highlightChanges && $option['correct_changed'];
                    $changeLabel = match ($change) {
                        'removed' => 'Xóa',
                        'added' => 'Thêm',
                        'modified' => 'Sửa',
                        default => null,
                    };
                @endphp
                @if ($sideOption === null)
                    <div class="rounded-xl border border-dashed px-3 py-2 text-sm {{ $isPublished ? 'border-emerald-300 bg-emerald-50/60 text-emerald-900' : 'border-rose-300 bg-rose-50/70 text-rose-800' }}">
                        <span class="mr-2 rounded-md px-1.5 py-0.5 text-[11px] font-bold {{ $isPublished ? 'bg-emerald-200 text-emerald-900' : 'bg-rose-200 text-rose-800' }}">{{ $isPublished ? 'Thêm' : 'Xóa' }}</span>
                        {{ $isPublished ? 'Đáp án mới — không có ở bản xuất bản.' : 'Đáp án đã bị xóa ở bản gửi duyệt.' }}
                    </div>
                @else
                    <div @class([
                        'rounded-xl border text-sm',
                        'flex w-full flex-col overflow-hidden text-left' => $reviewerStyle,
                        'px-3 py-2' => ! $reviewerStyle,
                        'border-rose-300 bg-rose-50 text-rose-900' => $change === 'removed',
                        'border-emerald-300 bg-emerald-50 text-emerald-950' => $change === 'added',
                        'border-amber-300 bg-amber-50' => $change === 'modified',
                        'border-[#16A34A] bg-[#16A34A]/5 text-emerald-900' => $change === 'same' && $sideOption['is_correct'],
                        'border-outline-variant bg-surface-container-lowest' => $change === 'same' && ! $sideOption['is_correct'],
                    ])>
                        <div @class(['flex items-start', 'gap-4 p-4' => $reviewerStyle, 'gap-2' => ! $reviewerStyle])>
                            @if ($changeLabel)
                                <span @class([
                                    'mt-0.5 shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-bold',
                                    'bg-rose-200 text-rose-800' => $change === 'removed',
                                    'bg-amber-200 text-amber-900' => $change === 'modified',
                                    'bg-emerald-200 text-emerald-900' => $change === 'added',
                                ])>{{ $changeLabel }}</span>
                            @endif
                            <span @class([
                                'shrink-0 font-bold',
                                'flex size-8 items-center justify-center rounded-full' => $reviewerStyle,
                                'bg-[#16A34A] text-white' => $reviewerStyle && $sideOption['is_correct'],
                                'border border-outline-variant text-on-surface-variant' => $reviewerStyle && ! $sideOption['is_correct'],
                            ])>{{ $sideOption['label'] }}{{ $reviewerStyle ? '' : '.' }}</span>
                            <div @class([
                                'instructor-image-preview min-w-0 flex-1 prose prose-sm max-w-none',
                                'pt-1 text-body-md text-on-surface' => $reviewerStyle,
                                'text-sm' => ! $reviewerStyle,
                            ])>
                                {!! filled($sideOption['content_html']) ? $sideOption['content_html'] : e($empty) !!}
                            </div>
                            @if ($sideOption['is_correct'])
                                @if ($reviewerStyle)
                                    <span class="material-symbols-outlined shrink-0 text-[#16A34A]" aria-label="Đáp án đúng"
                                        style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                @else
                                    <span @class([
                                        'shrink-0 text-xs font-bold',
                                        'rounded bg-emerald-200 px-1.5 py-0.5 text-emerald-900' => $correctChanged && ! $isPublished,
                                        'rounded bg-rose-200 px-1.5 py-0.5 text-rose-800 line-through' => $correctChanged && $isPublished,
                                    ])>Đáp án đúng</span>
                                @endif
                            @elseif ($correctChanged)
                                <span class="shrink-0 text-xs font-bold {{ $isPublished ? 'text-rose-700' : 'text-emerald-800' }}">
                                    {{ $isPublished ? 'Không còn là đáp án đúng' : 'Được chọn làm đáp án đúng' }}
                                </span>
                            @endif
                        </div>
                        @if (filled($sideOption['explanation_html']))
                            <div @class([
                                'border-t border-current/10 text-on-surface-variant',
                                'space-y-2 px-4 pb-4 pl-16 pt-2 text-body-sm leading-relaxed' => $reviewerStyle,
                                'mt-1 pt-1 text-xs leading-5' => ! $reviewerStyle,
                            ])>
                                <span @class([
                                    'font-semibold',
                                    'text-label-sm font-bold uppercase tracking-wide' => $reviewerStyle,
                                    'text-[#16A34A]' => $reviewerStyle && $sideOption['is_correct'],
                                    'text-error' => $reviewerStyle && ! $sideOption['is_correct'],
                                ])>{{ $reviewerStyle ? ($sideOption['is_correct'] ? 'Đáp án đúng' : 'Vì sao sai') : 'Giải thích:' }}</span>
                                <div class="instructor-image-preview prose prose-sm mt-0.5 max-w-none">
                                    {!! $sideOption['explanation_html'] !!}
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <div>
        @if ($reviewerStyle)
            <div class="flex min-h-12 items-center border-y border-outline-variant bg-surface-container-lowest px-1">
                <button type="button" @click="hintOpen = !hintOpen"
                    class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors"
                    :class="hintOpen ? 'border-amber-600 text-amber-700' : 'border-transparent text-on-surface-variant hover:bg-surface-container-high'"
                    :aria-pressed="hintOpen">
                    <span class="material-symbols-outlined text-[20px]">format_align_left</span>
                    <span>Gợi ý</span>
                </button>
                <button type="button" @click="knowledgeOpen = !knowledgeOpen" @disabled(! $hasAttendingTip)
                    class="inline-flex h-12 items-center gap-2 border-b-2 px-3 text-label-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                    :class="knowledgeOpen ? 'border-amber-600 text-amber-700' : 'border-transparent text-on-surface-variant hover:bg-surface-container-high'"
                    :aria-pressed="knowledgeOpen">
                    <span class="material-symbols-outlined text-[20px]">help</span>
                    <span>Kiến thức</span>
                </button>
            </div>
        @endif

        <div @class(['mt-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4' => $reviewerStyle])
            @if ($reviewerStyle) x-show="hintOpen" @endif>
            <div class="mb-2 flex items-center gap-2">
                <h4 class="text-sm font-bold text-on-surface">Gợi ý</h4>
                @if ($highlightChanges && $comparison['key_info']['changed'])
                    <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
                @endif
            </div>
            @foreach ($keyInfoItems as $item)
                @php $itemChange = $highlightChanges ? $item['change'] : 'same'; @endphp
                <p @class([
                    'mt-1 text-sm',
                    'text-rose-800 line-through' => $itemChange === 'removed',
                    'text-emerald-900' => $itemChange === 'added',
                    'text-on-surface' => $itemChange === 'same',
                ])>• {!! $item['html'] !!}</p>
            @endforeach
        </div>

        <div @class(['mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-4' => $reviewerStyle])
            @if ($reviewerStyle) x-show="knowledgeOpen" @endif>
            <div class="mb-2 flex items-center gap-2">
                @if ($reviewerStyle)
                    <span class="material-symbols-outlined text-[20px] text-amber-700">stethoscope</span>
                @endif
                <h4 @class(['text-sm font-bold', 'uppercase text-amber-700' => $reviewerStyle, 'text-on-surface' => ! $reviewerStyle])>Kiến thức</h4>
                @if ($highlightChanges && $comparison['attending_tip']['changed'])
                    <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
                @endif
            </div>
            @if ($hasAttendingTip)
                <div class="question-rich-content instructor-image-preview prose prose-sm max-w-none text-sm leading-6 text-on-surface">
                    {!! $attendingHtml !!}
                </div>
                @if ($preserveRawRichText && $comparison['can_compare'] && filled($comparison['attending_tip'][$textKey] ?? null))
                    <span class="sr-only" aria-hidden="true">{!! $comparison['attending_tip'][$textKey] !!}</span>
                @endif
            @endif
        </div>
    </div>

    @foreach ([
        ['title' => 'Bài học', 'field' => 'lessons'],
        ['title' => 'Đối tượng', 'field' => 'professions'],
        ['title' => 'Độ khó', 'field' => 'difficulty', 'scalar' => true],
        ['title' => 'Kỳ thi', 'field' => 'blueprints'],
        ['title' => 'Truy cập', 'field' => 'access', 'scalar' => true],
    ] as $meta)
        @continue(! $highlightChanges && ! ($meta['scalar'] ?? false) && ($comparison[$meta['field']][$chipKey] ?? []) === [])
        <div>
            <div class="mb-2 flex items-center gap-2">
                <h4 class="text-sm font-bold text-on-surface">{{ $meta['title'] }}</h4>
                @if ($highlightChanges && $comparison[$meta['field']]['changed'])
                    <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
                @endif
            </div>
            @if ($meta['scalar'] ?? false)
                <span @class([
                    'inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold',
                    'bg-rose-100 text-rose-800 line-through' => $highlightChanges && $isPublished && $comparison[$meta['field']]['changed'],
                    'bg-emerald-100 text-emerald-900' => $highlightChanges && ! $isPublished && $comparison[$meta['field']]['changed'],
                    'bg-surface-container-high text-on-surface' => ! $highlightChanges || ! $comparison[$meta['field']]['changed'],
                ])>{{ $comparison[$meta['field']][$chipKey] }}</span>
            @else
                <div class="flex flex-wrap gap-2">
                    @forelse ($comparison[$meta['field']][$chipKey] as $item)
                        @php $chipChange = $highlightChanges ? $item['change'] : 'same'; @endphp
                        <span @class([
                            'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold',
                            'bg-rose-100 text-rose-800 line-through' => $chipChange === 'removed',
                            'bg-emerald-100 text-emerald-900' => $chipChange === 'added',
                            'bg-surface-container-high text-on-surface' => $chipChange === 'same',
                        ])>
                            {{ $item['label'] }}
                        </span>
                    @empty
                        <span class="text-sm text-on-surface-variant">{{ $empty }}</span>
                    @endforelse
                </div>
            @endif
        </div>
    @endforeach
</div>
