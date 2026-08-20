<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">
<head>
    <meta charset="utf-8">
    <x-theme-init />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo trì — {{ $siteName }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface-container-lowest font-body-md text-on-surface">
    <main class="flex min-h-screen items-center justify-center px-margin-mobile py-12">
        <section class="w-full max-w-xl rounded-xl border border-outline-variant bg-surface p-6 text-center shadow-sm">
            <span class="material-symbols-outlined mx-auto mb-4 block text-[44px] text-primary">construction</span>
            <h1 class="font-headline-md text-headline-md text-on-surface">{{ $siteName }} đang bảo trì</h1>
            <p class="mt-3 font-body-md text-body-md text-on-surface-variant">
                Hệ thống đang được cập nhật. Vui lòng quay lại sau ít phút.
            </p>
            @if ($supportEmail || $supportHotline)
                <div class="mt-6 flex flex-col items-center justify-center gap-2 font-label-md text-label-md text-primary sm:flex-row sm:gap-4">
                    @if ($supportEmail)
                        <a href="mailto:{{ $supportEmail }}" class="hover:underline">{{ $supportEmail }}</a>
                    @endif
                    @if ($supportHotline)
                        <a href="tel:{{ preg_replace('/\s+/', '', $supportHotline) }}" class="hover:underline">{{ $supportHotline }}</a>
                    @endif
                </div>
            @endif
        </section>
    </main>
</body>
</html>
