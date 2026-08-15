@php
    use Modules\Billing\Support\MoneyFormatter;

    $freeFeatures = $free?->features ?? ['20 câu hỏi/ngày', 'Thư viện giới hạn'];
    $premiumFeatures = $premium?->features ?? [];
    $monthlyAmount = $monthlyPrice?->price_cents ?? 199_000;
    $defaultYears = $yearlyForAlpine->keys()->max() ?? 1;
    $isAuthenticated = auth()->check();
    $isCurrentFree = $current['is_free'] ?? true;
    $isCurrentPremium = ! ($current['is_free'] ?? true) && ($current['plan_slug'] ?? 'free') === 'premium';

    $ctaForTier = function (string $tierSlug, string $defaultLabel) use ($isAuthenticated, $isCurrentFree, $isCurrentPremium): array {
        if ($tierSlug === 'free' && $isCurrentFree) {
            return ['label' => 'Gói của bạn', 'disabled' => true, 'href' => null];
        }
        if ($tierSlug === 'premium' && $isCurrentPremium) {
            return ['label' => 'Gói của bạn', 'disabled' => true, 'href' => null];
        }
        if (! $isAuthenticated) {
            return ['label' => $defaultLabel, 'disabled' => false, 'href' => route('register')];
        }

        return ['label' => $defaultLabel, 'disabled' => false, 'href' => route('subscription.show')];
    };

    $freeCta = $ctaForTier('free', $free?->prices->first()?->cta_label ?? 'Bắt đầu ngay');
    $monthlyCta = $ctaForTier('premium', $monthlyPrice?->cta_label ?? 'Nâng cấp theo tháng');
@endphp

