@props(['title' => null])

@php
    // Dashboard, Q-Bank, StudyPlan, Flashcards are wired; the rest land as modules ship.
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard'],
        ['label' => 'Ngân hàng câu hỏi', 'icon' => 'quiz', 'route' => 'qbank.index', 'match' => 'qbank.*'],
        ['label' => 'Thư viện', 'icon' => 'library_books', 'route' => null],
        ['label' => 'Flashcards', 'icon' => 'style', 'route' => 'flashcards.index', 'match' => 'flashcards.*'],
        ['label' => 'Kế hoạch học tập', 'icon' => 'event_note', 'route' => 'study-plan.index', 'match' => 'study-plan.*'],
        ['label' => 'Classroom', 'icon' => 'cast_for_education', 'route' => 'classroom.index', 'match' => 'classroom.*'],
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

<body class="bg-surface font-body-md text-on-surface"
    x-data="{ menu: false, accountMenu: false, notificationsOpen: false, language: 'vi', units: 'si', theme: 'light' }"
    @keydown.escape.window="menu = false; accountMenu = false; notificationsOpen = false">
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
                <div class="relative" @click.outside="notificationsOpen = false">
                    <button type="button" @click="notificationsOpen = !notificationsOpen; accountMenu = false"
                        class="group relative cursor-pointer rounded-full p-2 transition-colors hover:bg-surface-container"
                        :aria-expanded="notificationsOpen" aria-label="Thông báo">
                        <span
                            class="material-symbols-outlined text-[24px] leading-none text-on-surface-variant group-hover:text-primary">notifications</span>
                        @if (($headerUnreadCount ?? 0) > 0)
                            <span class="absolute top-2 right-2 size-2 rounded-full border-2 border-surface bg-error"></span>
                        @endif
                    </button>

                    <section x-show="notificationsOpen" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="translate-y-1 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        class="absolute right-0 z-50 mt-2 w-[min(100vw-2rem,360px)] overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-lg">
                        <div class="flex items-center justify-between border-b border-outline-variant px-4 py-3">
                            <p class="font-label-md text-label-md font-semibold text-on-surface">Thông báo</p>
                            @if (($headerUnreadCount ?? 0) > 0)
                                <form method="post" action="{{ route('notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="font-label-sm text-label-sm text-primary hover:underline">
                                        Đánh dấu đã đọc
                                    </button>
                                </form>
                            @endif
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            @forelse ($headerNotifications ?? [] as $notification)
                                <div @class([
                                    'border-b border-outline-variant/60 px-4 py-3 last:border-0',
                                    'bg-primary-fixed/15' => $notification->read_at === null,
                                ])>
                                    <p class="font-label-md text-label-md font-semibold text-on-surface">{{ $notification->title }}</p>
                                    <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $notification->body }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <span class="font-label-sm text-label-sm text-on-surface-variant">
                                            {{ $notification->created_at->diffForHumans() }}
                                        </span>
                                        @if ($notification->read_at === null)
                                            <form method="post" action="{{ route('notifications.read', $notification) }}">
                                                @csrf
                                                <button type="submit" class="font-label-sm text-label-sm text-primary hover:underline">
                                                    Đã đọc
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="px-4 py-6 font-body-md text-body-md text-on-surface-variant">Chưa có thông báo.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>

            <div class="hidden h-8 w-px bg-outline-variant sm:block"></div>

            <div class="relative" @click.outside="accountMenu = false">
                <button type="button" @click="accountMenu = !accountMenu"
                    class="flex items-center gap-3 rounded-xl p-1.5 text-left transition-colors hover:bg-surface-container-low focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    :aria-expanded="accountMenu" aria-haspopup="dialog" aria-label="Mở menu tài khoản">
                <div class="hidden text-right sm:block">
                    <p class="font-label-md text-label-md text-on-surface">{{ auth()->user()->name }}</p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">
                        {{ auth()->user()->getRoleNames()->first() === 'student' ? 'Học viên' : 'Nhân sự' }}
                    </p>
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
                            <p class="font-body-md text-body-md text-on-surface-variant">
                                {{ auth()->user()->getRoleNames()->first() === 'student' ? 'Học viên Y khoa' : 'Nhân sự' }}
                            </p>
                        </div>
                        <div>
                            <p class="font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Mục tiêu học tập hiện tại</p>
                            <p class="font-body-md text-body-md text-on-surface-variant">
                                {{ auth()->user()->studyObjectiveLabel() }}
                            </p>
                        </div>
                        <a href="{{ route('profile.show') }}" @click="accountMenu = false"
                            class="block w-full rounded-lg bg-primary px-4 py-2.5 text-center font-label-md text-label-md font-bold text-on-primary transition-opacity hover:opacity-90">
                            Quản lý tài khoản
                        </a>
                        <a href="{{ route('settings.edit', ['tab' => 'contact']) }}" @click="accountMenu = false"
                            class="mt-2 block w-full rounded-lg border border-outline-variant px-4 py-2.5 text-center font-label-md text-label-md font-bold text-on-surface transition-colors hover:bg-surface-container-low">
                            Liên hệ & cài đặt
                        </a>
                    </div>

                    <div class="space-y-4 p-4">
                        <label class="block">
                            <span class="font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Ngôn ngữ</span>
                            <select x-model="language"
                                class="mt-2 w-full rounded-lg border border-outline bg-surface px-3 py-2.5 font-body-md text-body-md text-on-surface focus:border-primary focus:ring-primary">
                                <option value="vi">vi — Tiếng Việt</option>
                                <option value="en">en — English</option>
                            </select>
                        </label>

                        <fieldset>
                            <legend class="font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Đơn vị</legend>
                            <div class="mt-2 grid grid-cols-3 overflow-hidden rounded-lg border border-outline-variant">
                                <template x-for="option in [{ value: 'si', label: 'SI' }, { value: 'us', label: 'US' }, { value: 'both', label: 'Cả hai' }]" :key="option.value">
                                    <button type="button" @click="units = option.value" x-text="option.label"
                                        class="border-r border-outline-variant px-2 py-2.5 font-label-md text-label-md font-bold last:border-r-0"
                                        :class="units === option.value ? 'bg-primary-container text-white' : 'bg-surface text-on-surface-variant'"></button>
                                </template>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Giao diện</legend>
                            <div class="mt-2 grid grid-cols-3 overflow-hidden rounded-lg border border-outline-variant">
                                <template x-for="option in [{ value: 'light', label: 'Sáng' }, { value: 'dark', label: 'Tối' }, { value: 'system', label: 'Hệ thống' }]" :key="option.value">
                                    <button type="button" @click="theme = option.value" x-text="option.label"
                                        class="border-r border-outline-variant px-2 py-2.5 font-label-md text-label-md font-bold last:border-r-0"
                                        :class="theme === option.value ? 'bg-primary-container text-white' : 'bg-surface text-on-surface-variant'"></button>
                                </template>
                            </div>
                        </fieldset>
                    </div>

                    <form action="{{ route('logout') }}" method="post" class="border-t border-outline-variant p-3">
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

    <!-- Content Canvas -->
    <main class="min-h-screen bg-surface-container-lowest pt-header-height md:ml-sidebar-width">
        {{ $slot }}
    </main>

    @livewireScriptConfig
</body>

</html>
