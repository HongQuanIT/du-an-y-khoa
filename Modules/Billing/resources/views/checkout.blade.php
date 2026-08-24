@php
    use Modules\Billing\Support\MoneyFormatter;
    $price = $session->planPrice;
@endphp

<x-layouts.app title="Thanh toán" description="Hoàn tất thanh toán gói Premium.">
    <div class="mx-auto max-w-lg px-margin-mobile py-8 md:px-gutter md:py-12">
        <h1 class="font-headline-md text-headline-md text-on-surface">Thanh toán</h1>
        <p class="mt-1 font-body-md text-body-md text-on-surface-variant">
            Phiên {{ \Illuminate\Support\Str::limit($session->uuid, 13, '…') }}
        </p>

        @if (session('error'))
            <div class="mt-4 rounded-lg border border-error/30 bg-error/10 px-4 py-3 font-body-sm text-error">
                {{ session('error') }}
            </div>
        @endif

        <div class="mt-6 space-y-3 rounded-xl border border-outline-variant bg-surface p-5">
            <div class="flex justify-between font-body-md">
                <span class="text-on-surface-variant">Gói</span>
                <span class="text-on-surface">{{ $price?->plan?->name }} — {{ $price?->label }}</span>
            </div>
            <div class="flex justify-between font-body-md">
                <span class="text-on-surface-variant">Tổng</span>
                <span class="font-semibold text-on-surface">{{ MoneyFormatter::vnd($session->totalCents()) }}</span>
            </div>
            <div class="flex justify-between font-body-sm">
                <span class="text-on-surface-variant">Trạng thái</span>
                <span>{{ $session->status }}</span>
            </div>
        </div>

        @if ($session->isPending() && $session->redirect_url)
            <a href="{{ $session->redirect_url }}"
                class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                Tiếp tục thanh toán
            </a>
        @endif

        <a href="{{ route('subscription.upgrade') }}"
            class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
            Chọn gói khác
        </a>
    </div>
</x-layouts.app>
