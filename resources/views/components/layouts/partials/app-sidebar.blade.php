@php
    $closeOnNavigate = $closeOnNavigate ?? false;
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

<div class="space-y-1 border-t border-outline-variant pt-4">
    <a href="{{ route('landing.pricing') }}"
        class="premium-gradient flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-body-sm font-semibold text-white shadow-md transition-opacity hover:opacity-90">
        <span class="material-symbols-outlined text-body-sm">stars</span>
        Nâng cấp tài khoản
    </a>
    <a href="{{ route('settings.edit') }}"
        @if ($closeOnNavigate) @click="menu = false" @endif
        @class([
            'flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors',
            'bg-primary/10 font-semibold text-primary' => request()->routeIs('settings.*'),
            'text-on-surface-variant hover:bg-surface-container-low' => ! request()->routeIs('settings.*'),
        ])>
        <span class="material-symbols-outlined" @if (request()->routeIs('settings.*')) style="font-variation-settings: 'FILL' 1;" @endif>settings</span>
        <span class="font-body-md text-body-md">Cài đặt</span>
    </a>
    <a href="{{ route('profile.show') }}"
        @if ($closeOnNavigate) @click="menu = false" @endif
        @class([
            'flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors',
            'bg-primary/10 font-semibold text-primary' => request()->routeIs('profile.*'),
            'text-on-surface-variant hover:bg-surface-container-low' => ! request()->routeIs('profile.*'),
        ])>
        <span class="material-symbols-outlined" @if (request()->routeIs('profile.*')) style="font-variation-settings: 'FILL' 1;" @endif>account_circle</span>
        <span class="font-body-md text-body-md">Hồ sơ</span>
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
