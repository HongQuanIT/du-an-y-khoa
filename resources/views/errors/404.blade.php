@php
    $appName = config('app.name');
    $links = [
        ['label' => 'Trang chủ', 'route' => 'landing.home', 'icon' => 'home'],
        ['label' => 'Tính năng', 'route' => 'landing.features', 'icon' => 'auto_awesome'],
        ['label' => 'Bảng giá', 'route' => 'landing.pricing', 'icon' => 'payments'],
        ['label' => 'FAQ', 'route' => 'landing.faq', 'icon' => 'help'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth light">

<head>
    <meta charset="utf-8">
    <x-theme-init force="light" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, follow">
    <title>Không tìm thấy trang | {{ $appName }}</title>
    <meta name="description" content="Trang bạn tìm không tồn tại hoặc đã được gỡ khỏi {{ $appName }}.">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    class="min-h-screen bg-background text-on-background font-body-md antialiased selection:bg-primary-fixed selection:text-on-primary-fixed">
    <x-public.header />

    <main class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute -top-24 left-1/2 h-72 w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-64 w-64 rounded-full bg-primary-container/20 blur-3xl"></div>
        </div>

        <section class="mx-auto flex min-h-[70vh] max-w-container-max flex-col items-center justify-center px-margin-mobile py-20 text-center md:px-gutter md:py-28">
            <p class="mb-4 font-label-md text-label-md uppercase tracking-[0.2em] text-primary">Lỗi 404</p>
            <p class="font-display text-[72px] leading-none text-primary/20 md:text-[120px]">404</p>
            <h1 class="mt-4 max-w-xl font-headline-lg text-headline-lg text-on-background md:text-[40px] md:leading-tight">
                Trang này không còn ở đây
            </h1>
            <p class="mt-4 max-w-lg font-body-lg text-body-lg text-text-secondary">
                Liên kết có thể đã thay đổi, trang đã ngừng xuất bản, hoặc bạn đã gõ nhầm địa chỉ.
                Quay lại trang chủ hoặc chọn một lối tắt bên dưới.
            </p>

            <div class="mt-10 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-center">
                <a href="{{ route('landing.home') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-8 py-3.5 font-label-md text-label-md text-on-primary shadow-lg transition hover:opacity-90">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                    Về trang chủ
                </a>
                <a href="{{ route('landing.contact') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-surface px-8 py-3.5 font-label-md text-label-md text-on-surface transition hover:bg-surface-container-low">
                    Liên hệ hỗ trợ
                </a>
            </div>

            <div class="mt-14 grid w-full max-w-3xl grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                        class="group flex items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 text-left transition hover:border-primary/40 hover:shadow-sm">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-on-primary">
                            <span class="material-symbols-outlined text-[22px]">{{ $link['icon'] }}</span>
                        </span>
                        <span class="font-label-md text-label-md text-on-surface">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </main>

    <x-public.footer />
</body>

</html>
