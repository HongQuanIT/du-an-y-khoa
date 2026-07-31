@props([
    'title' => null,
    'description' => 'Nền tảng ôn luyện y khoa thông minh dành cho bác sĩ tương lai.',
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

<body class="min-h-screen bg-background text-on-surface font-body-md antialiased">
    {{ $slot }}

    @livewireScriptConfig
</body>

</html>