<x-layouts.public :seo="$seo">
    <div class="w-full max-w-container-max mx-auto px-margin-mobile md:px-gutter py-16"
        x-data="{
            years: {{ $defaultYears }},
            plans: @js($yearlyForAlpine),
            monthlyPrice: {{ $monthlyAmount }},
            format(n) {
                return new Intl.NumberFormat('vi-VN').format(n) + '₫';
            },
            get plan() {
                return this.plans[this.years] ?? Object.values(this.plans)[0];
            },
            listPrice() {
                return this.plan?.listPrice ?? 0;
            },
        }">
        @if ($isAuthenticated && ! $isCurrentFree)
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-center font-body-sm text-on-surface">
                Bạn đang dùng <strong>{{ $current['plan_name'] }}</strong>
                @if ($current['price_label'])
                    ({{ $current['price_label'] }})
                @endif
                · <a href="{{ route('subscription.show') }}" class="font-medium text-primary hover:underline">Xem chi tiết gói</a>
            </div>
        @endif

        <section class="text-center mb-10 md:mb-12">
            <h1 class="font-headline-lg text-headline-lg mb-4 text-on-surface">Chọn gói phù hợp với bạn</h1>
            <p class="text-text-secondary font-body-lg text-body-lg max-w-2xl mx-auto">
                Tăng tốc hành trình y khoa của bạn với các công cụ ôn thi chuyên sâu và AI hỗ trợ thông minh.
            </p>
        </section>

        @if ($yearlyForAlpine->isNotEmpty())
            <div class="flex flex-col items-center gap-3 mb-12 md:mb-16">
                <p class="font-label-sm text-label-sm text-on-surface-variant">Xem bảng giá theo năm</p>
                <div class="relative inline-flex p-1 pt-3 rounded-2xl bg-surface-container-low border border-border" role="tablist"
                    aria-label="Chọn thời hạn gói năm">
                    @foreach ($yearlyForAlpine->keys()->sort() as $y)
                        <button type="button" role="tab"
                            @click="years = {{ $y }}"
                            :aria-selected="years === {{ $y }}"
                            :class="years === {{ $y }}
                                ? 'bg-surface text-primary shadow-sm border border-border'
                                : 'text-on-surface-variant hover:text-on-surface border border-transparent'"
                            class="relative min-w-[5.5rem] sm:min-w-[6.5rem] px-4 py-2.5 rounded-xl font-label-md text-label-md transition-all">
                            {{ $yearlyForAlpine[$y]['label'] ?? $y.' năm' }}
                            @if (($yearlyForAlpine[$y]['badge'] ?? null) === 'Tiết kiệm nhất')
                                <span
                                    class="absolute -top-3.5 left-1/2 -translate-x-1/2 premium-badge px-2 py-0.5 rounded-full text-white text-[10px] font-bold whitespace-nowrap leading-none pointer-events-none">
                                    Tiết kiệm nhất
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-24 relative items-start">
            {{-- Free --}}
            <div @class([
                'bg-surface border p-8 rounded-xl flex flex-col hover:shadow-lg transition-shadow',
                'border-primary ring-2 ring-primary/20' => $isCurrentFree,
                'border-border' => ! $isCurrentFree,
            ])>
                @if ($isCurrentFree)
                    <span class="mb-3 inline-flex w-fit items-center rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-semibold text-primary">
                        Gói của bạn
                    </span>
                @endif
                <div class="mb-8">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">{{ $free?->name ?? 'Miễn phí' }}</h3>
                    <p class="text-text-secondary font-body-sm text-body-sm">{{ $free?->description ?? 'Cơ bản cho người mới bắt đầu' }}</p>
                </div>
                <div class="mb-8">
                    <span class="text-headline-lg font-bold">0₫</span>
                    <span class="text-text-secondary">/tháng</span>
                </div>
                <ul class="space-y-4 mb-12 flex-grow">
                    @foreach ($freeFeatures as $feature)
                        <li class="flex items-center gap-3 font-body-sm text-body-sm">
                            <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
                @if ($freeCta['disabled'])
                    <span class="w-full py-3 px-4 border border-primary/30 bg-primary/5 text-primary font-label-md text-label-md rounded-xl text-center cursor-default">
                        {{ $freeCta['label'] }}
                    </span>
                @else
                    <a href="{{ $freeCta['href'] }}"
                        class="w-full py-3 px-4 border border-border text-on-surface font-label-md text-label-md rounded-xl hover:bg-surface-container-low transition-colors text-center">
                        {{ $freeCta['label'] }}
                    </a>
                @endif
            </div>

            {{-- Premium yearly --}}
            @if ($yearlyForAlpine->isNotEmpty())
                <div @class([
                    'bg-surface premium-border p-8 rounded-xl flex flex-col relative shadow-2xl md:scale-105 z-10',
                    'ring-2 ring-primary/30' => $isCurrentPremium,
                ])>
                    @if ($isCurrentPremium)
                        <span class="absolute -top-4 right-4 inline-flex items-center rounded-full bg-primary px-3 py-1 font-label-sm text-label-sm font-semibold text-on-primary">
                            Gói của bạn
                        </span>
                    @endif
                    <div
                        class="absolute -top-4 left-1/2 -translate-x-1/2 premium-badge px-4 py-1 rounded-full text-white text-label-sm font-bold flex items-center gap-1 whitespace-nowrap">
                        <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                        <span x-text="plan?.badge ?? 'Giá theo năm'"></span>
                    </div>
                    <div class="mb-8 mt-2">
                        <h3 class="font-headline-sm text-headline-sm text-primary mb-2">
                            {{ $premium?->name ?? 'Premium' }} <span x-text="plan?.label"></span>
                        </h3>
                        <p class="text-text-secondary font-body-sm text-body-sm">Giải pháp ôn thi toàn diện · thanh toán một lần</p>
                    </div>
                    <div class="mb-2">
                        <span class="text-headline-lg font-bold" x-text="format(plan?.total ?? 0)"></span>
                        <span class="text-text-secondary">/<span x-text="plan?.label"></span></span>
                    </div>
                    <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant line-through"
                        x-show="plan?.total" x-text="'Giá lẻ: ' + format(listPrice())"></p>
                    <div class="mb-8 p-3 bg-primary-fixed/20 rounded-lg" x-show="plan?.perMonth">
                        <p class="text-primary font-label-md text-label-md">
                            Chỉ ~<span x-text="format(plan.perMonth)"></span>/tháng · tiết kiệm <span x-text="plan.save"></span>%
                        </p>
                    </div>
                    <ul class="space-y-4 mb-12 flex-grow">
                        @foreach ($premiumFeatures as $feature)
                            <li class="flex items-center gap-3 font-body-sm text-body-sm @if($loop->first) font-medium @endif">
                                <span class="material-symbols-outlined text-success text-[20px]" @if($loop->first) style="font-variation-settings: 'FILL' 1;" @endif>check_circle</span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    @if ($isCurrentPremium)
                        <span class="w-full py-3 px-4 bg-primary/10 text-primary font-label-md text-label-md rounded-xl text-center cursor-default">
                            Gói của bạn
                        </span>
                    @else
                        <a href="{{ $isAuthenticated ? route('subscription.show') : route('register') }}"
                            class="w-full py-3 px-4 bg-primary text-on-primary font-label-md text-label-md rounded-xl hover:opacity-90 transition-opacity text-center"
                            x-text="plan?.cta ?? 'Mua gói'">
                        </a>
                    @endif
                </div>
            @endif

            {{-- Premium monthly --}}
            @if ($monthlyPrice)
                <div @class([
                    'bg-surface border p-8 rounded-xl flex flex-col hover:shadow-lg transition-shadow',
                    'border-primary ring-2 ring-primary/20' => $isCurrentPremium && ($current['price_label'] ?? '') === $monthlyPrice->label,
                    'border-border' => ! ($isCurrentPremium && ($current['price_label'] ?? '') === $monthlyPrice->label),
                ])>
                    <div class="mb-8">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">{{ $premium?->name ?? 'Premium' }} {{ $monthlyPrice->label }}</h3>
                        <p class="text-text-secondary font-body-sm text-body-sm">Linh hoạt theo từng giai đoạn</p>
                    </div>
                    <div class="mb-8">
                        <span class="text-headline-lg font-bold">{{ MoneyFormatter::vnd($monthlyPrice->price_cents) }}</span>
                        <span class="text-text-secondary">/tháng</span>
                    </div>
                    <div class="mb-8 p-3 bg-surface-container-low rounded-lg">
                        <p class="text-on-surface-variant font-label-md text-label-md">Không cam kết dài hạn · có thể nâng cấp sang gói năm bất cứ lúc nào</p>
                    </div>
                    <ul class="space-y-4 mb-12 flex-grow">
                        @foreach (array_slice($premiumFeatures, 0, 4) as $feature)
                            <li class="flex items-center gap-3 font-body-sm text-body-sm">
                                <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    @if ($monthlyCta['disabled'])
                        <span class="w-full py-3 px-4 bg-primary-container/50 text-on-primary-container font-label-md text-label-md rounded-xl text-center cursor-default">
                            {{ $monthlyCta['label'] }}
                        </span>
                    @else
                        <a href="{{ $monthlyCta['href'] }}"
                            class="w-full py-3 px-4 bg-primary-container text-on-primary-container font-label-md text-label-md rounded-xl hover:opacity-90 transition-opacity text-center">
                            {{ $monthlyCta['label'] }}
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if ($free && $premium)
            <section class="mb-24">
                <h2 class="font-headline-md text-headline-md text-center mb-12">So sánh chi tiết</h2>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse min-w-[480px]">
                        <thead>
                            <tr class="border-b-2 border-primary">
                                <th class="text-left py-4 px-6 font-label-md text-label-md text-on-surface-variant">Tính năng</th>
                                <th class="text-center py-4 px-6 font-label-md text-label-md">{{ $free->name }}</th>
                                <th class="text-center py-4 px-6 font-label-md text-label-md text-primary">{{ $premium->name }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $maxRows = max(count($freeFeatures), count($premiumFeatures));
                            @endphp
                            @for ($i = 0; $i < $maxRows; $i++)
                                <tr class="border-b border-border hover:bg-surface-container-lowest transition-colors">
                                    <td class="py-4 px-6 font-body-sm text-body-sm">Quyền lợi {{ $i + 1 }}</td>
                                    <td class="text-center py-4 px-6 font-body-sm text-body-sm text-text-secondary">
                                        {{ $freeFeatures[$i] ?? '—' }}
                                    </td>
                                    <td class="text-center py-4 px-6 font-body-sm text-body-sm text-primary font-medium">
                                        {{ $premiumFeatures[$i] ?? '—' }}
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="max-w-3xl mx-auto">
            <h2 class="font-headline-md text-headline-md text-center mb-8">Câu hỏi thường gặp</h2>
            <div class="space-y-4">
                @foreach ([
                    [
                        'q' => 'Tôi có thể hủy gói Premium bất cứ lúc nào không?',
                        'a' => 'Đúng vậy, bạn có thể hủy gia hạn tự động bất cứ lúc nào trong phần cài đặt tài khoản. Sau khi hủy, bạn vẫn giữ quyền lợi Premium cho đến hết thời hạn đã thanh toán.',
                    ],
                    [
                        'q' => config('app.name') . ' hỗ trợ các phương thức thanh toán nào?',
                        'a' => 'Chúng tôi hỗ trợ đa dạng phương thức: Chuyển khoản ngân hàng, Ví điện tử MoMo, ZaloPay và thẻ tín dụng Visa/Mastercard thông qua cổng thanh toán bảo mật.',
                    ],
                    [
                        'q' => 'Gói theo năm có ưu đãi gì so với trả tháng?',
                        'a' => 'Gói trả trước theo năm giúp tiết kiệm đáng kể so với gia hạn lẻ hàng tháng — xem bảng giá phía trên để biết mức % tiết kiệm từng gói.',
                    ],
                ] as $faq)
                    <details class="group bg-surface border border-border rounded-xl overflow-hidden">
                        <summary class="p-4 flex justify-between items-center cursor-pointer list-none hover:bg-surface-container-low transition-colors">
                            <h4 class="font-label-md text-label-md text-on-surface">{{ $faq['q'] }}</h4>
                            <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="p-4 pt-0 text-text-secondary font-body-sm text-body-sm">{{ $faq['a'] }}</div>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.public>
