@props([
    'label',
    'value' => '—',
    'hint' => null,
    'icon' => 'insights',
])

<div class="rounded-xl border border-outline-variant bg-surface p-4 sm:p-5">
    <div class="mb-3 flex items-start justify-between gap-2">
        <p class="font-label-md text-label-md text-on-surface-variant">{{ $label }}</p>
        <span class="material-symbols-outlined text-[22px] text-on-surface-variant/70">{{ $icon }}</span>
    </div>
    <p class="font-headline-sm text-headline-sm text-on-surface">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 font-label-sm text-label-sm text-on-surface-variant">{{ $hint }}</p>
    @endif
</div>
