@props([
    'title' => null,
    'description' => null,
])

@php
    $navItems = [
        [
            'label' => 'Tổng quan',
            'icon' => 'dashboard',
            'route' => 'partner.dashboard',
            'match' => 'partner.dashboard',
        ],
        [
            'label' => 'Mã mời',
            'icon' => 'link',
            'route' => 'partner.codes.index',
            'match' => 'partner.codes.*',
        ],
        [
            'label' => 'Người được mời',
            'icon' => 'group',
            'route' => 'partner.referrals.index',
            'match' => 'partner.referrals.*',
        ],
        [
            'label' => 'Hoa hồng',
            'icon' => 'payments',
            'route' => 'partner.commissions.index',
            'match' => 'partner.commissions.*',
        ],
        [
            'label' => 'Chi trả',
            'icon' => 'account_balance_wallet',
            'route' => 'partner.payouts.index',
            'match' => 'partner.payouts.*',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">

<head>
    <meta charset="utf-8">
    <x-theme-init />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-activity-heartbeat />
    <meta name="robots" content="noindex, nofollow">
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <title>{{ $title ? $title . ' — Cộng tác viên' : 'Cộng tác viên — ' . config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
            <a href="{{ route('partner.dashboard') }}" class="block">
                <span class="font-headline-sm text-headline-sm font-extrabold text-primary tracking-tight">{{ config('app.name') }}</span>
                <span class="mt-0.5 block font-label-sm text-label-sm text-on-surface-variant">Cổng cộng tác viên</span>
            </a>
        </div>
        <nav class="flex flex-1 flex-col gap-1 overflow-y-auto" aria-label="Menu cộng tác viên">
            @foreach ($navItems as $item)
                @php
                    $active = $item['match'] && request()->routeIs($item['match']);
                    $href = route($item['route']);
                @endphp
                <a href="{{ $href }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md transition-colors {{ $active ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-[22px] leading-none">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-50 bg-black/40 md:hidden"></div>
    <aside x-show="menu" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed top-0 bottom-0 left-0 z-[60] flex w-sidebar-width flex-col border-r border-outline-variant bg-surface p-4 md:hidden"
        @click.stop>
        <div class="mb-4 flex items-center justify-between px-2">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Cộng tác viên</span>
            <button type="button" @click="menu = false"
                class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container-low"
                aria-label="Đóng menu">
                <span class="material-symbols-outlined text-[24px] leading-none">close</span>
            </button>
        </div>
        <nav class="flex flex-1 flex-col gap-1">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" @click="menu = false"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[22px] leading-none">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
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

        <div class="relative ml-2 flex shrink-0 items-center gap-3">
            <div class="relative" @click.outside="accountMenu = false">
                <button type="button" @click="accountMenu = !accountMenu"
                    class="flex items-center gap-3 rounded-xl p-1.5 text-left transition-colors hover:bg-surface-container-low"
                    :aria-expanded="accountMenu" aria-haspopup="dialog" aria-label="Mở menu tài khoản">
                    <div class="hidden text-right sm:block">
                        <p class="font-label-md text-label-md text-on-surface">{{ auth()->user()->name }}</p>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">Cộng tác viên</p>
                    </div>
                    <span
                        class="flex size-10 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-primary-container font-bold text-body-md text-on-primary-container">
                        {{ auth()->user()->avatarInitial() }}
                    </span>
                </button>

                <section x-show="accountMenu" x-cloak
                    class="absolute top-[calc(100%+0.5rem)] right-0 z-50 w-[min(100vw-2rem,280px)] overflow-hidden rounded-[10px] border border-outline-variant bg-surface shadow-xl"
                    role="dialog">
                    <form action="{{ route('partner.logout') }}" method="post" class="p-3">
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
</body>

</html>
