@props([
    'title' => null,
])

@php
    $navItems = \Modules\Admin\Support\AdminMenu::for(auth()->user());
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">

<head>
    <meta charset="utf-8">
    <x-theme-init />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (Route::has('admin.media.items'))
        <meta name="media-items-url" content="{{ route('admin.media.items') }}">
        <meta name="media-upload-url" content="{{ route('admin.media.store') }}">
        <meta name="media-from-url" content="{{ route('admin.media.from-url') }}">
        <meta name="media-show-url-template" content="{{ url('/admin/media') }}/__ID__">
    @endif
    <title>{{ $title ? $title . ' — Quản trị' : 'Quản trị — ' . config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-surface-container-lowest font-body-md text-on-surface"
    x-data="{
        menu: false,
        accountMenu: false,
        theme: 'light',
        initTheme() {
            this.theme = window.MedlearnTheme?.getStoredTheme?.() ?? 'system';
        },
        async setTheme(value) {
            this.theme = value;
            if (window.MedlearnTheme?.setTheme) {
                this.theme = await window.MedlearnTheme.setTheme(value);
            }
        },
    }"
    x-init="initTheme()"
    @keydown.escape.window="menu = false; accountMenu = false">
    <aside
        class="fixed top-0 left-0 z-50 hidden h-screen w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:flex">
        <div class="mb-6 px-2">
            <a href="{{ route('admin.dashboard') }}" class="block">
                <span class="font-headline-sm text-headline-sm font-extrabold text-primary tracking-tight">{{ config('app.name') }}</span>
                <span class="mt-0.5 block font-label-sm text-label-sm text-on-surface-variant">Quản trị hệ thống</span>
            </a>
        </div>
        <nav class="flex flex-1 flex-col gap-1 overflow-y-auto" aria-label="Menu quản trị">
            @foreach ($navItems as $item)
                @php
                    $active = $item['match'] && request()->routeIs($item['match']);
                    $href = $item['route'] ? route($item['route']) : null;
                @endphp
                @if ($href)
                    <a href="{{ $href }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md transition-colors {{ $active ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' }}">
                        <span class="material-symbols-outlined text-[22px] leading-none">{{ $item['icon'] }}</span>
                        {{ $item['label'] }}
                    </a>
                @else
                    <span
                        class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md text-on-surface-variant/50"
                        title="Sắp có">
                        <span class="material-symbols-outlined text-[22px] leading-none">{{ $item['icon'] }}</span>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        <span class="font-label-sm text-label-sm">Sắp có</span>
                    </span>
                @endif
            @endforeach
        </nav>
        <form method="post" action="{{ route('admin.logout') }}" class="mt-4 border-t border-outline-variant pt-4">
            @csrf
            <button type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-on-surface">
                <span class="material-symbols-outlined text-[22px] leading-none">logout</span>
                Đăng xuất
            </button>
        </form>
    </aside>

    <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-50 bg-black/40 md:hidden"></div>
    <aside x-show="menu" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed top-0 bottom-0 left-0 z-[60] flex w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:hidden"
        @click.stop>
        <div class="mb-4 flex items-center justify-between px-2">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Quản trị</span>
            <button type="button" @click="menu = false"
                class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container-low"
                aria-label="Đóng menu">
                <span class="material-symbols-outlined text-[24px] leading-none">close</span>
            </button>
        </div>
        <nav class="flex flex-1 flex-col gap-1">
            @foreach ($navItems as $item)
                @php $href = $item['route'] ? route($item['route']) : null; @endphp
                @if ($href)
                    <a href="{{ $href }}" @click="menu = false"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[22px] leading-none">{{ $item['icon'] }}</span>
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>
    </aside>

    <header
        class="fixed top-0 right-0 left-0 z-40 flex h-header-height items-center justify-between border-b border-outline-variant bg-surface px-margin-mobile md:left-sidebar-width md:px-margin-desktop">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <button type="button" @click="menu = true"
                class="hidden size-10 shrink-0 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container-low max-md:inline-flex"
                :aria-expanded="menu" aria-label="Mở menu">
                <span class="material-symbols-outlined text-[24px] leading-none">menu</span>
            </button>
            <h1 class="truncate font-headline-sm text-headline-sm text-on-surface">{{ $title ?? 'Tổng quan' }}</h1>
        </div>
        <div class="ml-2 flex shrink-0 items-center gap-3">
            <div class="relative" @click.outside="accountMenu = false">
                <button type="button" @click="accountMenu = !accountMenu"
                    class="flex items-center gap-3 rounded-xl p-1.5 text-left transition-colors hover:bg-surface-container-low focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    :aria-expanded="accountMenu" aria-haspopup="dialog" aria-label="Mở menu tài khoản">
                <div class="hidden text-right sm:block">
                    <p class="font-label-md text-label-md text-on-surface">{{ auth()->user()->name }}</p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Quản trị viên</p>
                </div>
                <span
                    class="flex size-10 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-primary-container font-bold text-body-md text-on-primary-container">
                    @if (auth()->user()->avatarUrl())
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="size-full object-cover">
                    @else
                        {{ auth()->user()->avatarInitial() }}
                    @endif
                </span>
                </button>

                <section x-show="accountMenu" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="translate-y-1 opacity-0"
                    class="fixed top-header-height right-0 z-50 max-h-[calc(100vh-var(--header-height))] w-full overflow-y-auto border-l border-b border-outline-variant bg-surface shadow-xl sm:absolute sm:top-[calc(100%+0.5rem)] sm:w-[320px] sm:rounded-[10px] sm:border"
                    role="dialog" aria-label="Tùy chọn tài khoản">
                    <div class="space-y-3 bg-primary-container/40 p-4">
                        <div>
                            <p class="font-title-md text-title-md font-bold text-on-surface">{{ auth()->user()->name }}</p>
                            <p class="font-body-md text-body-md text-on-surface-variant">Quản trị viên</p>
                        </div>

                        <a href="{{ route('profile.show') }}" @click="accountMenu = false"
                            class="block w-full rounded-lg bg-primary px-4 py-2.5 text-center font-label-md text-label-md font-bold text-on-primary transition-opacity hover:opacity-90">
                            Quản lý tài khoản
                        </a>
                    </div>

                    <div class="p-4">
                        <fieldset>
                            <legend class="font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Giao diện</legend>
                            <div class="mt-2 grid grid-cols-3 overflow-hidden rounded-lg border border-outline-variant">
                                <template x-for="option in [{ value: 'light', label: 'Sáng' }, { value: 'dark', label: 'Tối' }, { value: 'system', label: 'Hệ thống' }]" :key="option.value">
                                    <button type="button" @click="setTheme(option.value)" x-text="option.label"
                                        class="border-r border-outline-variant px-2 py-2.5 font-label-md text-label-md font-bold last:border-r-0"
                                        :class="theme === option.value ? 'bg-primary-container text-on-primary-container' : 'bg-surface text-on-surface-variant'"></button>
                                </template>
                            </div>
                        </fieldset>
                    </div>

                    <form action="{{ route('admin.logout') }}" method="post" class="border-t border-outline-variant p-3">
                        @csrf
                        <button type="submit"
                            class="w-full rounded-lg px-4 py-2.5 font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase transition-colors hover:bg-surface-container-low">
                            Đăng xuất
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </header>

    <main class="min-h-screen bg-surface-container-lowest pt-header-height md:ml-sidebar-width">
        <div class="p-margin-mobile md:p-margin-desktop">
            {{ $slot }}
        </div>
    </main>

    @include('media::admin.picker')
    @livewireScriptConfig
</body>

</html>
