@props([
    'title' => null,
    'description' => 'Học hiệu quả hơn — hiểu bản chất, nhớ lâu, luyện thi đúng trọng tâm với ngân hàng câu hỏi chuẩn hóa và AI Tutor.',
    'seo' => null,
])

@php
    /** @var array<string, mixed>|null $seo */
    $documentTitle = $seo['document_title'] ?? ($title ? $title.' | '.config('app.name') : config('app.name'));
    $metaDescription = $seo['description'] ?? $description;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth light">

<head>
    <meta charset="utf-8">
    <x-theme-init force="light" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $documentTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">

    @if ($seo)
        @if (! empty($seo['keywords']))
            <meta name="keywords" content="{{ $seo['keywords'] }}">
        @endif
        @if (! empty($seo['robots']))
            <meta name="robots" content="{{ $seo['robots'] }}">
            <meta name="googlebot" content="{{ $seo['robots'] }}">
        @endif
        @if (! empty($seo['canonical']))
            <link rel="canonical" href="{{ $seo['canonical'] }}">
        @endif

        <meta property="og:type" content="{{ $seo['og_type'] ?? 'website' }}">
        <meta property="og:site_name" content="{{ $seo['og_site_name'] ?? config('app.name') }}">
        <meta property="og:title" content="{{ $seo['og_title'] ?? $documentTitle }}">
        <meta property="og:description" content="{{ $seo['og_description'] ?? $metaDescription }}">
        <meta property="og:url" content="{{ $seo['og_url'] ?? url()->current() }}">
        <meta property="og:locale" content="{{ $seo['og_locale'] ?? str_replace('_', '-', app()->getLocale()) }}">
        @if (! empty($seo['og_image']))
            <meta property="og:image" content="{{ $seo['og_image'] }}">
            <meta property="og:image:alt" content="{{ $seo['og_title'] ?? $documentTitle }}">
        @endif

        <meta name="twitter:card" content="{{ $seo['twitter_card'] ?? 'summary' }}">
        <meta name="twitter:title" content="{{ $seo['twitter_title'] ?? ($seo['og_title'] ?? $documentTitle) }}">
        <meta name="twitter:description" content="{{ $seo['twitter_description'] ?? ($seo['og_description'] ?? $metaDescription) }}">
        @if (! empty($seo['twitter_image']))
            <meta name="twitter:image" content="{{ $seo['twitter_image'] }}">
        @endif

        @foreach ($seo['json_ld'] ?? [] as $graph)
            <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endforeach
    @endif

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
