@props([
    'title' => null,
    'description' => 'Học hiệu quả hơn — hiểu bản chất, nhớ lâu, luyện thi đúng trọng tâm với ngân hàng câu hỏi chuẩn hóa và AI Tutor.',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title ? $title . ' | ' . config('app.name') : config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body
    class="min-h-screen bg-background text-on-background font-body-md antialiased selection:bg-primary-fixed selection:text-on-primary-fixed">
    <x-public.header />

    <main>
        {{ $slot }}
    </main>

    <x-public.footer />
    <x-public.cookie-banner />

    @livewireScriptConfig
</body>

</html>
