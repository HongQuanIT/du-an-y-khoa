@php
    use Modules\Billing\Support\MoneyFormatter;
@endphp

<x-layouts.app title="Nâng cấp Premium" description="Chọn gói và thanh toán để mở khóa toàn bộ tính năng.">
    <div class="mx-auto max-w-4xl px-margin-mobile py-8 md:px-gutter md:py-12">
        <div class="mb-8">
            <p class="font-label-md text-label-md text-primary">
                <a href="{{ route('subscription.show') }}" class="hover:underline">← Gói đăng ký</a>
            </p>
            <h1 class="mt-2 font-headline-md text-headline-md text-on-surface">Nâng cấp Premium</h1>
            <p class="mt-1 font-body-md text-body-md text-on-surface-variant">
                Chọn thời hạn phù hợp. Thanh toán an toàn qua cổng đối tác — không lưu thẻ trên hệ thống.
            </p>
        </div>

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-error/30 bg-error/10 px-4 py-3 font-body-sm text-error">
                {{ session('error') }}
            </div>
        @endif

        @if ($selectedPlanPriceId)
            <div class="mb-6 rounded-xl border border-primary/25 bg-primary/5 px-5 py-4 font-body-sm text-on-surface">
                Bạn đã chọn gói từ bảng giá.
                Xác nhận bên dưới rồi bấm <strong>Thanh toán</strong> để tiếp tục.
            </div>
        @endif

        @if ($current['is_free'] === false)
            <div class="mb-6 rounded-xl border border-primary/20 bg-primary/5 px-5 py-4 font-body-sm text-on-surface">
                Đang dùng <strong>{{ $current['plan_name'] }}</strong>
                @if ($current['ends_at'])
                    — hết hạn {{ $current['ends_at']->locale('vi')->isoFormat('D MMMM YYYY') }}.
                    Gia hạn sẽ cộng dồn thời gian còn lại.
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($prices as $price)
                @php
                    $isSelected = $selectedPlanPriceId !== null && (int) $price->id === (int) $selectedPlanPriceId;
                    $isHighlighted = $isSelected || ($selectedPlanPriceId === null && $price->is_featured);
                @endphp
                <article
                    class="flex flex-col rounded-xl border {{ $isHighlighted ? 'border-primary ring-1 ring-primary' : 'border-outline-variant' }} bg-surface p-5 shadow-sm"
                    @if ($isSelected) id="selected-plan" @endif>
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-title-md text-title-md text-on-surface">{{ $price->label }}</h2>
                            @if ($price->badge_label)
                                <span class="mt-1 inline-block font-label-sm text-label-sm text-primary">{{ $price->badge_label }}</span>
                            @endif
                            @if ($isSelected)
                                <span class="mt-1 block font-label-sm text-label-sm font-semibold text-primary">Đã chọn</span>
                            @endif
                        </div>
                        @if ($price->displaySavingsPercent())
                            <span class="rounded-md bg-primary/10 px-2 py-1 font-label-sm text-label-sm font-semibold text-primary">
                                -{{ $price->displaySavingsPercent() }}%
                            </span>
                        @endif
                    </div>

                    <p class="font-headline-sm text-headline-sm text-on-surface">
                        {{ MoneyFormatter::vnd($price->price_cents) }}
                    </p>
                    @if ($price->listPriceCents() && $price->listPriceCents() > $price->price_cents)
                        <p class="font-body-sm text-body-sm text-on-surface-variant line-through">
                            {{ MoneyFormatter::vnd($price->listPriceCents()) }}
                        </p>
                    @endif

                    <a href="{{ route('billing.payment-methods', $price) }}"
                        class="mt-auto pt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg {{ $isSelected ? 'bg-primary text-on-primary' : 'bg-primary px-4 py-2.5 text-on-primary' }} px-4 py-2.5 font-label-md text-label-md font-semibold hover:opacity-90">
                        Đăng ký gói
                    </a>
                </article>
            @endforeach
        </div>

        <p class="mt-8 text-center font-body-sm text-body-sm text-on-surface-variant">
            Có mã kích hoạt?
            <a href="{{ route('profile.show', ['tab' => 'redeem']) }}" class="text-primary hover:underline">Đổi mã</a>
        </p>
    </div>

    @if ($selectedPlanPriceId)
        <script>
            document.getElementById('selected-plan')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        </script>
    @endif
</x-layouts.app>
