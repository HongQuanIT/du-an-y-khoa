@props([
    'id',
    'title',
    'subtitle' => null,
    'fullWidth' => false,
])

<article @class([
    'rounded-xl border border-outline-variant bg-surface p-5',
    'lg:col-span-2' => $fullWidth,
])>
    <div class="mb-4">
        <h3 class="font-headline-sm text-headline-sm text-on-surface">{{ $title }}</h3>
        @if ($subtitle)
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="relative h-64 w-full">
        <canvas id="{{ $id }}" aria-label="{{ $title }}"></canvas>
    </div>
</article>
