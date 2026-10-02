@php
    $items = \Modules\QuestionBank\Support\ReviewerChecklist::definitions();
    $interactive = (bool) ($interactive ?? false);
    $selected = array_values((array) ($selected ?? []));
@endphp

<section aria-labelledby="review-checklist-title">
    <div class="flex items-center justify-between gap-2 border-b border-outline-variant px-4 py-2.5">
        <h2 id="review-checklist-title" class="text-sm font-semibold text-on-surface">Checklist kiểm tra</h2>
        @if ($interactive)
            <span class="text-[11px] font-semibold text-on-surface-variant" x-show="flag !== 'red'">{{ count($items) }} mục</span>
            <span class="text-[11px] font-semibold text-rose-700" x-show="flag === 'red'" x-cloak
                x-text="failed.length ? failed.length + ' không đạt' : 'Đánh dấu mục không đạt'"></span>
        @elseif ($selected !== [])
            <span class="text-[11px] font-semibold text-rose-700">{{ count($selected) }} mục không đạt</span>
        @else
            <span class="text-[11px] font-semibold text-on-surface-variant">{{ count($items) }} mục</span>
        @endif
    </div>

    <ol>
        @foreach ($items as $index => $item)
            @php $isFailed = in_array($item['key'], $selected, true); @endphp
            <li @class([
                'border-b border-outline-variant/60 last:border-b-0',
                'bg-rose-50' => ! $interactive && $isFailed,
            ])
                @if ($interactive) :class="flag === 'red' && failed.includes('{{ $item['key'] }}') ? 'bg-rose-50' : 'hover:bg-surface-container-low'" @endif>
                <label class="flex items-start gap-3 px-4 py-2.5"
                    @if ($interactive) :class="flag === 'red' ? 'cursor-pointer' : ''" @endif>
                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center">
                        @if ($interactive)
                            <span class="col-start-1 row-start-1 flex size-6 items-center justify-center rounded-md bg-surface-container-high text-[11px] font-semibold tabular-nums text-on-surface-variant"
                                x-show="flag !== 'red'">{{ $index + 1 }}</span>
                            <input type="checkbox" name="failed_checks[]" value="{{ $item['key'] }}"
                                class="peer col-start-1 row-start-1 size-6 appearance-none rounded-md border border-outline-variant bg-white checked:border-rose-600 checked:bg-rose-600"
                                x-show="flag === 'red'" x-cloak
                                x-model="failed"
                                :disabled="flag !== 'red'"
                                aria-label="{{ $item['detail'] }}">
                            <svg class="pointer-events-none col-start-1 row-start-1 size-3.5 text-white opacity-0 peer-checked:opacity-100" viewBox="0 0 16 16" fill="none" aria-hidden="true"
                                x-show="flag === 'red'" x-cloak>
                                <path d="M3.5 8.2 6.4 11l6.1-6.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @elseif ($isFailed)
                            <span class="flex size-6 items-center justify-center rounded-md bg-rose-600 text-white" aria-hidden="true">
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none">
                                    <path d="M3.5 8.2 6.4 11l6.1-6.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        @else
                            <span class="flex size-6 items-center justify-center rounded-md bg-surface-container-high text-[11px] font-semibold tabular-nums text-on-surface-variant">{{ $index + 1 }}</span>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1 pt-0.5 text-sm leading-5 text-on-surface">{{ $item['detail'] }}</span>
                </label>
            </li>
        @endforeach
    </ol>
</section>
