@props([
    'feature' => null,
    'title' => 'Tính năng Premium',
    'message' => 'Nâng cấp để mở khóa toàn bộ QBank, thư viện, AI Mentor và mô phỏng thi.',
])

@php
    use Modules\Billing\Support\CheckoutIntent;

    $entitlement = $feature ?? session('paywall');
    $featuredPriceId = CheckoutIntent::featuredPremiumPriceId();
    $upgradeHref = auth()->check()
        ? CheckoutIntent::upgradeUrl($featuredPriceId)
        : CheckoutIntent::registerUrl($featuredPriceId);
@endphp

@if ($entitlement)
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-on-surface/40 p-4 sm:items-center"
        role="dialog" aria-modal="true" aria-labelledby="paywall-title"
        x-data="{ open: true }" x-show="open" x-cloak>
        <div class="w-full max-w-md rounded-xl border border-outline-variant bg-surface p-6 shadow-lg"
            @click.outside="open = false">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                <span class="material-symbols-outlined text-primary">lock</span>
            </div>
            <h2 id="paywall-title" class="font-headline-sm text-headline-sm text-on-surface">{{ $title }}</h2>
            <p class="mt-2 font-body-md text-body-md text-on-surface-variant">{{ $message }}</p>
            @if (is_string($entitlement))
                <p class="mt-2 font-label-sm text-label-sm text-on-surface-variant">Yêu cầu: {{ $entitlement }}</p>
            @endif
            <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                <a href="{{ $upgradeHref }}"
                    class="inline-flex flex-1 items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                    Nâng cấp ngay
                </a>
                <a href="{{ route('landing.pricing') }}"
                    class="inline-flex flex-1 items-center justify-center rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
                    Xem bảng giá
                </a>
            </div>
            <button type="button" class="mt-4 w-full font-label-sm text-on-surface-variant hover:underline"
                @click="open = false">Đóng</button>
        </div>
    </div>
@endif
