@php
    $premiumFeatures = [
        'Toàn bộ QBank & Thư viện',
        'AI Mentor không giới hạn',
        'Mô phỏng thi thật',
        'Phân tích lỗ hổng kiến thức',
        'Ưu tiên hỗ trợ 24/7',
    ];
@endphp

<x-layouts.public title="Bảng giá">
    <div class="w-full max-w-container-max mx-auto px-margin-mobile md:px-gutter py-16"
        x-data="{
            years: 1,
            plans: {
                1: { label: '1 năm', total: 1790000, perMonth: 149000, save: 25, months: 12 },
                2: { label: '2 năm', total: 2990000, perMonth: 124000, save: 37, months: 24 },
                3: { label: '3 năm', total: 3990000, perMonth: 110000, save: 44, months: 36 },
            },
            monthlyPrice: 199000,
            format(n) {
                return new Intl.NumberFormat('vi-VN').format(n) + '₫';
            },
            get plan() {
                return this.plans[this.years];
            },
            listPrice() {
                return this.monthlyPrice * this.plan.months;
            },
        }">
        <!-- Hero Section -->
        <section class="text-center mb-10 md:mb-12">
            <h1 class="font-headline-lg text-headline-lg mb-4 text-on-surface">Chọn gói phù hợp với bạn</h1>
            <p class="text-text-secondary font-body-lg text-body-lg max-w-2xl mx-auto">Tăng tốc hành trình y khoa của
                bạn với các công cụ ôn thi chuyên sâu và AI hỗ trợ thông minh.</p>
        </section>

        <!-- Billing period switch -->
        <div class="flex flex-col items-center gap-3 mb-12 md:mb-16">
            <p class="font-label-sm text-label-sm text-on-surface-variant">Xem bảng giá theo năm</p>
            <div
                class="relative inline-flex p-1 pt-3 rounded-2xl bg-surface-container-low border border-border"
                role="tablist"
                aria-label="Chọn thời hạn gói năm">
                <template x-for="y in [1, 2, 3]" :key="y">
                    <button type="button" role="tab"
                        @click="years = y"
                        :aria-selected="years === y"
                        :class="years === y
                            ? 'bg-surface text-primary shadow-sm border border-border'
                            : 'text-on-surface-variant hover:text-on-surface border border-transparent'"
                        class="relative min-w-[5.5rem] sm:min-w-[6.5rem] px-4 py-2.5 rounded-xl font-label-md text-label-md transition-all">
                        <span x-text="plans[y].label"></span>
                        <span x-show="y === 3"
                            class="absolute -top-3.5 left-1/2 -translate-x-1/2 premium-badge px-2 py-0.5 rounded-full text-white text-[10px] font-bold whitespace-nowrap leading-none pointer-events-none">
                            Tiết kiệm nhất
                        </span>
                    </button>
                </template>
            </div>
            <p class="font-body-sm text-body-sm text-primary" x-show="years === 3" x-cloak>
                Gói 3 năm — mức giá / tháng thấp nhất, tiết kiệm đến 44% so với trả tháng.
            </p>
            <p class="font-body-sm text-body-sm text-on-surface-variant" x-show="years !== 3" x-cloak>
                Thanh toán theo năm giúp bạn tiết kiệm hơn so với gia hạn từng tháng.
            </p>
        </div>

        <!-- Pricing Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-24 relative items-start">
            <!-- Free Plan -->
            <div class="bg-surface border border-border p-8 rounded-xl flex flex-col hover:shadow-lg transition-shadow">
                <div class="mb-8">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">Miễn phí</h3>
                    <p class="text-text-secondary font-body-sm text-body-sm">Cơ bản cho người mới bắt đầu</p>
                </div>
                <div class="mb-8">
                    <span class="text-headline-lg font-bold">0₫</span>
                    <span class="text-text-secondary">/tháng</span>
                </div>
                <ul class="space-y-4 mb-12 flex-grow">
                    <li class="flex items-center gap-3 font-body-sm text-body-sm">
                        <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                        20 câu hỏi/ngày
                    </li>
                    <li class="flex items-center gap-3 font-body-sm text-body-sm">
                        <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                        Thư viện giới hạn
                    </li>
                    <li class="flex items-center gap-3 font-body-sm text-body-sm opacity-50">
                        <span class="material-symbols-outlined text-[20px]">cancel</span>
                        AI không giới hạn
                    </li>
                    <li class="flex items-center gap-3 font-body-sm text-body-sm opacity-50">
                        <span class="material-symbols-outlined text-[20px]">cancel</span>
                        Toàn bộ QBank
                    </li>
                </ul>
                <a href="{{ route('register') }}"
                    class="w-full py-3 px-4 border border-border text-on-surface font-label-md text-label-md rounded-xl hover:bg-surface-container-low transition-colors text-center">Bắt
                    đầu ngay</a>
            </div>

            <!-- Premium yearly (Featured) -->
            <div
                class="bg-surface premium-border p-8 rounded-xl flex flex-col relative shadow-2xl md:scale-105 z-10">
                <div
                    class="absolute -top-4 left-1/2 -translate-x-1/2 premium-badge px-4 py-1 rounded-full text-white text-label-sm font-bold flex items-center gap-1 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[14px]"
                        style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                    <span x-text="years === 3 ? 'Tiết kiệm nhất' : 'Giá theo năm'"></span>
                </div>
                <div class="mb-8">
                    <h3 class="font-headline-sm text-headline-sm text-primary mb-2">
                        Premium <span x-text="plan.label"></span>
                    </h3>
                    <p class="text-text-secondary font-body-sm text-body-sm">Giải pháp ôn thi toàn diện · thanh toán một lần</p>
                </div>
                <div class="mb-2">
                    <span class="text-headline-lg font-bold" x-text="format(plan.total)"></span>
                    <span class="text-text-secondary">/<span x-text="plan.label"></span></span>
                </div>
                <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant line-through"
                    x-text="'Giá lẻ: ' + format(listPrice())"></p>
                <div class="mb-8 p-3 bg-primary-fixed/20 rounded-lg">
                    <p class="text-primary font-label-md text-label-md">
                        Chỉ ~<span x-text="format(plan.perMonth)"></span>/tháng · tiết kiệm <span x-text="plan.save"></span>%
                    </p>
                </div>
                <ul class="space-y-4 mb-12 flex-grow">
                    <li class="flex items-center gap-3 font-body-sm text-body-sm font-medium">
                        <span class="material-symbols-outlined text-success text-[20px]"
                            style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        Toàn bộ tính năng Premium
                    </li>
                    @foreach ($premiumFeatures as $feature)
                        <li class="flex items-center gap-3 font-body-sm text-body-sm">
                            <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('register') }}"
                    class="w-full py-3 px-4 bg-primary text-on-primary font-label-md text-label-md rounded-xl hover:opacity-90 transition-opacity text-center">
                    Mua gói <span x-text="plan.label"></span>
                </a>
            </div>

            <!-- Premium monthly -->
            <div class="bg-surface border border-border p-8 rounded-xl flex flex-col hover:shadow-lg transition-shadow">
                <div class="mb-8">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">Premium 1 tháng</h3>
                    <p class="text-text-secondary font-body-sm text-body-sm">Linh hoạt theo từng giai đoạn</p>
                </div>
                <div class="mb-8">
                    <span class="text-headline-lg font-bold">199.000₫</span>
                    <span class="text-text-secondary">/tháng</span>
                </div>
                <div class="mb-8 p-3 bg-surface-container-low rounded-lg">
                    <p class="text-on-surface-variant font-label-md text-label-md">Không cam kết dài hạn · có thể nâng cấp sang gói năm bất cứ lúc nào</p>
                </div>
                <ul class="space-y-4 mb-12 flex-grow">
                    @foreach (['Toàn bộ QBank', 'Thư viện đầy đủ', 'AI không giới hạn', 'Phân tích nâng cao'] as $feature)
                        <li class="flex items-center gap-3 font-body-sm text-body-sm">
                            <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('register') }}"
                    class="w-full py-3 px-4 bg-primary-container text-on-primary-container font-label-md text-label-md rounded-xl hover:opacity-90 transition-opacity text-center">Nâng
                    cấp theo tháng</a>
            </div>
        </div>

        <!-- Comparison Table -->
        <section class="mb-24">
            <h2 class="font-headline-md text-headline-md text-center mb-12">So sánh chi tiết</h2>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse min-w-[480px]">
                    <thead>
                        <tr class="border-b-2 border-primary">
                            <th class="text-left py-4 px-6 font-label-md text-label-md text-on-surface-variant">Tính
                                năng</th>
                            <th class="text-center py-4 px-6 font-label-md text-label-md">Miễn phí</th>
                            <th class="text-center py-4 px-6 font-label-md text-label-md text-primary">Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([['Số lượng câu hỏi (QBank)', '20 câu/ngày', 'Không giới hạn'], ['Truy cập thư viện y khoa', 'Cơ bản', 'Đầy đủ 100%'], ['Phân tích lỗ hổng kiến thức', 'Không hỗ trợ', 'Nâng cao (AI)'], ['Mô phỏng kỳ thi thật', 'Giới hạn', 'Không giới hạn'], ['Hỗ trợ từ chuyên gia', 'Community', '24/7 Ưu tiên']] as $row)
                            <tr class="border-b border-border hover:bg-surface-container-lowest transition-colors">
                                <td class="py-4 px-6 font-body-sm text-body-sm">{{ $row[0] }}</td>
                                <td class="text-center py-4 px-6 font-body-sm text-body-sm text-text-secondary">
                                    {{ $row[1] }}</td>
                                <td class="text-center py-4 px-6 font-body-sm text-body-sm text-primary font-medium">
                                    {{ $row[2] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <!-- FAQ Section -->
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
                        'a' => 'Gói 1 năm tiết kiệm 25%, 2 năm tiết kiệm 37%, và 3 năm tiết kiệm đến 44% so với gia hạn lẻ hàng tháng — đây là mức giá / tháng thấp nhất. Bạn vẫn nhận đầy đủ quyền lợi Premium và ưu tiên hỗ trợ 24/7.',
                    ],
                ] as $faq)
                    <details class="group bg-surface border border-border rounded-xl overflow-hidden">
                        <summary
                            class="p-4 flex justify-between items-center cursor-pointer list-none hover:bg-surface-container-low transition-colors">
                            <h4 class="font-label-md text-label-md text-on-surface">{{ $faq['q'] }}</h4>
                            <span
                                class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="p-4 pt-0 text-text-secondary font-body-sm text-body-sm">{{ $faq['a'] }}</div>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.public>
