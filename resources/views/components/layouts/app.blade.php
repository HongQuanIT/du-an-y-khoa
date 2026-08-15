@props(['title' => null])

@php
    use Modules\Billing\Support\CurrentSubscription;

    // Dashboard, Q-Bank, StudyPlan, Flashcards are wired; the rest land as modules ship.
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard'],
        ['label' => 'Ngân hàng câu hỏi', 'icon' => 'quiz', 'route' => 'qbank.index', 'match' => 'qbank.*'],
        ['label' => 'Thư viện', 'icon' => 'library_books', 'route' => null],
        ['label' => 'Flashcards', 'icon' => 'style', 'route' => 'flashcards.index', 'match' => 'flashcards.*'],
        ['label' => 'Kế hoạch học tập', 'icon' => 'event_note', 'route' => 'study-plan.index', 'match' => 'study-plan.*'],
        ['label' => 'Classroom', 'icon' => 'cast_for_education', 'route' => 'classroom.index', 'match' => 'classroom.*'],
        ['label' => 'Phân tích', 'icon' => 'analytics', 'route' => null],
        ['label' => 'Kỳ thi', 'icon' => 'assignment', 'route' => 'exam.index', 'match' => 'exam.*'],
    ];

    $headerSubscription = CurrentSubscription::for(auth()->user());
    $membershipChipParts = [$headerSubscription['is_free'] ? 'Free' : 'Premium'];

    if (! $headerSubscription['is_free'] && filled($headerSubscription['price_label'])) {
        $membershipChipParts[] = $headerSubscription['price_label'];
    }

    if (! $headerSubscription['is_free'] && $headerSubscription['ends_at']?->isFuture()) {
        $membershipChipParts[] = 'còn '.(int) now()->diffInDays($headerSubscription['ends_at']).' ngày';
    } elseif (! $headerSubscription['is_free'] && $headerSubscription['ends_at'] === null) {
        $membershipChipParts[] = 'không giới hạn';
    }

    $membershipChipLabel = implode(' · ', $membershipChipParts);
    $isStudent = auth()->user()->getRoleNames()->first() === 'student';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">

<head>
    <meta charset="utf-8">
    <x-theme-init />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-surface font-body-md text-on-surface"
    x-data="{
        menu: false,
        accountMenu: false,
        notificationsOpen: false,
        search: {
            open: false,
            loading: false,
            query: @js((string) request('q', '')),
            suggestions: [],
            controller: null,
            async fetchSuggestions() {
                const query = this.query.trim();
                if (this.controller) {
                    this.controller.abort();
                }

                this.controller = new AbortController();
                this.loading = true;

                try {
                    const url = new URL(@js(route('search.suggest')), window.location.origin);
                    if (query !== '') {
                        url.searchParams.set('q', query);
                    }
                    url.searchParams.set('limit', '6');

                    const response = await fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        signal: this.controller.signal,
                    });

                    if (!response.ok) {
                        throw new Error('search_suggest_failed');
                    }

                    const payload = await response.json();
                    this.suggestions = Array.isArray(payload.data) ? payload.data : [];
                    this.open = true;
                } catch (error) {
                    if (error?.name !== 'AbortError') {
                        this.suggestions = [];
                    }
                } finally {
                    this.loading = false;
                }
            },
            clear() {
                this.query = '';
                this.suggestions = [];
                this.open = true;
                this.fetchSuggestions();
            },
            openDropdown() {
                this.open = true;
                this.fetchSuggestions();
            },
            submit() {
                window.location.assign(@js(route('search.index')) + '?q=' + encodeURIComponent(this.query.trim()));
            },
        },
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

            <div class="relative w-full max-w-2xl" @click.outside="search.open = false">
                <form method="GET" action="{{ route('search.index') }}" role="search" class="relative" @submit.prevent="search.submit()">
                    <span
                        class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant">search</span>
                    <input name="q" x-model="search.query" type="search"
                        placeholder="Tìm kiếm bài học, câu hỏi, thuốc..." aria-label="Tìm kiếm toàn hệ thống"
                        autocomplete="off"
                        @focus="search.openDropdown()"
                        @input.debounce.250ms="search.openDropdown()"
                        class="w-full rounded-lg border-none bg-surface-container-low py-2 pr-4 pl-10 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                </form>

                <section x-show="search.open" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-xl">
                    <div class="border-b border-outline-variant px-4 py-3">
                        <p class="font-label-sm text-label-sm text-on-surface-variant">
                            <span x-show="search.loading">Đang tìm gợi ý…</span>
                            <span x-show="!search.loading">Gợi ý tìm kiếm</span>
                        </p>
                    </div>
                    <div class="max-h-[min(60vh,28rem)] overflow-y-auto">
                        <template x-if="search.suggestions.length === 0 && !search.loading">
                            <p class="px-4 py-5 font-body-sm text-body-sm text-on-surface-variant">
                                Chưa có gợi ý phù hợp.
                            </p>
                        </template>
                        <template x-for="item in search.suggestions" :key="item.id">
                            <a :href="item.url ?? ('{{ route('search.index') }}?q=' + encodeURIComponent(item.text))"
                                class="block border-b border-outline-variant/60 px-4 py-3 transition-colors last:border-0 hover:bg-primary/5"
                                @click="search.open = false">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-body-md text-body-md font-medium text-on-surface" x-text="item.text"></p>
                                        <p class="mt-1 font-label-sm text-label-sm text-on-surface-variant" x-html="item.highlight"></p>
                                    </div>
                                    <span class="rounded-full bg-surface-container-low px-2.5 py-1 font-label-xs text-label-xs text-on-surface-variant"
                                        x-text="item.type || 'keyword'"></span>
                                </div>
                            </a>
                        </template>
                    </div>
                </section>
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
                        {{ $isStudent ? 'Học viên' : 'Nhân sự' }}
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
                                {{ $isStudent ? 'Học viên Y khoa' : 'Nhân sự' }}
                            </p>
                        </div>

                        <a href="{{ route('profile.show', ['tab' => 'membership']) }}" @click="accountMenu = false"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-label-sm font-semibold transition-opacity hover:opacity-90',
                                'bg-surface-container-high text-on-surface-variant' => $headerSubscription['is_free'],
                                'bg-primary/15 text-primary' => ! $headerSubscription['is_free'],
                            ])>
                            <span class="material-symbols-outlined text-[16px]">workspace_premium</span>
                            {{ $membershipChipLabel }}
                        </a>

                        <a href="{{ route('profile.show') }}" @click="accountMenu = false"
                            class="block w-full rounded-lg bg-primary px-4 py-2.5 text-center font-label-md text-label-md font-bold text-on-primary transition-opacity hover:opacity-90">
                            Quản lý tài khoản
                        </a>

                        @if ($headerSubscription['is_free'])
                            <a href="{{ route('landing.pricing') }}" @click="accountMenu = false"
                                class="block text-center font-label-sm text-primary hover:underline">
                                Nâng cấp gói
                            </a>
                        @endif
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
