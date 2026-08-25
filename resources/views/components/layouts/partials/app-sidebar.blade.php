@php
    use Modules\Billing\Support\CurrentSubscription;

    $closeOnNavigate = $closeOnNavigate ?? false;
    $subscription = $subscription ?? CurrentSubscription::for(auth()->user());
    $isPremium = ! ($subscription['is_free'] ?? true);
    $planName = $isPremium
        ? ($subscription['plan_name'] ?: 'Premium')
        : 'Free';
    $premiumDetail = null;
    if ($isPremium && filled($subscription['price_label'] ?? null)) {
        $premiumDetail = $subscription['price_label'];
    }
    if ($isPremium && ($subscription['ends_at'] ?? null)?->isFuture()) {
        $daysLeft = (int) now()->diffInDays($subscription['ends_at']);
        $premiumDetail = trim(($premiumDetail ? $premiumDetail.' · ' : '').'còn '.$daysLeft.' ngày');
    }
@endphp

<a href="{{ route('dashboard') }}" class="mb-8 flex items-center gap-3 px-2"
    @if ($closeOnNavigate) @click="menu = false" @endif>
    <span class="material-symbols-outlined text-3xl text-primary"
        style="font-variation-settings: 'FILL' 1;">medical_services</span>
    <span class="flex flex-col">
        <span class="font-headline-md text-headline-md font-bold leading-tight text-primary">
            {{ config('app.name') }}
        </span>
        <span class="font-label-sm text-label-sm text-on-surface-variant">Học thuật Y khoa</span>
    </span>
</a>

<nav class="flex-1 space-y-1 overflow-y-auto">
    @foreach ($navItems as $item)
        @php
            $match = $item['match'] ?? $item['route'];
            $active = $match && request()->routeIs($match);
        @endphp
        <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
            @if ($closeOnNavigate) @click="menu = false" @endif
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

<div class="space-y-2 border-t border-outline-variant pt-4">
    @if ($isPremium)
        <a href="{{ route('subscription.show') }}"
            @if ($closeOnNavigate) @click="menu = false" @endif
            class="group relative block overflow-hidden rounded-xl border border-amber-500/25 bg-gradient-to-br from-amber-50 via-orange-50 to-rose-50 p-3 shadow-sm transition-all hover:border-amber-500/40 hover:shadow-md dark:border-amber-400/20 dark:from-amber-950/40 dark:via-orange-950/30 dark:to-rose-950/20">
            <div class="flex items-start gap-2.5">
                <span
                    class="premium-badge flex size-9 shrink-0 items-center justify-center rounded-lg text-white shadow-sm">
                    <span class="material-symbols-outlined text-[20px]"
                        style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-label-md text-label-md font-bold text-on-surface">{{ $planName }}</span>
                        <span
                            class="inline-flex items-center rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                            Đang dùng
                        </span>
                    </div>
                    <p class="mt-0.5 font-label-sm text-label-sm text-on-surface-variant">
                        {{ $premiumDetail ?: 'Đang mở khóa toàn bộ tính năng' }}
                    </p>
                </div>
            </div>
        </a>
    @else
        <a href="{{ route('subscription.upgrade') }}"
            @if ($closeOnNavigate) @click="menu = false" @endif
            class="premium-gradient flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 font-label-md text-label-md font-semibold text-white shadow-md transition-opacity hover:opacity-90">
            <span class="material-symbols-outlined text-[18px]"
                style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
            Nâng cấp Premium
        </a>
    @endif

    <a href="{{ route('profile.show') }}"
        @if ($closeOnNavigate) @click="menu = false" @endif
        @class([
            'flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors',
            'bg-primary/10 font-semibold text-primary' => request()->routeIs('profile.show'),
            'text-on-surface-variant hover:bg-surface-container-low' => ! request()->routeIs('profile.show'),
        ])>
        <span class="material-symbols-outlined" @if (request()->routeIs('profile.show')) style="font-variation-settings: 'FILL' 1;" @endif>manage_accounts</span>
        <span class="font-body-md text-body-md">Tài khoản</span>
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
