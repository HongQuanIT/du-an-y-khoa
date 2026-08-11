@props([
    'user',
    'size' => 'md',
])

@php
    $sizeClasses = match ($size) {
        'xs' => 'size-11 text-body-lg',
        'sm' => 'size-10 text-body-md',
        'lg' => 'size-24 text-headline-lg md:size-32',
        default => 'size-16 text-title-md',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-primary-container font-bold text-on-primary-container',
    $sizeClasses,
]) }}>
    @if ($user->avatarUrl())
        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="size-full object-cover">
    @else
        {{ $user->avatarInitial() }}
    @endif
</span>
