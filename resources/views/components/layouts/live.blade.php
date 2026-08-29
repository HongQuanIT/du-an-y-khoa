@props(['title' => null, 'classroomTitle' => null, 'sessionTitle' => null, 'isLive' => false, 'viewerCount' => null, 'exitUrl' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">

<head>
    <meta charset="utf-8">
    <x-theme-init />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-activity-heartbeat />
    <title>{{ $title ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="flex h-[100dvh] flex-col overflow-hidden bg-on-surface text-white">
    <header class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-4 py-2.5 md:px-6">
        <div class="flex min-w-0 items-center gap-3">
            @if ($exitUrl)
                <a href="{{ $exitUrl }}"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10"
                    aria-label="Rời phòng">
                    <span class="material-symbols-outlined text-[22px]">arrow_back</span>
                </a>
            @endif
            <div class="min-w-0">
                @if ($classroomTitle)
                    <p class="truncate text-xs text-white/60">{{ $classroomTitle }}</p>
                @endif
                <h1 class="truncate font-headline-sm text-headline-sm">{{ $sessionTitle ?? $title }}</h1>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if ($isLive)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-error px-2.5 py-1 text-xs font-bold uppercase tracking-wide">
                    <span class="size-1.5 animate-pulse rounded-full bg-white"></span>
                    Live
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-xs text-white/90"
                    title="Số người trong phòng">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">visibility</span>
                    <span data-live-viewer-count-num>{{ $viewerCount ?? 1 }}</span>
                </span>
            @elseif ($viewerCount !== null)
                <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-xs"
                    title="Số người trong phòng">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">visibility</span>
                    <span data-live-viewer-count-num>{{ $viewerCount }}</span>
                </span>
            @endif
        </div>
    </header>

    <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
        {{ $slot }}
    </main>

    @livewireScriptConfig
</body>

</html>
