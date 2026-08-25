@php
    use App\Support\Auth\Instructor;
    use App\Support\Auth\Staff;
    use Modules\Admin\Support\Cms\ResolvedMenu;
    use Modules\Billing\Support\CurrentSubscription;

    $navLinks = ResolvedMenu::headerLinks();
    $siteName = setting('general.site_name', config('app.name'));
    $registrationEnabled = setting('features.registration_enabled', true);

    $user = auth()->user();
    $isAuthenticated = $user !== null;
    $subscription = CurrentSubscription::for($user instanceof \App\Models\User ? $user : null);
    $isPremium = ! ($subscription['is_free'] ?? true);
    $planName = $isPremium
        ? ($subscription['plan_name'] ?: 'Premium')
        : 'Free';

    if ($user !== null && Staff::isStaff($user)) {
        $studyHref = route('admin.dashboard');
        $studyLabel = 'Vào quản trị';
    } elseif ($user !== null && Instructor::is($user)) {
        $studyHref = route('teach.dashboard');
        $studyLabel = 'Vào Teach';
    } elseif ($user !== null) {
        $studyHref = route('qbank.index');
        $studyLabel = 'Tạo phiên học';
    } else {
        $studyHref = null;
        $studyLabel = null;
    }
@endphp

<nav x-data="{ open: false }"
    class="sticky top-0 z-50 border-b border-border bg-surface/80 backdrop-blur-md">
    <div class="mx-auto flex h-16 w-full max-w-container-max items-center justify-between px-margin-mobile md:px-gutter">
        <a href="{{ route('landing.home') }}"
            class="font-headline-md text-headline-md font-bold tracking-tight text-primary">{{ $siteName }}</a>

        <div class="hidden items-center gap-8 md:flex">
            @foreach ($navLinks as $link)
                <a href="{{ $link['href'] }}"
                    @class([
                        'font-label-md text-label-md transition-colors duration-200',
                        'font-bold text-primary' => $link['route'] && request()->routeIs($link['route']),
                        'text-on-surface-variant hover:text-primary' => ! ($link['route'] && request()->routeIs($link['route'])),
                    ])>{{ $link['label'] }}</a>
            @endforeach
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            @if ($isAuthenticated)
                <div class="hidden min-w-0 items-center gap-2.5 sm:flex">
                    <div class="min-w-0 text-right">
                        <p class="truncate font-label-md text-label-md font-semibold text-on-surface max-w-[9rem] lg:max-w-[12rem]"
                            title="{{ $user->name }}">{{ $user->name }}</p>
                        @if ($isPremium)
                            <span
                                class="premium-badge mt-0.5 inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                <span class="material-symbols-outlined text-[12px] leading-none"
                                    style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                                {{ $planName }}
                            </span>
                        @else
                            <span
                                class="mt-0.5 inline-flex items-center rounded-full border border-outline-variant bg-surface-container-low px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant">
                                {{ $planName }}
                            </span>
                        @endif
                    </div>
                </div>

                <a href="{{ $studyHref }}"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2.5 font-label-md text-label-md font-semibold text-on-primary transition-all hover:opacity-90 active:scale-[0.98] sm:px-5">
                    <span class="material-symbols-outlined text-[18px] leading-none">play_arrow</span>
                    <span class="hidden sm:inline">{{ $studyLabel }}</span>
                    <span class="sm:hidden">Học</span>
                </a>
            @else
                <a href="{{ route('login') }}"
                    class="hidden px-4 py-2 font-label-md text-label-md text-on-surface-variant transition-colors hover:text-primary md:inline-block">Đăng nhập</a>
                @if ($registrationEnabled)
                    <a href="{{ route('register') }}"
                        class="hidden rounded-xl bg-primary-container px-6 py-2.5 font-label-md text-label-md text-on-primary-container transition-all hover:opacity-90 active:scale-95 sm:inline-block">Đăng ký</a>
                @endif
            @endif

            <button type="button" @click="open = !open"
                class="inline-flex items-center justify-center rounded-lg p-2 text-on-surface transition-colors hover:bg-surface-container-low md:hidden"
                :aria-expanded="open" aria-label="Mở menu">
                <span class="material-symbols-outlined text-[24px] leading-none" x-show="!open">menu</span>
                <span class="material-symbols-outlined text-[24px] leading-none" x-show="open" x-cloak>close</span>
            </button>
        </div>
    </div>

    {{-- Mobile drawer --}}
    <div x-show="open" x-cloak x-transition.origin.top
        class="space-y-1 border-t border-border bg-surface px-margin-mobile py-4 md:hidden">
        @foreach ($navLinks as $link)
            <a href="{{ $link['href'] }}"
                @class([
                    'block rounded-lg px-3 py-2.5 font-label-md text-label-md transition-colors',
                    'bg-primary-fixed/20 text-primary' => $link['route'] && request()->routeIs($link['route']),
                    'text-on-surface-variant hover:bg-surface-container-low' => ! ($link['route'] && request()->routeIs($link['route'])),
                ])>{{ $link['label'] }}</a>
        @endforeach

        <div class="mt-2 space-y-3 border-t border-border pt-3">
            @if ($isAuthenticated)
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-3">
                    <p class="font-label-md text-label-md font-semibold text-on-surface">{{ $user->name }}</p>
                    <div class="mt-2">
                        @if ($isPremium)
                            <span
                                class="premium-badge inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">
                                <span class="material-symbols-outlined text-[14px] leading-none"
                                    style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                                {{ $planName }}
                            </span>
                        @else
                            <span
                                class="inline-flex items-center rounded-full border border-outline-variant bg-surface px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">
                                {{ $planName }}
                            </span>
                        @endif
                    </div>
                </div>
                <a href="{{ $studyHref }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                    {{ $studyLabel }}
                </a>
                <a href="{{ route('profile.show') }}"
                    class="block w-full rounded-xl border border-border py-2.5 text-center font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                    Hồ sơ của tôi
                </a>
            @else
                <a href="{{ route('login') }}"
                    class="block w-full rounded-xl border border-border py-2.5 text-center font-label-md text-label-md text-on-surface hover:bg-surface-container-low">Đăng nhập</a>
                @if ($registrationEnabled)
                    <a href="{{ route('register') }}"
                        class="block w-full rounded-xl bg-primary-container py-2.5 text-center font-label-md text-label-md text-on-primary-container hover:opacity-90">Đăng ký</a>
                @endif
            @endif
        </div>
    </div>
</nav>
