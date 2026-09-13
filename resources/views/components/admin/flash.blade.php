@props(['status' => null, 'except' => []])

@php
    $message = $status ?? session('status');
    $exceptKeys = array_fill_keys(array_map('strval', (array) $except), true);
    $bannerErrors = collect($errors->getMessages())
        ->reject(fn ($messages, $key) => isset($exceptKeys[(string) $key]))
        ->flatten()
        ->filter()
        ->values();
@endphp

@if ($message)
    <div class="mb-4 rounded-xl border border-primary/20 bg-primary-container/40 px-4 py-3 font-body-sm text-body-sm text-on-surface"
        role="status">
        {{ $message }}
    </div>
@endif

@if ($bannerErrors->isNotEmpty())
    <div class="mb-4 rounded-xl border border-error/30 bg-error-container/30 px-4 py-3 font-body-sm text-body-sm text-on-surface"
        role="alert">
        <ul class="list-disc space-y-1 ps-4">
            @foreach ($bannerErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
