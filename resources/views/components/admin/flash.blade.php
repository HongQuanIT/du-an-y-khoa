@props(['status' => null])

@php
    $message = $status ?? session('status');
@endphp

@if ($message)
    <div class="mb-4 rounded-xl border border-primary/20 bg-primary-container/40 px-4 py-3 font-body-sm text-body-sm text-on-surface"
        role="status">
        {{ $message }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-error/30 bg-error-container/30 px-4 py-3 font-body-sm text-body-sm text-on-surface"
        role="alert">
        <ul class="list-disc space-y-1 ps-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
