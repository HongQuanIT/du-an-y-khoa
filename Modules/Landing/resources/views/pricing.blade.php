<x-layouts.public title="Bảng giá">
    <div class="w-full max-w-container-max mx-auto px-margin-mobile md:px-gutter py-16">
        <!-- Hero Section -->
        <section class="text-center mb-16">
            <h1 class="font-headline-lg text-headline-lg mb-4 text-on-surface">Chọn gói phù hợp với bạn</h1>
            <p class="text-text-secondary font-body-lg text-body-lg max-w-2xl mx-auto">Tăng tốc hành trình y khoa của
                bạn với các công cụ ôn thi chuyên sâu và AI hỗ trợ thông minh.</p>
        </section>

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

            <!-- Premium 6 Months (Featured) -->
            <div
                class="bg-surface premium-border p-8 rounded-xl flex flex-col relative shadow-2xl md:scale-105 z-10">
                <div
                    class="absolute -top-4 left-1/2 -translate-x-1/2 premium-badge px-4 py-1 rounded-full text-white text-label-sm font-bold flex items-center gap-1 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[14px]"
                        style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                    Tiết kiệm nhất
                </div>
                <div class="mb-8">
                    <h3 class="font-headline-sm text-headline-sm text-primary mb-2">Premium 6 tháng</h3>
                    <p class="text-text-secondary font-body-sm text-body-sm">Giải pháp ôn thi toàn diện</p>
                </div>
                <div class="mb-4">
                    <span class="text-headline-lg font-bold">990.000₫</span>
                    <span class="text-text-secondary">/6 tháng</span>
                </div>
                <div class="mb-8 p-3 bg-primary-fixed/20 rounded-lg">
                    <p class="text-primary font-label-md text-label-md">Chỉ ~165.000₫/tháng · tiết kiệm 17%</p>
                </div>
                <ul class="space-y-4 mb-12 flex-grow">
                    <li class="flex items-center gap-3 font-body-sm text-body-sm font-medium">
                        <span class="material-symbols-outlined text-success text-[20px]"
                            style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        Toàn bộ tính năng Premium
                    </li>
                    @foreach (['Mô phỏng thi thật', 'Ưu tiên hỗ trợ 24/7', 'Toàn bộ QBank & Thư viện', 'AI Mentor thông minh'] as $feature)
                        <li class="flex items-center gap-3 font-body-sm text-body-sm">
                            <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('register') }}"
                    class="w-full py-3 px-4 bg-primary text-on-primary font-label-md text-label-md rounded-xl hover:opacity-90 transition-opacity text-center">Mua
                    gói 6 tháng</a>
            </div>

            <!-- Premium 1 Month -->
            <div class="bg-surface border border-border p-8 rounded-xl flex flex-col hover:shadow-lg transition-shadow">
                <div class="mb-8">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">Premium 1 tháng</h3>
                    <p class="text-text-secondary font-body-sm text-body-sm">Linh hoạt theo từng giai đoạn</p>
                </div>
                <div class="mb-8">
                    <span class="text-headline-lg font-bold">199.000₫</span>
                    <span class="text-text-secondary">/tháng</span>
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
                    cấp ngay</a>
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
                @foreach ([['q' => 'Tôi có thể hủy gói Premium bất cứ lúc nào không?', 'a' => 'Đúng vậy, bạn có thể hủy gia hạn tự động bất cứ lúc nào trong phần cài đặt tài khoản. Sau khi hủy, bạn vẫn giữ quyền lợi Premium cho đến hết thời hạn đã thanh toán.'], ['q' => 'MebPro hỗ trợ các phương thức thanh toán nào?', 'a' => 'Chúng tôi hỗ trợ đa dạng phương thức: Chuyển khoản ngân hàng, Ví điện tử MoMo, ZaloPay và thẻ tín dụng Visa/Mastercard thông qua cổng thanh toán bảo mật.'], ['q' => 'Gói 6 tháng có ưu đãi gì đặc biệt không?', 'a' => 'Ngoài mức giá tiết kiệm 17% so với gia hạn lẻ hàng tháng, bạn còn nhận được quyền ưu tiên hỗ trợ 24/7 và bộ tài liệu mô phỏng kỳ thi thật độc quyền chỉ dành cho gói dài hạn.']] as $faq)
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
