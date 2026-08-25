@props(['title' => null])

@php
    use Illuminate\Support\Facades\DB;
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
    $isStudent = auth()->user()->hasRole(\App\Support\Enums\Role::Student->value);
    $searchHistory = DB::table('search_histories')
        ->select('query')
        ->where('user_id', auth()->id())
        ->whereNotNull('query')
        ->where('query', '!=', '')
        ->orderByDesc('created_at')
        ->limit(6)
        ->pluck('query')
        ->all();
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
            mode: 'search',
            loading: false,
            query: @js((string) request('q', '')),
            suggestions: [],
            history: @js($searchHistory),
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
            openPopup() {
                this.mode = 'search';
                this.open = true;
                this.fetchSuggestions();
                this.$nextTick(() => this.$refs.searchPopupInput?.focus());
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
        @include('components.layouts.partials.app-sidebar', [
            'navItems' => $navItems,
            'subscription' => $headerSubscription,
        ])
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
        @include('components.layouts.partials.app-sidebar', [
            'navItems' => $navItems,
            'closeOnNavigate' => true,
            'subscription' => $headerSubscription,
        ])
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
                        placeholder="Tìm kiếm bài học, classroom, kỳ thi..." aria-label="Tìm kiếm toàn hệ thống"
                        autocomplete="off"
                        readonly
                        @click.prevent="search.openPopup()"
                        class="w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pr-4 pl-10 font-body-sm text-body-sm hover:border-primary focus:ring-2 focus:ring-primary outline-none focus:outline-none focus-visible:outline-none">
                </form>
            </div>
        </div>

        <div class="ml-2 flex shrink-0 items-center gap-6">
            <div class="flex items-center gap-3">
                @include('notification::partials.bell', ['indexRoute' => 'notifications.index'])
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
                                'premium-badge text-white shadow-sm' => ! $headerSubscription['is_free'],
                            ])>
                            <span class="material-symbols-outlined text-[16px]"
                                @if (! $headerSubscription['is_free']) style="font-variation-settings: 'FILL' 1;" @endif>workspace_premium</span>
                            {{ $membershipChipLabel }}
                        </a>

                        <a href="{{ route('profile.show') }}" @click="accountMenu = false"
                            class="block w-full rounded-lg bg-primary px-4 py-2.5 text-center font-label-md text-label-md font-bold text-on-primary transition-opacity hover:opacity-90">
                            Quản lý tài khoản
                        </a>

                        @if ($headerSubscription['is_free'])
                            <a href="{{ route('subscription.upgrade') }}" @click="accountMenu = false"
                                class="block text-center font-label-sm font-semibold text-primary hover:underline">
                                Nâng cấp Premium
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

    <div x-show="search.open" x-cloak class="fixed inset-0 z-[80] bg-black/35" @click="search.open = false"></div>
    <section x-show="search.open" x-cloak x-transition.opacity
        class="fixed inset-0 z-[90] flex items-start justify-center p-4 sm:p-6">
        <div class="mt-4 w-full max-w-2xl overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-2xl sm:mt-8"
            @click.stop>
            <div class="border-b border-outline-variant px-3 py-3 sm:px-4">
                <div class="relative flex items-center gap-2.5 rounded-xl border border-outline-variant bg-surface-container-low px-3 py-1.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                    <span class="material-symbols-outlined pointer-events-none text-[20px] text-on-surface-variant">search</span>
                    <input x-ref="searchPopupInput" x-model="search.query" type="search" placeholder="Tìm kiếm..."
                        autocomplete="off"
                        @input.debounce.250ms="search.fetchSuggestions()"
                        @keydown.enter.prevent="search.submit()"
                        class="w-full border-0 bg-transparent px-0 py-1 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant outline-none focus:outline-none focus:ring-0 focus-visible:outline-none">
                    <button type="button"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container"
                        @click="search.open = false" aria-label="Đóng popup">
                        <span class="material-symbols-outlined text-[20px] leading-none">cancel</span>
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" @click="search.mode = 'search'"
                        :class="search.mode === 'search' ? 'bg-primary text-white border-primary' : 'bg-white text-on-surface-variant border-outline-variant hover:bg-surface-container-low'"
                        class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-bold transition-colors">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                        Tìm kiếm
                    </button>
                    <button type="button" @click="search.mode = 'ai'"
                        :class="search.mode === 'ai' ? 'bg-primary-container text-primary border-primary/30' : 'bg-white text-on-surface-variant border-outline-variant hover:bg-surface-container-low'"
                        class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-bold transition-colors">
                        <span class="material-symbols-outlined text-[18px]">psychology</span>
                        Chế độ AI
                    </button>
                    <button type="button" @click="search.submit()"
                        :class="search.query.trim() === '' ? 'bg-primary/20 text-primary/40' : 'bg-primary text-white hover:bg-primary/90'"
                        class="ml-auto inline-flex size-11 items-center justify-center rounded-full shadow-sm transition-colors"
                        aria-label="Tìm kiếm">
                        <span class="material-symbols-outlined text-[22px]">arrow_forward</span>
                    </button>
                </div>
            </div>

            <div x-show="search.query.trim() === ''" class="bg-surface-container-lowest p-2">
                <div class="rounded-xl bg-surface p-2">
                    <p class="px-1 pb-2 font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Search history</p>
                    <div class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
                        @forelse ($searchHistory as $historyItem)
                            <a href="{{ route('search.index', ['q' => $historyItem]) }}"
                                class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 transition-colors last:border-0 hover:bg-primary/5"
                                @click="search.open = false">
                                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">search</span>
                                <span class="min-w-0 flex-1 truncate font-body-md text-body-md text-on-surface">{{ $historyItem }}</span>
                            </a>
                        @empty
                            <div class="px-4 py-5 text-sm text-on-surface-variant">
                                Chưa có lịch sử tìm kiếm.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div x-show="search.query.trim() !== ''" x-cloak class="border-b border-outline-variant bg-surface-container-lowest p-4">
                <p class="mb-3 font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Search for</p>
                <button type="button" @click="search.submit()"
                    class="flex w-full items-center gap-3 rounded-xl border border-outline-variant bg-white px-4 py-3 text-left transition-colors hover:border-primary/40 hover:bg-primary/5">
                    <span class="material-symbols-outlined text-[20px] text-on-surface-variant">search</span>
                    <span class="min-w-0 flex-1 truncate font-body-md text-body-md text-on-surface" x-text="search.query.trim()"></span>
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">arrow_forward</span>
                </button>

                <template x-if="search.suggestions.length > 0">
                    <div class="mt-4">
                        <p class="mb-3 font-label-md text-label-md font-bold tracking-wide text-on-surface-variant uppercase">Nội dung liên quan</p>
                        <div class="space-y-2">
                            <template x-for="item in search.suggestions" :key="item.id">
                                <a :href="item.url ?? ('{{ route('search.index') }}?q=' + encodeURIComponent(item.text))"
                                    class="flex items-start gap-3 rounded-xl border border-outline-variant bg-white px-3 py-3 transition-colors hover:border-primary/40 hover:bg-primary/5"
                                    @click="search.open = false">
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">article</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-md text-body-md font-medium text-on-surface" x-text="item.text"></p>
                                        <p class="mt-1 line-clamp-2 font-label-sm text-label-sm text-on-surface-variant" x-html="item.highlight"></p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-surface-container-low px-2.5 py-1 font-label-xs text-label-xs text-on-surface-variant"
                                        x-text="item.type || 'keyword'"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>


        </div>
    </section>

    <!-- Content Canvas -->
    <main class="min-h-screen bg-surface-container-lowest pt-header-height md:ml-sidebar-width">
        {{ $slot }}
    </main>

    {{-- Persistent learner support entry point, intentionally separate from navigation. --}}
    <button type="button" data-support-launcher
        class="group fixed right-5 bottom-5 z-40 flex size-14 items-center justify-center rounded-full bg-primary text-on-primary shadow-lg ring-4 ring-surface transition-transform hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary sm:right-7 sm:bottom-7"
        aria-label="Mở chat hỗ trợ" title="Chat hỗ trợ">
        <span class="material-symbols-outlined text-[28px] leading-none">support_agent</span>
        <span class="pointer-events-none absolute right-[calc(100%+0.75rem)] whitespace-nowrap rounded-lg bg-inverse-surface px-3 py-2 text-sm font-medium text-inverse-on-surface opacity-0 shadow-md transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
            Hỗ trợ trực tuyến
        </span>
    </button>

    <dialog data-support-dialog class="fixed inset-auto right-6 bottom-24 m-0 h-[560px] w-[390px] max-w-none overflow-hidden rounded-2xl border border-outline-variant bg-surface p-0 text-on-surface shadow-[0_20px_60px_rgba(20,28,45,0.28)] backdrop:bg-transparent max-md:inset-0 max-md:m-0 max-md:h-[100dvh] max-md:w-full max-md:rounded-none max-md:border-0">
        <div class="relative flex h-full min-h-0 flex-col">
            <header class="flex shrink-0 items-center gap-3 bg-primary px-4 py-3.5 text-on-primary">
                <span class="flex size-9 items-center justify-center rounded-xl bg-white/15"><span class="material-symbols-outlined text-[20px]">support_agent</span></span>
                <div class="min-w-0 flex-1"><p class="font-title-sm font-bold">MedLearn Support</p><p class="mt-0.5 text-[11px] text-on-primary/80">Thường phản hồi trong vài phút</p></div>
                <button type="button" data-support-history class="flex size-9 items-center justify-center rounded-lg text-on-primary/80 transition-colors hover:bg-white/15 hover:text-on-primary" aria-label="Lịch sử hội thoại"><span class="material-symbols-outlined text-[20px]">history</span></button>
                <button type="button" data-support-close class="flex size-9 items-center justify-center rounded-lg text-on-primary/80 transition-colors hover:bg-white/15 hover:text-on-primary" aria-label="Đóng chat"><span class="material-symbols-outlined text-[20px]">close</span></button>
            </header>
            <section data-support-history-panel class="absolute inset-x-0 top-[64px] bottom-0 z-10 hidden flex flex-col bg-surface"><div class="flex shrink-0 items-center justify-between border-b border-outline-variant px-4 py-3"><p class="font-title-sm font-bold">Hội thoại</p><button type="button" data-support-new class="inline-flex items-center gap-1 rounded-lg bg-primary px-2.5 py-1.5 text-xs font-semibold text-on-primary"><span class="material-symbols-outlined text-[16px]">add</span>Tạo mới</button></div><div data-support-list class="min-h-0 flex-1 overflow-y-auto p-2"></div></section>
            <div data-support-empty class="m-auto max-w-[280px] p-6 text-center"><span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary-container text-primary"><span class="material-symbols-outlined text-[26px]">forum</span></span><h2 class="mt-4 font-title-md font-bold">Bạn cần hỗ trợ?</h2><p class="mt-2 text-sm leading-5 text-on-surface-variant">Trợ lý AI sẵn sàng hỗ trợ trước khi chuyển đến đội ngũ MedLearn.</p><button type="button" data-support-new class="mt-5 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary">Bắt đầu cuộc trò chuyện</button></div>
            <form data-support-create class="hidden min-h-0 flex-1 space-y-3 overflow-y-auto p-5"><div><p class="font-title-md font-bold">Yêu cầu mới</p><p class="mt-1 text-xs text-on-surface-variant">Chọn chủ đề để hỗ trợ nhanh hơn.</p></div><label class="block text-xs font-semibold text-on-surface-variant">DANH MỤC<select name="category" class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 text-sm font-normal text-on-surface"><option value="account">Tài khoản</option><option value="billing">Thanh toán</option><option value="course">Khóa học</option><option value="system">Lỗi hệ thống</option><option value="other">Vấn đề khác</option></select></label><input name="subject" maxlength="160" class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 text-sm" placeholder="Tiêu đề (không bắt buộc)"><textarea name="message" required maxlength="4000" rows="5" class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 text-sm" placeholder="Mô tả vấn đề của bạn…"></textarea><p class="text-xs leading-5 text-on-surface-variant">Không gửi mật khẩu, OTP hoặc thông tin thẻ.</p><button class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary">Gửi yêu cầu</button></form>
            <div data-support-chat class="hidden flex min-h-0 flex-1 flex-col overflow-hidden"><div class="flex shrink-0 items-center gap-2 border-b border-outline-variant px-4 py-2.5"><span class="size-2 rounded-full bg-green-500"></span><p data-support-title class="min-w-0 flex-1 truncate text-xs font-semibold text-on-surface"></p></div><div data-support-messages class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-surface-container-lowest px-4 py-4" role="log" aria-live="polite"></div><form data-support-message class="flex shrink-0 items-end gap-2 border-t border-outline-variant bg-surface p-3"><textarea name="message" required maxlength="4000" rows="1" class="min-w-0 flex-1 resize-none rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2.5 text-sm" placeholder="Nhập tin nhắn…"></textarea><button type="submit" data-support-send class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-on-primary transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Gửi tin nhắn"><span class="material-symbols-outlined text-[19px]">arrow_upward</span></button></form></div>
        </div>
    </dialog>

    @livewireScriptConfig
    <script>
        (() => {
            const dialog = document.querySelector('[data-support-dialog]');
            const launcher = document.querySelector('[data-support-launcher]');
            const list = document.querySelector('[data-support-list]');
            const empty = document.querySelector('[data-support-empty]');
            const create = document.querySelector('[data-support-create]');
            const chat = document.querySelector('[data-support-chat]');
            const historyPanel = document.querySelector('[data-support-history-panel]');
            const messages = document.querySelector('[data-support-messages]');
            const title = document.querySelector('[data-support-title]');
            const messageForm = document.querySelector('[data-support-message]');
            const messageInput = messageForm?.querySelector('textarea[name="message"]');
            const sendButton = messageForm?.querySelector('[data-support-send]');
            const labels = { account: 'Tài khoản', billing: 'Thanh toán', course: 'Khóa học', system: 'Lỗi hệ thống', other: 'Vấn đề khác' };
            const statuses = { ai_active: 'Trợ lý AI đang hỗ trợ', waiting_admin: 'Đang chờ quản trị viên', admin_active: 'Quản trị viên đang hỗ trợ', resolved: 'Đã giải quyết' };
            let current = null, conversations = [], subscribedConversationId = null, sending = false;
            let typingController = null;
            const ensureTyping = () => {
                if (typingController || !window.MedlearnSupportTyping || !messages) return typingController;
                typingController = window.MedlearnSupportTyping.createSupportTypingController({ messagesEl: messages, selfSenderType: 'user', remoteLabel: 'Quản trị viên' });
                typingController.bindInput(messageInput);
                return typingController;
            };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const request = async (url, options = {}) => {
                const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) } });
                if (!response.ok) throw new Error('support_request_failed');
                return response.json();
            };
            const show = (element, visible) => element?.classList.toggle('hidden', !visible);
            const scrollMessages = () => { messages.scrollTop = messages.scrollHeight; };
            const renderMessages = () => {
                messages.querySelectorAll('[data-message-row]').forEach((node) => node.remove());
                (current?.messages || []).forEach((message) => {
                    const row = document.createElement('div'); row.dataset.messageRow = 'true'; row.className = `flex ${message.sender_type === 'user' ? 'justify-end' : 'justify-start'}`;
                    const bubble = document.createElement('div'); bubble.className = `max-w-[80%] rounded-2xl px-4 py-3 ${message.sender_type === 'user' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'}`;
                    const by = document.createElement('p'); by.className = 'mb-1 text-xs opacity-70'; by.textContent = message.sender_type === 'ai' ? 'Trợ lý AI' : (message.sender_type === 'admin' ? 'Quản trị viên' : 'Bạn');
                    const body = document.createElement('p'); body.className = 'whitespace-pre-wrap'; body.textContent = message.body; bubble.append(by, body); row.append(bubble);
                    messages.insertBefore(row, ensureTyping()?.getTypingNode() ?? null);
                });
                scrollMessages();
            };
            const render = () => {
                list.replaceChildren(); conversations.forEach((conversation) => { const button = document.createElement('button'); button.type = 'button'; button.className = `mb-1 w-full rounded-lg p-3 text-left ${current?.id === conversation.id ? 'bg-primary-container' : 'hover:bg-surface-container'}`; const subject = document.createElement('p'); subject.className = 'truncate font-semibold'; subject.textContent = conversation.subject || labels[conversation.category]; const status = document.createElement('p'); status.className = 'mt-1 text-xs text-on-surface-variant'; status.textContent = statuses[conversation.status]; button.append(subject, status); button.addEventListener('click', () => { show(historyPanel, false); load(conversation.id); }); list.append(button); });
                show(empty, !current && create.classList.contains('hidden')); show(chat, !!current); if (current) { show(empty, false); title.textContent = `${current.subject || labels[current.category]} · ${statuses[current.status]}`; renderMessages(); show(messageForm, current.status !== 'resolved'); }
            };
            const subscribe = () => {
                ensureTyping();
                if (!window.Echo || !current || subscribedConversationId === current.id) return;
                if (subscribedConversationId) window.Echo.leave(`support-conversation.${subscribedConversationId}`);
                subscribedConversationId = current.id;
                const echoChannel = window.Echo.join(`support-conversation.${current.id}`);
                echoChannel.listen('.message.created', () => load(current.id));
                ensureTyping()?.bindChannel(echoChannel);
            };
            const load = async (id = null) => { const data = await request(`{{ route('support.index') }}${id ? `?conversation=${id}` : ''}`); conversations = data.conversations; current = data.conversation; show(create, false); render(); subscribe(); };
            const setSending = (value) => { sending = value; if (sendButton) sendButton.disabled = value; if (messageInput) messageInput.disabled = value; };
            launcher.addEventListener('click', async () => { ensureTyping(); dialog.showModal(); window.enableMedlearnRealtime?.().catch(() => null); try { await load(); } catch { show(create, true); show(empty, false); } });
            document.querySelector('[data-support-close]').addEventListener('click', () => dialog.close());
            document.querySelector('[data-support-history]').addEventListener('click', () => show(historyPanel, historyPanel.classList.contains('hidden')));
            document.querySelectorAll('[data-support-new]').forEach((button) => button.addEventListener('click', () => { current = null; show(historyPanel, false); show(create, true); show(empty, false); show(chat, false); }));
            create.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (sending) return;
                setSending(true);
                ensureTyping()?.setAiTyping(true);
                show(create, false);
                show(chat, true);
                show(empty, false);
                try {
                    const data = await request('{{ route('support.store') }}', { method: 'POST', body: new FormData(create) });
                    current = data.conversation;
                    conversations.unshift(current);
                    create.reset();
                    render();
                } catch {
                    show(create, true);
                    show(chat, false);
                } finally {
                    ensureTyping()?.setAiTyping(false);
                    setSending(false);
                }
            });
            messageForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!current || current.status === 'resolved' || sending) return;
                const text = messageInput.value.trim();
                if (!text) return;
                messageInput.value = '';
                setSending(true);
                const awaitingAi = current.status === 'ai_active';
                const snapshot = current;
                current = { ...current, messages: [...(current.messages || []), { sender_type: 'user', body: text }] };
                renderMessages();
                if (awaitingAi) ensureTyping()?.setAiTyping(true);
                try {
                    const form = new FormData();
                    form.append('message', text);
                    const data = await request(`{{ url('/support') }}/${snapshot.id}/messages`, { method: 'POST', body: form });
                    current = data.conversation;
                    conversations = conversations.map((item) => item.id === current.id ? current : item);
                    render();
                } catch {
                    current = snapshot;
                    renderMessages();
                    messageInput.value = text;
                } finally {
                    ensureTyping()?.setAiTyping(false);
                    setSending(false);
                }
            });
            const bootRealtime = () => {
                ensureTyping();
                window.enableMedlearnRealtime?.().catch(() => null);
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootRealtime, { once: true });
            } else {
                bootRealtime();
            }
            window.addEventListener('medlearn:support-typing-ready', ensureTyping, { once: true });
            window.addEventListener('medlearn:echo-ready', subscribe);
        })();
    </script>
    @stack('scripts')
    <x-billing::paywall-overlay />
</body>

</html>
