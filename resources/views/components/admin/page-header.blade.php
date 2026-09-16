@props([
    'title',
    'description' => null,
])

<div class="mb-6 flex flex-col gap-1">
    <h1 class="font-headline-md text-headline-md text-on-surface">{{ $title }}</h1>
    @if ($description)
        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-2 flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
