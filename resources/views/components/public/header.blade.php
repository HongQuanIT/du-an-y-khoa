@php
    $navLinks = [
        ['label' => 'Tính năng', 'route' => 'landing.features'],
        ['label' => 'Bảng giá', 'route' => 'landing.pricing'],
        ['label' => 'Về chúng tôi', 'route' => 'landing.about'],
        ['label' => 'Liên hệ', 'route' => 'landing.contact'],
        ['label' => 'FAQ', 'route' => 'landing.faq'],
    ];
@endphp

<nav class="sticky top-0 z-50 border-b border-border bg-surface/80 backdrop-blur-md">
    <div
        class="mx-auto flex h-16 w-full max-w-container-max items-center justify-between px-margin-mobile md:px-gutter">
        <a href="{{ route('landing.home') }}"
            class="font-headline-md text-headline-md font-bold tracking-tight text-primary">{{ config('app.name') }}</a>

        <div class="hidden items-center gap-8 md:flex">
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
                class="hidden px-4 py-2 font-label-md text-label-md text-on-surface-variant transition-colors hover:text-primary md:inline-block">Đăng
                nhập</a>
            <a href="{{ route('register') }}"
                class="hidden rounded-xl bg-primary-container px-6 py-2.5 font-label-md text-label-md text-on-primary-container transition-all hover:opacity-90 active:scale-95 sm:inline-block">Đăng
                ký</a>
        </div>
    </div>
</nav>
