@props([
    'label',
    'value' => '—',
    'hint' => null,
    'icon' => 'insights',
    'delta' => null,
    'deltaSuffix' => '%',
    'deltaMode' => 'percent',
    'href' => null,
    'severity' => null,
])

@php
    $isLink = filled($href);
    $tag = $isLink ? 'a' : 'div';
    $severityClasses = match ($severity) {
        'critical' => 'border-error/40 bg-error/5',
        'warning' => 'border-amber-500/40 bg-amber-500/5',
        default => 'border-outline-variant bg-surface',
    };

    $deltaValue = is_numeric($delta) ? (float) $delta : null;
    $deltaPositive = $deltaValue !== null && $deltaValue > 0;
    $deltaNegative = $deltaValue !== null && $deltaValue < 0;
    $deltaNeutral = $deltaValue !== null && $deltaValue === 0.0;

    $deltaLabel = match (true) {
        $deltaValue === null => null,
        $deltaMode === 'absolute' => ($deltaPositive ? '+' : '').number_format($deltaValue, $deltaSuffix === '%' ? 1 : 0, ',', '.').$deltaSuffix,
        default => ($deltaPositive ? '+' : '').rtrim(rtrim(number_format($deltaValue, 1, ',', '.'), '0'), ',').$deltaSuffix,
    };
@endphp

<{{ $tag }}
    @if ($isLink) href="{{ $href }}" @endif
    {{ $attributes->class([
        'group block rounded-xl border p-4 transition sm:p-5',
        $severityClasses,
        'hover:border-primary/40 hover:shadow-sm' => $isLink,
    ]) }}>
    <div class="mb-3 flex items-start justify-between gap-2">
        <p class="font-label-md text-label-md text-on-surface-variant">{{ $label }}</p>
        <span class="material-symbols-outlined text-[22px] text-on-surface-variant/70">{{ $icon }}</span>
    </div>

    <div class="flex flex-wrap items-end gap-2">
        <p class="font-headline-sm text-headline-sm text-on-surface">{{ $value }}</p>

        @if ($deltaLabel !== null)
            <span @class([
                'inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 font-label-sm text-label-sm',
                'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' => $deltaPositive,
                'bg-red-500/10 text-red-700 dark:text-red-400' => $deltaNegative,
                'bg-surface-container-high text-on-surface-variant' => $deltaNeutral,
            ])>
                @if ($deltaPositive)
                    <span class="material-symbols-outlined text-[14px]">arrow_upward</span>
                @elseif ($deltaNegative)
                    <span class="material-symbols-outlined text-[14px]">arrow_downward</span>
                @endif
                {{ $deltaLabel }}
            </span>
        @endif
    </div>

    @if ($hint)
        <p class="mt-1 font-label-sm text-label-sm text-on-surface-variant">{{ $hint }}</p>
    @endif

    @if ($isLink)
        <p class="mt-2 font-label-sm text-label-sm text-primary opacity-0 transition group-hover:opacity-100">
            Xem chi tiết →
        </p>
    @endif
</{{ $tag }}>
