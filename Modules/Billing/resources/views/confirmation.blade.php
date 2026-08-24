@php
    use Modules\Billing\Support\MoneyFormatter;
@endphp

<x-layouts.app title="Thanh toán thành công" description="Premium đã được kích hoạt.">
    <div class="mx-auto max-w-lg px-margin-mobile py-8 md:px-gutter md:py-12 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
            <span class="material-symbols-outlined text-[32px] text-primary">check_circle</span>
        </div>
        <h1 class="font-headline-md text-headline-md text-on-surface">Thanh toán thành công</h1>
        <p class="mt-2 font-body-md text-body-md text-on-surface-variant">
            @if ($session->isCompleted())
                Gói {{ $session->planPrice?->plan?->name }} ({{ $session->planPrice?->label }}) đã được kích hoạt.
            @else
                Đang xác nhận thanh toán… Nếu đã trừ tiền, Premium sẽ mở trong vài phút.
            @endif
        </p>

        @if ($current['ends_at'])
            <p class="mt-4 font-body-sm text-body-sm text-on-surface-variant">
                Hết hạn: {{ $current['ends_at']->locale('vi')->isoFormat('D MMMM YYYY') }}
            </p>
        @endif

        @if ($session->invoice)
            <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                Hóa đơn {{ $session->invoice->number }}
                · {{ MoneyFormatter::vnd((int) $session->invoice->amount_cents) }}
            </p>
        @endif

        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ route('subscription.show') }}"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                Xem gói của tôi
            </a>
            <a href="{{ url('/') }}"
                class="inline-flex items-center justify-center rounded-lg border border-outline-variant px-5 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
                Về trang chủ
            </a>
        </div>
    </div>
</x-layouts.app>
