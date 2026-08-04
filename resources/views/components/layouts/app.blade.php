@props(['title' => null])

@php
    // Dashboard, Q-Bank, StudyPlan, Flashcards are wired; the rest land as modules ship.
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard'],
        ['label' => 'Ngân hàng câu hỏi', 'icon' => 'quiz', 'route' => 'qbank.index', 'match' => 'qbank.*'],
        ['label' => 'Thư viện', 'icon' => 'library_books', 'route' => null],
        ['label' => 'Flashcards', 'icon' => 'style', 'route' => 'flashcards.index', 'match' => 'flashcards.*'],
        ['label' => 'Kế hoạch học tập', 'icon' => 'event_note', 'route' => 'study-plan.index', 'match' => 'study-plan.*'],
        ['label' => 'Phân tích', 'icon' => 'analytics', 'route' => null],
        ['label' => 'Kỳ thi', 'icon' => 'assignment', 'route' => null],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-surface font-body-md text-on-surface" x-data="{ menu: false }"
    @keydown.escape.window="menu = false">
    <!-- SideNavBar (desktop) -->
    <aside
        class="fixed top-0 left-0 z-50 hidden h-screen w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:flex">
        @include('components.layouts.partials.app-sidebar', ['navItems' => $navItems])
    </aside>

    <!-- Mobile sidebar drawer -->
    <div x-show="menu" x-cloak @click="menu = false"
        class="fixed inset-0 z-50 bg-black/40 md:hidden"></div>
    <aside x-show="menu" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed top-0 bottom-0 left-0 z-[60] flex w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:hidden"
        @click.stop>
        <div class="mb-4 flex items-center justify-between px-2">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Menu</span>
            <button type="button" @click="menu = false"
                class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container-low"
                aria-label="Đóng menu">
                <span class="material-symbols-outlined text-[24px] leading-none">close</span>
            </button>
        </div>
        @include('components.layouts.partials.app-sidebar', ['navItems' => $navItems, 'closeOnNavigate' => true])
    </aside>

    <!-- TopAppBar -->
    <header
        class="fixed top-0 right-0 left-0 z-40 flex h-header-height items-center justify-between border-b border-outline-variant bg-surface px-margin-mobile md:left-sidebar-width md:px-margin-desktop">
        <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-4">
            <button type="button" @click="menu = true"
                class="hidden size-10 shrink-0 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container-low max-md:inline-flex"
                :aria-expanded="menu" aria-label="Mở menu">
                <span class="material-symbols-outlined text-[24px] leading-none">menu</span>
            </button>

            <div class="relative w-full max-w-md">
                <span
                    class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant">search</span>
                <input type="search" placeholder="Tìm kiếm bài học, câu hỏi..." aria-label="Tìm kiếm"
                    class="w-full rounded-lg border-none bg-surface-container-low py-2 pr-4 pl-10 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
            </div>
        </div>

        <div class="ml-2 flex shrink-0 items-center gap-6">
            <div class="flex items-center gap-3">
                <button type="button"
                    class="group relative cursor-pointer rounded-full p-2 transition-colors hover:bg-surface-container"
                    aria-label="Tin nhắn">
                    <span
                        class="material-symbols-outlined text-[24px] leading-none text-on-surface-variant group-hover:text-primary">mail</span>
                </button>
                <button type="button"
                    class="group relative cursor-pointer rounded-full p-2 transition-colors hover:bg-surface-container"
                    aria-label="Thông báo">
                    <span
                        class="material-symbols-outlined text-[24px] leading-none text-on-surface-variant group-hover:text-primary">notifications</span>
                    <span class="absolute top-2 right-2 size-2 rounded-full border-2 border-surface bg-error"></span>
                </button>
            </div>

            <div class="hidden h-8 w-px bg-outline-variant sm:block"></div>

            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="font-label-md text-label-md text-on-surface">{{ auth()->user()->name }}</p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">
                        {{ auth()->user()->getRoleNames()->first() === 'student' ? 'Học viên' : 'Nhân sự' }}
                    </p>
                </div>
                <span
                    class="flex size-10 items-center justify-center rounded-full border border-outline-variant bg-primary-container font-bold text-body-md text-on-primary-container">
                    {{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}
                </span>
            </div>
        </div>
    </header>

    <!-- Content Canvas -->
    <main class="min-h-screen bg-surface-container-lowest pt-header-height md:ml-sidebar-width">
        {{ $slot }}
    </main>

    @livewireScriptConfig
</body>

</html>
