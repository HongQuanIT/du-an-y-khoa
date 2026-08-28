@php
    use Modules\Billing\Support\MoneyFormatter;

    $planName = $price->plan?->name ?? 'Premium';
    $taxRate = (float) config('billing.tax_rate', 0);
    $subtotalCents = (int) $price->price_cents;
    $taxCents = (int) round($subtotalCents * $taxRate);
    $totalCents = $subtotalCents + $taxCents;

    $methods = [
        [
            'key' => 'vnpay',
            'name' => 'VNPay',
            'description' => 'Thanh toán qua VNPay bằng QR, ATM nội địa hoặc thẻ quốc tế.',
            'icon' => 'account_balance',
            'active' => $vnpayReady,
        ],
        [
            'key' => 'momo',
            'name' => 'Ví MoMo',
            'description' => 'Thanh toán nhanh bằng ứng dụng MoMo.',
            'icon' => 'account_balance_wallet',
            'active' => false,
        ],
        [
            'key' => 'zalopay',
            'name' => 'ZaloPay',
            'description' => 'Thanh toán bằng ví điện tử ZaloPay.',
            'icon' => 'payments',
            'active' => false,
        ],
        [
            'key' => 'bank_qr',
            'name' => 'QR ngân hàng',
            'description' => 'Quét mã bằng ứng dụng ngân hàng hỗ trợ VietQR.',
            'icon' => 'qr_code_2',
            'active' => false,
        ],
    ];
@endphp

