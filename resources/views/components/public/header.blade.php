@php
    $navLinks = [
        ['label' => 'Tính năng', 'route' => 'landing.features'],
        ['label' => 'Bảng giá', 'route' => 'landing.pricing'],
        ['label' => 'Về chúng tôi', 'route' => 'landing.about'],
        ['label' => 'Liên hệ', 'route' => 'landing.contact'],
        ['label' => 'FAQ', 'route' => 'landing.faq'],
    ];
@endphp

<nav x-data="{ open: false }"
    class="bg-surface/80 backdrop-blur-md border-b border-border sticky top-0 z-50">
    <div class="flex justify-between items-center w-full px-margin-mobile md:px-gutter max-w-container-max mx-auto h-16">
        <a href="{{ route('landing.home') }}"
            class="text-headline-md font-headline-md font-bold text-primary tracking-tight">{{ config('app.name') }}</a>

        <div class="hidden md:flex items-center gap-8">
            @foreach ($navLinks as $link)
                <a href="{{ route($link['route']) }}"
                    @class([
                        'transition-colors duration-200 font-label-md text-label-md',
                        'text-primary font-bold' => request()->routeIs($link['route']),
                        'text-on-surface-variant hover:text-primary' => !request()->routeIs($link['route']),
                    ])>{{ $link['label'] }}</a>
            @endforeach
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            <a href="{{ route('login') }}"
                class="hidden md:inline-block font-label-md text-label-md text-on-surface-variant hover:text-primary px-4 py-2 transition-colors">Đăng
                nhập</a>
            <a href="{{ route('register') }}"
                class="hidden sm:inline-block bg-primary-container text-on-primary-container px-6 py-2.5 rounded-xl font-label-md text-label-md hover:opacity-90 active:scale-95 transition-all">Đăng
                ký</a>

            <button type="button" @click="open = !open"
                class="md:hidden material-symbols-outlined text-on-surface p-2 rounded-lg hover:bg-surface-container-low transition-colors"
                :aria-expanded="open" aria-label="Mở menu">
                <span x-show="!open">menu</span>
                <span x-show="open" x-cloak>close</span>
            </button>
        </div>
    </div>

    <!-- Mobile drawer -->
    <div x-show="open" x-cloak x-transition.origin.top
        class="md:hidden border-t border-border bg-surface px-margin-mobile py-4 space-y-1">
        @foreach ($navLinks as $link)
            <a href="{{ route($link['route']) }}"
                @class([
                    'block px-3 py-2.5 rounded-lg font-label-md text-label-md transition-colors',
                    'bg-primary-fixed/20 text-primary' => request()->routeIs($link['route']),
                    'text-on-surface-variant hover:bg-surface-container-low' => !request()->routeIs($link['route']),
                ])>{{ $link['label'] }}</a>
        @endforeach
        <div class="pt-3 mt-2 border-t border-border flex flex-col gap-3">
            <a href="{{ route('login') }}"
                class="w-full text-center py-2.5 rounded-xl border border-border font-label-md text-label-md text-on-surface hover:bg-surface-container-low transition-colors">Đăng
                nhập</a>
            <a href="{{ route('register') }}"
                class="w-full text-center py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md hover:opacity-90 transition-all">Đăng
                ký</a>
        </div>
    </div>
</nav>
