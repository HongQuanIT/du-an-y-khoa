@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' — Reviewer' : 'Reviewer — '.config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-container-lowest font-body-md text-on-surface">
    <header class="border-b border-outline-variant bg-surface px-4 py-4 md:px-8">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4">
            <a href="{{ route('reviewer.dashboard') }}" class="font-headline-sm font-bold text-primary">{{ config('app.name') }} · Reviewer</a>
            <nav class="flex flex-wrap items-center gap-4 font-label-md" aria-label="Menu reviewer">
                <a href="{{ route('reviewer.dashboard') }}" @class(['text-primary' => request()->routeIs('reviewer.dashboard')])>Tổng quan</a>
                <a href="{{ route('reviewer.questions.flags.index') }}" @class(['text-primary' => request()->routeIs('reviewer.questions.*')])>Review câu hỏi</a>
                <a href="{{ route('reviewer.profile.show') }}" @class(['text-primary' => request()->routeIs('reviewer.profile.*')])>Hồ sơ</a>
                <form method="post" action="{{ route('reviewer.logout') }}">@csrf<button type="submit">Đăng xuất</button></form>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-7xl p-4 md:p-8">{{ $slot }}</main>
    @stack('scripts')
</body>
</html>
