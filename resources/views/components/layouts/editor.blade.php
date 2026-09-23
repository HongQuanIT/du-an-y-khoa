@props(['title' => null])

@php
    $navItems = [
        ['label' => 'Tổng quan', 'icon' => 'dashboard', 'route' => 'editor.dashboard', 'match' => 'editor.dashboard'],
        ['label' => 'Câu hỏi của tôi', 'icon' => 'quiz', 'route' => 'editor.questions.index', 'match' => 'editor.questions.*'],
        ['label' => 'Phân loại', 'icon' => 'category', 'route' => 'editor.taxonomy.index', 'match' => 'editor.taxonomy.*', 'permission' => 'taxonomy.view'],
        ['label' => 'CMS', 'icon' => 'article', 'route' => 'editor.cms.pages.index', 'match' => 'editor.cms.*', 'permission' => 'cms.view'],
        ['label' => 'Media', 'icon' => 'perm_media', 'route' => 'editor.media.index', 'match' => 'editor.media.*', 'permission' => 'media.view'],
    ];
    $navItems = array_values(array_filter($navItems, static fn (array $item): bool => ! isset($item['permission']) || auth()->user()?->can($item['permission'] ?? '') === true));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">
<head>
    <meta charset="utf-8">
    <x-theme-init />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <x-activity-heartbeat />
    <title>{{ $title ? $title.' — Biên tập' : 'Biên tập — '.config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-container-lowest font-body-md text-on-surface"
    x-data="{ menu: false, accountMenu: false, theme: 'light', initTheme() { this.theme = window.MedlearnTheme?.getStoredTheme?.() ?? 'system'; }, async setTheme(value) { this.theme = value; if (window.MedlearnTheme?.setTheme) this.theme = await window.MedlearnTheme.setTheme(value); } }"
    x-init="initTheme()" @keydown.escape.window="menu = false; accountMenu = false">
    <aside class="fixed top-0 left-0 z-50 hidden h-screen w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:flex">
        <div class="mb-6 px-2">
            <a href="{{ route('editor.dashboard') }}" class="block">
                <span class="font-headline-sm text-headline-sm font-extrabold tracking-tight text-primary">{{ config('app.name') }}</span>
                <span class="mt-0.5 block font-label-sm text-label-sm text-on-surface-variant">Cổng biên tập nội dung</span>
            </a>
        </div>
        <nav class="flex flex-1 flex-col gap-1" aria-label="Menu biên tập viên">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md transition-colors', 'bg-primary-container text-on-primary-container' => request()->routeIs($item['match']), 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' => ! request()->routeIs($item['match'])])>
                    <span class="material-symbols-outlined text-[22px] leading-none">{{ $item['icon'] }}</span>{{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <form method="post" action="{{ route('editor.logout') }}" class="mt-4 border-t border-outline-variant pt-4">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-on-surface"><span class="material-symbols-outlined text-[22px]">logout</span>Đăng xuất</button>
        </form>
    </aside>

    <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-50 bg-black/40 md:hidden"></div>
    <aside x-show="menu" x-cloak class="fixed top-0 bottom-0 left-0 z-[60] flex w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:hidden">
        <div class="mb-4 flex items-center justify-between px-2"><span class="font-label-md text-label-md font-semibold text-on-surface-variant">Biên tập</span><button type="button" @click="menu = false" class="inline-flex size-10 items-center justify-center rounded-lg" aria-label="Đóng menu"><span class="material-symbols-outlined">close</span></button></div>
        <nav class="flex flex-1 flex-col gap-1" aria-label="Menu biên tập viên di động">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" @click="menu = false" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low"><span class="material-symbols-outlined text-[22px]">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </aside>

    <header class="fixed top-0 right-0 left-0 z-40 flex h-header-height items-center justify-between border-b border-outline-variant bg-surface px-margin-mobile md:left-sidebar-width md:px-margin-desktop">
        <div class="flex min-w-0 flex-1 items-center gap-2"><button type="button" @click="menu = true" class="hidden size-10 shrink-0 items-center justify-center rounded-lg max-md:inline-flex" :aria-expanded="menu" aria-label="Mở menu"><span class="material-symbols-outlined">menu</span></button><h1 class="truncate font-headline-sm text-headline-sm text-on-surface">{{ $title ?? 'Tổng quan' }}</h1></div>
        <div class="relative ml-2" @click.outside="accountMenu = false">
            <button type="button" @click="accountMenu = !accountMenu" class="flex items-center gap-3 rounded-xl p-1.5 text-left hover:bg-surface-container-low" :aria-expanded="accountMenu" aria-haspopup="dialog" aria-label="Mở menu tài khoản">
                <span class="hidden text-right sm:block"><span class="block font-label-md text-label-md text-on-surface">{{ auth()->user()->name }}</span><span class="block font-label-sm text-label-sm text-on-surface-variant">Biên tập viên</span></span>
                <span class="flex size-10 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-primary-container font-bold text-body-md text-on-primary-container">{{ auth()->user()->avatarInitial() }}</span>
            </button>
            <section x-show="accountMenu" x-cloak class="absolute top-[calc(100%+0.5rem)] right-0 z-50 w-[min(100vw-2rem,280px)] overflow-hidden rounded-[10px] border border-outline-variant bg-surface shadow-xl" role="dialog" aria-label="Tùy chọn tài khoản">
                @can('profile.view')<a href="{{ route('profile.show') }}" class="block px-4 py-3 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">Quản lý tài khoản</a>@endcan
                <form action="{{ route('editor.logout') }}" method="post" class="border-t border-outline-variant p-3">@csrf<button type="submit" class="w-full rounded-lg px-4 py-2.5 font-label-md text-label-md font-bold text-on-surface-variant uppercase hover:bg-surface-container-low">Đăng xuất</button></form>
            </section>
        </div>
    </header>
    <main class="min-h-screen bg-surface-container-lowest pt-header-height md:ml-sidebar-width"><div class="p-margin-mobile md:p-margin-desktop">{{ $slot }}</div></main>
    @stack('scripts')
</body>
</html>