<x-layouts.app
    title="Thanh toán gói {{ $planName }}"
    description="Kiểm tra đơn hàng và chọn phương thức thanh toán an toàn cho gói {{ $planName }} trên MedLearn.">
    <div class="mx-auto max-w-6xl px-margin-mobile py-6 md:px-gutter md:py-10">
        <nav aria-label="Điều hướng thanh toán" class="text-sm text-on-surface-variant">
            <ol class="flex flex-wrap items-center gap-2">
                <li>
                    <a href="{{ route('subscription.show') }}" class="hover:text-primary hover:underline">Gói đăng ký</a>
                </li>
                <li aria-hidden="true">/</li>
                <li>
                    <a href="{{ route('subscription.upgrade', ['plan_price_id' => $price->id]) }}"
                        class="hover:text-primary hover:underline">Chọn gói</a>
                </li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="font-semibold text-on-surface">Thanh toán</li>
            </ol>
        </nav>

        <header class="mt-5 border-b border-outline-variant pb-6">
            <p class="font-label-md font-semibold uppercase tracking-wide text-primary">Cổng thanh toán MedLearn</p>
            <h1 class="mt-1 font-headline-md text-headline-md font-bold text-on-surface">
                Xác nhận đơn hàng và phương thức thanh toán
            </h1>
            <p class="mt-2 max-w-3xl text-on-surface-variant">
                Kiểm tra thông tin gói trước khi tiếp tục. Dữ liệu thanh toán được nhập và xử lý trực tiếp tại cổng đối tác; MedLearn không lưu số thẻ hoặc tài khoản ngân hàng.
            </p>
        </header>

        <section aria-labelledby="checkout-progress-title" class="mt-6">
            <h2 id="checkout-progress-title" class="sr-only">Tiến trình thanh toán</h2>
            <ol class="grid gap-2 text-sm sm:grid-cols-3">
                <li class="flex items-center gap-3 rounded-lg border border-outline-variant bg-surface px-4 py-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary">1</span>
                    <span><strong class="block text-on-surface">Chọn gói</strong><span class="text-on-surface-variant">Đã hoàn thành</span></span>
                </li>
                <li aria-current="step" class="flex items-center gap-3 rounded-lg border border-primary bg-primary/5 px-4 py-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary">2</span>
                    <span><strong class="block text-on-surface">Xác nhận và thanh toán</strong><span class="text-on-surface-variant">Đang thực hiện</span></span>
                </li>
                <li class="flex items-center gap-3 rounded-lg border border-outline-variant bg-surface px-4 py-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-container text-xs font-bold text-on-surface-variant">3</span>
                    <span><strong class="block text-on-surface">Hoàn tất</strong><span class="text-on-surface-variant">Kích hoạt gói</span></span>
                </li>
            </ol>
        </section>

        @if (session('error'))
            <div class="mt-5 rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
                {{ session('error') }}
            </div>
        @endif

        @error('gateway')
            <div class="mt-5 rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
                {{ $message }}
            </div>
        @enderror

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside aria-labelledby="order-summary-title" class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm lg:sticky lg:top-24">
                <h2 id="order-summary-title" class="font-title-md font-semibold text-on-surface">Thông tin đơn hàng</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Sản phẩm</dt>
                        <dd class="text-right font-semibold text-on-surface">{{ $planName }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Gói đăng ký</dt>
                        <dd class="text-right text-on-surface">{{ $price->label }}</dd>
                    </div>
                    @if ($price->duration_days)
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Thời hạn sử dụng</dt>
                            <dd class="text-on-surface">{{ $price->duration_days }} ngày</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Tạm tính</dt>
                        <dd class="text-on-surface">{{ MoneyFormatter::vnd($subtotalCents) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Thuế</dt>
                        <dd class="text-on-surface">{{ $taxCents > 0 ? MoneyFormatter::vnd($taxCents) : '0đ' }}</dd>
                    </div>
                    <div class="flex items-end justify-between gap-4 border-t border-outline-variant pt-4">
                        <dt class="font-semibold text-on-surface">Tổng thanh toán</dt>
                        <dd class="font-headline-sm text-headline-sm font-bold text-primary">{{ MoneyFormatter::vnd($totalCents) }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs leading-5 text-on-surface-variant">
                    Gói được kích hoạt sau khi cổng thanh toán xác nhận giao dịch thành công. Thời hạn còn lại của cùng gói sẽ được cộng dồn.
                </p>
                <div class="mt-4 flex items-start gap-2 rounded-lg bg-primary/5 p-3 text-xs leading-5 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[18px] text-primary">lock</span>
                    <span>Kết nối được mã hóa. MedLearn chỉ nhận trạng thái và mã giao dịch từ đối tác thanh toán.</span>
                </div>

                <a href="{{ route('subscription.upgrade', ['plan_price_id' => $price->id]) }}"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-outline-variant px-4 py-2.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                    Chọn gói khác
                </a>
            </aside>

            <section aria-labelledby="payment-method-title" class="min-w-0">
                <div>
                    <h2 id="payment-method-title" class="font-title-lg text-title-lg font-bold text-on-surface">Phương thức thanh toán</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Hiện tại hệ thống hỗ trợ thanh toán trực tuyến qua VNPay.</p>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($methods as $method)
                        @if ($method['key'] === 'vnpay')
                            <form method="post" action="{{ route('billing.checkout.store') }}"
                                aria-labelledby="payment-vnpay-title"
                                class="flex min-h-64 flex-col rounded-xl border p-5 {{ $method['active'] ? 'border-primary bg-primary/5 shadow-sm' : 'border-outline-variant bg-surface opacity-70' }} sm:col-span-2">
                                @csrf
                                <input type="hidden" name="plan_price_id" value="{{ $price->id }}">
                                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                                <input type="hidden" name="gateway" value="vnpay">

                                <div class="flex items-start justify-between gap-3">
                                    <span class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined text-[26px]">{{ $method['icon'] }}</span>
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $method['active'] ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant' }}">
                                        {{ $method['active'] ? 'Khả dụng' : 'Chưa cấu hình' }}
                                    </span>
                                </div>
                                <h3 id="payment-vnpay-title" class="mt-4 font-title-md font-bold text-on-surface">{{ $method['name'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-on-surface-variant">{{ $method['description'] }}</p>
                                <ul class="mt-3 grid gap-1 text-sm text-on-surface-variant sm:grid-cols-3">
                                    <li class="flex items-center gap-1"><span class="material-symbols-outlined text-[17px] text-primary" aria-hidden="true">check</span>QR VNPay</li>
                                    <li class="flex items-center gap-1"><span class="material-symbols-outlined text-[17px] text-primary" aria-hidden="true">check</span>Thẻ ATM nội địa</li>
                                    <li class="flex items-center gap-1"><span class="material-symbols-outlined text-[17px] text-primary" aria-hidden="true">check</span>Thẻ quốc tế</li>
                                </ul>

                                <label class="mt-5 flex items-start gap-3 rounded-lg border border-outline-variant bg-surface px-3 py-3 text-sm text-on-surface">
                                    <input type="checkbox" name="payment_terms" value="1" required
                                        class="mt-0.5 size-4 rounded border-outline text-primary focus:ring-primary">
                                    <span>
                                        Tôi đã kiểm tra đơn hàng và đồng ý với
                                        <a href="{{ route('landing.terms') }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">điều khoản sử dụng</a>
                                        cùng
                                        <a href="{{ route('landing.privacy') }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">chính sách bảo mật</a>.
                                    </span>
                                </label>
                                <button type="submit" @disabled(! $method['active'])
                                    class="mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-40">
                                    Xác nhận và thanh toán {{ MoneyFormatter::vnd($totalCents) }}
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                                </button>
                                <p class="mt-2 text-center text-xs text-on-surface-variant">Bạn sẽ được chuyển sang trang thanh toán bảo mật của VNPay.</p>
                            </form>
                        @else
                            <article aria-disabled="true" class="flex min-h-48 flex-col rounded-xl border border-outline-variant bg-surface p-5 opacity-60">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="flex size-12 items-center justify-center rounded-2xl bg-surface-container text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[26px]">{{ $method['icon'] }}</span>
                                    </span>
                                    <span class="rounded-full bg-surface-container px-2.5 py-1 text-xs font-semibold text-on-surface-variant">
                                        Sắp hỗ trợ
                                    </span>
                                </div>
                                <h3 class="mt-4 font-title-md font-bold text-on-surface">{{ $method['name'] }}</h3>
                                <p class="mt-1 text-sm leading-5 text-on-surface-variant">{{ $method['description'] }}</p>
                                <button type="button" disabled
                                    class="mt-auto inline-flex h-11 cursor-not-allowed items-center justify-center rounded-xl border border-outline-variant px-4 text-sm font-semibold text-on-surface-variant">
                                    Chưa hỗ trợ
                                </button>
                            </article>
                        @endif
                    @endforeach
                </div>

                <section aria-labelledby="payment-help-title" class="mt-6 rounded-xl border border-outline-variant bg-surface p-5">
                    <h2 id="payment-help-title" class="font-title-md font-semibold text-on-surface">Sau khi bấm thanh toán</h2>
                    <ol class="mt-3 space-y-2 text-sm leading-6 text-on-surface-variant">
                        <li><strong class="text-on-surface">1.</strong> Hệ thống tạo một phiên thanh toán có thời hạn và chuyển bạn sang VNPay.</li>
                        <li><strong class="text-on-surface">2.</strong> Bạn hoàn tất giao dịch trên VNPay, sau đó được đưa trở lại MedLearn.</li>
                        <li><strong class="text-on-surface">3.</strong> Khi giao dịch được xác nhận, gói được kích hoạt và hóa đơn xuất hiện trong tài khoản.</li>
                    </ol>
                    <p class="mt-3 text-sm text-on-surface-variant">
                        Không đóng trình duyệt trong lúc chuyển trang. Nếu đã bị trừ tiền nhưng gói chưa kích hoạt, hãy kiểm tra lại sau vài phút hoặc liên hệ hỗ trợ và cung cấp mã giao dịch.
                    </p>
                </section>
            </section>
        </div>
    </div>
</x-layouts.app>
