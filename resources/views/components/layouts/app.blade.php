@props(['title' => null])

@php
    // Only Dashboard is wired so far; the rest land here as their modules ship.
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard'],
        ['label' => 'Ngân hàng câu hỏi', 'icon' => 'quiz', 'route' => null],
        ['label' => 'Thư viện', 'icon' => 'library_books', 'route' => null],
        ['label' => 'Flashcards', 'icon' => 'style', 'route' => null],
        ['label' => 'Kế hoạch học tập', 'icon' => 'event_note', 'route' => null],
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

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-surface text-on-surface font-body-md" x-data="{ menu: false }">
    <!-- SideNavBar -->
    <aside
        class="fixed left-0 top-0 z-50 hidden h-screen w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:flex">
        <a href="{{ route('dashboard') }}" class="mb-8 flex items-center gap-3 px-2">
            <span class="material-symbols-outlined text-3xl text-primary"
                style="font-variation-settings: 'FILL' 1;">medical_services</span>
            <span class="flex flex-col">
                <span class="font-headline-md text-headline-md font-bold leading-tight text-primary">
                    {{ config('app.name') }}
                </span>
                <span class="text-label-sm font-label-sm text-on-surface-variant">Học thuật Y khoa</span>
            </span>
        </a>

        <nav class="flex-1 space-y-1">
            @foreach ($navItems as $item)
                @php $active = $item['route'] && request()->routeIs($item['route']); @endphp
                <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                    @class([
                        'group flex items-center gap-3 rounded-lg px-3 transition-colors',
                        'bg-primary/10 py-2 font-label-md text-label-md font-semibold text-primary' => $active,
                        'py-2.5 text-on-surface-variant hover:bg-surface-container-low' => !$active,
                    ])>
                    <span class="material-symbols-outlined group-hover:text-primary"
                        @if ($active) style="font-variation-settings: 'FILL' 1;" @endif>{{ $item['icon'] }}</span>
                    <span class="font-body-md text-body-md">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="space-y-1 border-t border-outline-variant pt-4">
            <a href="{{ route('landing.pricing') }}"
                class="premium-gradient flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-body-sm font-semibold text-white shadow-md transition-opacity hover:opacity-90">
                <span class="material-symbols-outlined text-body-sm">stars</span>
                Nâng cấp tài khoản
            </a>
            <a href="#"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-on-surface-variant transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-body-md text-body-md">Cài đặt</span>
            </a>
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-on-surface-variant transition-colors hover:bg-surface-container-low">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="font-body-md text-body-md">Đăng xuất</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- TopAppBar -->
    <header
        class="fixed top-0 right-0 left-0 z-40 flex h-header-height items-center justify-between border-b border-outline-variant bg-surface px-margin-mobile md:left-sidebar-width md:px-margin-desktop">
        <div class="flex flex-1 items-center gap-4">
            <button type="button" @click="menu = !menu"
                class="material-symbols-outlined rounded-lg p-2 text-on-surface transition-colors hover:bg-surface-container-low md:hidden"
                :aria-expanded="menu" aria-label="Mở menu">menu</button>

            <div class="relative w-full max-w-md">
                <span
                    class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant">search</span>
                <input type="search" placeholder="Tìm kiếm bài học, câu hỏi..." aria-label="Tìm kiếm"
                    class="w-full rounded-lg border-none bg-surface-container-low py-2 pr-4 pl-10 text-body-sm font-body-sm focus:ring-2 focus:ring-primary">
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="hidden items-center gap-3 sm:flex">
                <button type="button"
                    class="group cursor-pointer rounded-full p-2 transition-colors hover:bg-surface-container"
                    aria-label="Tin nhắn">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">mail</span>
                </button>
                <button type="button"
                    class="group relative cursor-pointer rounded-full p-2 transition-colors hover:bg-surface-container"
                    aria-label="Thông báo">
                    <span
                        class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">notifications</span>
                    <span class="absolute top-2 right-2 size-2 rounded-full border-2 border-surface bg-error"></span>
                </button>
            </div>

            <div class="hidden h-8 w-px bg-outline-variant sm:block"></div>

            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="font-label-md text-label-md text-on-surface">{{ auth()->user()->name }}</p>
                    <p class="text-label-sm font-label-sm text-on-surface-variant">
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

    <!-- Mobile drawer -->
    <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-40 bg-black/40 md:hidden"></div>
    <nav x-show="menu" x-cloak x-transition.origin.left
        class="fixed top-header-height bottom-0 left-0 z-50 w-64 space-y-1 overflow-y-auto border-r border-outline-variant bg-surface p-4 md:hidden">
        @foreach ($navItems as $item)
            <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                @class([
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 font-body-md text-body-md',
                    'bg-primary/10 font-semibold text-primary' => $item['route'] && request()->routeIs($item['route']),
                    'text-on-surface-variant' => !($item['route'] && request()->routeIs($item['route'])),
                ])>
                <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach
        <form action="{{ route('logout') }}" method="post" class="border-t border-outline-variant pt-3">
            @csrf
            <button type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-body-md text-body-md text-on-surface-variant">
                <span class="material-symbols-outlined">logout</span>
                Đăng xuất
            </button>
        </form>
    </nav>

    <!-- Content Canvas -->
    <main class="min-h-screen bg-surface-container-lowest pt-header-height md:ml-sidebar-width">
        {{ $slot }}
    </main>

    @livewireScriptConfig
</body>

</html>
