@php
    use Modules\Billing\Support\MoneyFormatter;
@endphp

<x-layouts.app title="Thanh toán thử" description="Mô phỏng cổng thanh toán — chỉ dùng trong môi trường phát triển và kiểm thử.">
    <div class="mx-auto max-w-md px-margin-mobile py-8 md:px-gutter md:py-12">
        <div class="rounded-xl border border-dashed border-outline-variant bg-surface-container-low/50 p-6">
            <p class="font-label-sm text-label-sm uppercase tracking-wide text-on-surface-variant">Cổng thanh toán thử</p>
            <h1 class="mt-2 font-headline-sm text-headline-sm text-on-surface">Mô phỏng thanh toán</h1>
            <p class="mt-2 font-body-md text-body-md text-on-surface-variant">
                {{ $session->planPrice?->plan?->name }} — {{ $session->planPrice?->label }}
            </p>
            <p class="mt-4 font-headline-md text-headline-md text-on-surface">
                {{ MoneyFormatter::vnd($session->totalCents()) }}
            </p>

            <form method="post" action="{{ route('billing.fake-pay.complete', $session->uuid) }}" class="mt-6 space-y-3">
                @csrf
                <input type="hidden" name="success" value="1">
                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                    Thanh toán thành công
                </button>
            </form>
            <form method="post" action="{{ route('billing.fake-pay.complete', $session->uuid) }}" class="mt-2">
                @csrf
                <input type="hidden" name="success" value="0">
                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
                    Mô phỏng thất bại
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
