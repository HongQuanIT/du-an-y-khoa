@php
    $isPublished = $side === 'published';
    $textKey = $isPublished ? 'published_html' : 'proposed_html';
    $chipKey = $isPublished ? 'published' : 'proposed';
    $keyInfoItems = $comparison['key_info'][$chipKey] ?? [];
    $preserveRichText = (bool) ($preserveRichText ?? false);
    $preserveRawRichText = $preserveRichText;
    $attendingHtml = $preserveRawRichText
        ? ($comparison['raw_attending_tip'][$textKey] ?? '')
        : ($comparison['attending_tip'][$textKey] ?? '');
    $hasAttendingTip = filled(strip_tags((string) $attendingHtml));
    $stemHtml = $preserveRawRichText
        ? ($comparison['raw_stem'][$textKey] ?? '')
        : ($comparison['stem'][$textKey] ?? '');
@endphp

<div class="space-y-5">
    <div>
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Câu hỏi</h4>
            @if ($comparison['stem']['changed'])
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        <div class="question-rich-content prose prose-sm max-w-none text-sm leading-6 text-on-surface">
            @if (filled($stemHtml))
                {!! $stemHtml !!}
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
                @if ($comparison['stem_image']['changed'])
                    <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
                @endif
            </div>
            @php $imageUrl = $comparison['stem_image'][$isPublished ? 'published_url' : 'proposed_url']; @endphp
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $isPublished ? 'Hình bản đang xuất bản' : 'Hình bản cần duyệt' }}"
                    class="max-h-72 rounded-xl border {{ $comparison['stem_image']['changed'] ? ($isPublished ? 'border-rose-300' : 'border-emerald-300') : 'border-outline-variant' }} object-contain">
            @else
                <p class="rounded-xl border border-dashed {{ $isPublished ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-emerald-300 bg-emerald-50 text-emerald-900' }} px-3 py-2 text-sm">
                    {{ $isPublished ? 'Hình đã bị gỡ ở bản gửi duyệt.' : 'Hình mới được thêm.' }}
                </p>
            @endif
        </div>
    @endif

    <div>
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Bài học</h4>
            @if ($comparison['lessons']['changed'])
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @forelse ($comparison['lessons'][$chipKey] as $lesson)
                <span @class([
                    'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold',
                    'bg-rose-100 text-rose-800 line-through' => $lesson['change'] === 'removed',
                    'bg-emerald-100 text-emerald-900' => $lesson['change'] === 'added',
                    'bg-surface-container-high text-on-surface' => $lesson['change'] === 'same',
                ])>
                    {{ $lesson['label'] }}
                </span>
            @empty
                <span class="text-sm text-on-surface-variant">{{ $empty }}</span>
            @endforelse
        </div>
    </div>

    <div>
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Độ khó</h4>
            @if ($comparison['difficulty']['changed'])
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        <span @class([
            'inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold',
            'bg-rose-100 text-rose-800 line-through' => $isPublished && $comparison['difficulty']['changed'],
            'bg-emerald-100 text-emerald-900' => ! $isPublished && $comparison['difficulty']['changed'],
            'bg-surface-container-high text-on-surface' => ! $comparison['difficulty']['changed'],
        ])>{{ $comparison['difficulty'][$chipKey] }}</span>
    </div>

    <div>
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Đáp án</h4>
            @if (collect($comparison['options'])->contains(fn (array $row): bool => $row['change'] !== 'same'))
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        <div class="space-y-2">
            @foreach ($comparison['options'] as $option)
                @php
                    $sideOption = $option[$chipKey];
                    $changeLabel = match ($option['change']) {
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
                        'rounded-xl border px-3 py-2 text-sm',
                        'border-rose-300 bg-rose-50 text-rose-900' => $option['change'] === 'removed',
                        'border-emerald-300 bg-emerald-50 text-emerald-950' => $option['change'] === 'added',
                        'border-amber-300 bg-amber-50' => $option['change'] === 'modified',
                        'border-emerald-300 bg-emerald-50 text-emerald-900' => $option['change'] === 'same' && $sideOption['is_correct'],
                        'border-outline-variant bg-surface-container-lowest' => $option['change'] === 'same' && ! $sideOption['is_correct'],
                    ])>
                        <div class="flex items-start gap-2">
                            @if ($changeLabel)
                                <span @class([
                                    'mt-0.5 shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-bold',
                                    'bg-rose-200 text-rose-800' => $option['change'] === 'removed',
                                    'bg-amber-200 text-amber-900' => $option['change'] === 'modified',
                                    'bg-emerald-200 text-emerald-900' => $option['change'] === 'added',
                                ])>{{ $changeLabel }}</span>
                            @endif
                            <span class="shrink-0 font-bold">{{ $sideOption['label'] }}.</span>
                            <div class="min-w-0 flex-1 prose prose-sm max-w-none text-sm">
                                {!! filled($sideOption['content_html']) ? $sideOption['content_html'] : e($empty) !!}
                            </div>
                            @if ($sideOption['is_correct'])
                                <span @class([
                                    'shrink-0 text-xs font-bold',
                                    'rounded bg-emerald-200 px-1.5 py-0.5 text-emerald-900' => $option['correct_changed'] && ! $isPublished,
                                    'rounded bg-rose-200 px-1.5 py-0.5 text-rose-800 line-through' => $option['correct_changed'] && $isPublished,
                                ])>Đáp án đúng</span>
                            @elseif ($option['correct_changed'])
                                <span class="shrink-0 text-xs font-bold {{ $isPublished ? 'text-rose-700' : 'text-emerald-800' }}">
                                    {{ $isPublished ? 'Không còn là đáp án đúng' : 'Được chọn làm đáp án đúng' }}
                                </span>
                            @endif
                        </div>
                        @if (filled($sideOption['explanation_html']))
                            <div class="mt-1 border-t border-current/10 pt-1 text-xs leading-5 text-on-surface-variant">
                                <span class="font-semibold">Giải thích:</span>
                                <div class="prose prose-sm mt-0.5 max-w-none">
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
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Gợi ý</h4>
            @if ($comparison['key_info']['changed'])
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        @foreach ($keyInfoItems as $item)
            <p @class([
                'mt-1 text-sm',
                'text-rose-800 line-through' => $item['change'] === 'removed',
                'text-emerald-900' => $item['change'] === 'added',
                'text-on-surface' => $item['change'] === 'same',
            ])>
                • {!! $item['html'] !!}
            </p>
        @endforeach
    </div>

    <div>
        <div class="mb-2 flex items-center gap-2">
            <h4 class="text-sm font-bold text-on-surface">Kiến thức</h4>
            @if ($comparison['attending_tip']['changed'])
                <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sửa</span>
            @endif
        </div>
        @if ($hasAttendingTip)
            <div class="question-rich-content prose prose-sm max-w-none text-sm leading-6 text-on-surface">
                {!! $attendingHtml !!}
            </div>
            @if ($preserveRawRichText && $comparison['can_compare'] && filled($comparison['attending_tip'][$textKey] ?? null))
                <span class="sr-only" aria-hidden="true">{!! $comparison['attending_tip'][$textKey] !!}</span>
            @endif
        @endif
    </div>
</div>
