<x-layouts.public title="Nền tảng ôn thi Y khoa">
    <!-- Hero Section -->
    <section class="relative pt-12 md:pt-24 pb-20 overflow-hidden">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="space-y-8 text-center lg:text-left">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[18px]"
                            style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                        Hỗ trợ bởi AI dành riêng cho Y khoa
                    </div>
                    <h1 class="font-display text-3xl sm:text-4xl md:text-display leading-tight tracking-tight">
                        Học hiệu quả hơn — hiểu bản chất, <span class="text-primary">nhớ lâu</span>, luyện thi đúng
                        trọng tâm
                    </h1>
                    <p class="font-body-lg text-body-lg text-text-secondary max-w-xl mx-auto lg:mx-0">
                        Nền tảng ôn thi y khoa tiên tiến giúp sinh viên y khoa và bác sĩ trẻ chinh phục mọi kỳ thi từ
                        cấp chứng chỉ hành nghề đến sau đại học.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="{{ route('register') }}"
                            class="bg-primary-container text-on-primary-container px-8 py-4 rounded-xl font-label-md text-label-md shadow-lg shadow-primary-container/20 hover:shadow-xl transition-all flex items-center justify-center gap-2">
                            Bắt đầu luyện thi
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </a>
                        <a href="#sample-question"
                            class="border border-border bg-surface text-on-surface px-8 py-4 rounded-xl font-label-md text-label-md hover:bg-surface-container-low transition-colors flex items-center justify-center gap-2">
                            Xem câu hỏi mẫu
                        </a>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -top-12 -left-12 w-64 h-64 bg-primary/10 rounded-full blur-3xl -z-10"></div>
                    <div class="absolute -bottom-12 -right-12 w-64 h-64 bg-secondary/10 rounded-full blur-3xl -z-10">
                    </div>
                    <div class="rounded-2xl border border-border shadow-2xl overflow-hidden bg-white/50 backdrop-blur-sm">
                        <img class="w-full aspect-video object-cover"
                            alt="Giao diện bảng điều khiển học tập y khoa MebPro"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuCKmBmH_jL81lnqycVaK7f31yR9s5DTmfe_R2ff-ghVles69zerPs9sdP-MIydPlBjOzz3xNtDX4i7Xf_G-dEmflYTVaCsjIdt2rv6lxVYQuQV3djPPfuMypgCd64GAuYtWUzndhQqEqKc8nZ7mGL1bFttR4pjQ1KxIhCUy8VST6R_epo77FoVfyej2PcVFNEzIo8NnR8BGyXeUkGvk19evM_UetyDHbHLFjTbkcll_UXpL-UBwn1IXXt6pv9fLYKftT5evadVYlZPC">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Bar -->
    <section class="bg-surface border-y border-border py-8">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="flex flex-wrap justify-around items-center gap-8 text-center">
                <div class="space-y-1">
                    <div class="font-headline-lg text-headline-lg text-primary">12.450</div>
                    <div class="font-label-md text-label-md text-text-secondary uppercase tracking-wider">Câu hỏi chuẩn
                        hóa</div>
                </div>
                <div class="w-px h-12 bg-border hidden md:block"></div>
                <div class="space-y-1">
                    <div class="font-headline-lg text-headline-lg text-primary">38.000+</div>
                    <div class="font-label-md text-label-md text-text-secondary uppercase tracking-wider">Người học tin
                        dùng</div>
                </div>
                <div class="w-px h-12 bg-border hidden md:block"></div>
                <div class="space-y-1">
                    <div class="font-headline-lg text-headline-lg text-primary">18</div>
                    <div class="font-label-md text-label-md text-text-secondary uppercase tracking-wider">Chuyên ngành y
                        khoa</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Value Props -->
    <section class="py-16 md:py-24">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="premium-card p-8 flex flex-col items-start gap-6">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-3xl">database</span>
                    </div>
                    <div class="space-y-3">
                        <h3 class="font-headline-sm text-headline-sm">Ngân hàng câu hỏi chuẩn hóa</h3>
                        <p class="font-body-md text-body-md text-text-secondary">Được biên soạn bởi các chuyên gia đầu
                            ngành, bám sát cấu trúc đề thi thực tế mới nhất.</p>
                    </div>
                </div>
                <div class="premium-card p-8 flex flex-col items-start gap-6">
                    <div class="w-12 h-12 bg-secondary/10 rounded-xl flex items-center justify-center text-secondary">
                        <span class="material-symbols-outlined text-3xl">bolt</span>
                    </div>
                    <div class="space-y-3">
                        <h3 class="font-headline-sm text-headline-sm">Luyện đề thông minh &amp; điểm yếu</h3>
                        <p class="font-body-md text-body-md text-text-secondary">Thuật toán Adaptive Learning giúp phát
                            hiện và tập trung vào các lỗ hổng kiến thức của bạn.</p>
                    </div>
                </div>
                <div class="premium-card p-8 flex flex-col items-start gap-6">
                    <div class="w-12 h-12 bg-warning/10 rounded-xl flex items-center justify-center text-warning">
                        <span class="material-symbols-outlined text-3xl">psychology</span>
                    </div>
                    <div class="space-y-3">
                        <h3 class="font-headline-sm text-headline-sm">AI Tutor giải thích tận gốc</h3>
                        <p class="font-body-md text-body-md text-text-secondary">Trợ lý ảo y khoa sẵn sàng giải thích
                            từng cơ chế bệnh sinh, không chỉ đơn thuần là đưa ra đáp án.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Sections -->
    <section class="py-12 space-y-24 md:space-y-32">
        <!-- Feature 1: QBank -->
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="space-y-6">
                    <div class="text-primary font-label-md text-label-md uppercase tracking-widest">Qbank Chuyên Sâu
                    </div>
                    <h2 class="font-headline-lg text-headline-lg">Học từ những tình huống lâm sàng thực tế</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">Mỗi câu hỏi là một ca lâm sàng mô phỏng sát
                        với thực tiễn bệnh viện, giúp bạn rèn luyện tư duy chẩn đoán và xử trí đúng phác đồ.</p>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-success">check_circle</span>
                            <span class="font-body-md text-body-md text-on-surface">Giải thích chi tiết từng đáp án
                                nhiễu</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-success">check_circle</span>
                            <span class="font-body-md text-body-md text-on-surface">Cập nhật theo Guidelines mới nhất
                                (AHA, ESC, ADA...)</span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden order-first lg:order-none">
                    <img class="w-full" alt="Giao diện ngân hàng câu hỏi MebPro"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBco-i_0cAFlAdsl6M2zaXtQz-AjP0tob5jmAwlVHZV34ooZqquhQy23_ahzFgZA6pPyYduvqwF2Z7txT5jFFmgfsKwZEPC7DlF0cCeMISnymOUebq9o-vWXNFeBb_go5v_eUXp67rUTM98wxDVzWmZRk-mhyWe-llPO2ZKCG6SNFXWB-eLHltGjrIbDGoK05U97-Fwof9VFH9AYhoKncqTT2nmUaXaeZUnted0jv3Xvc2o7_DLktjKU8S1xBPRAlVA_t8guvdx6LGH">
                </div>
            </div>
        </div>
        <!-- Feature 2: Library -->
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden">
                    <img class="w-full" alt="Thư viện số MedLib trên máy tính bảng"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBDnGjQ_o0IjKRjvJ1yKV48HHY4vxexg6Vrc9eo1KrGZHD0__BHgFrertnUrND8XwRD8ZgLNbCDaDkvEGmQkSS2AVRaeGWzNFfXrZh9qP-V4D8TPkIbvVlecqTqOsodMhO02hnvVp38Ee6Dbi08N7VAexRLrXkGraZPDie9njNzkonEgyqQgOCINd_1C4w-VviMq_28lETwm1uG18OVIwhX47_9rXfJYg0KQMqh9UmITXJSvp1QSvypuMCTCDpVFyH9WmHtZ-kt1wSw">
                </div>
                <div class="space-y-6">
                    <div class="text-secondary font-label-md text-label-md uppercase tracking-widest">Thư viện số</div>
                    <h2 class="font-headline-lg text-headline-lg">Mọi tài liệu bạn cần trong một tầm tay</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">MedLib tích hợp hàng nghìn đầu sách y khoa
                        và bài báo khoa học uy tín, được tổ chức thông minh để bạn tra cứu nhanh chóng trong lúc làm
                        bài.</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-surface-container-low rounded-xl border border-border">
                            <div class="font-label-md text-label-md text-primary mb-1">Search AI</div>
                            <div class="text-body-sm text-text-secondary">Tìm kiếm chính xác từ khóa trong nội dung
                                sách.</div>
                        </div>
                        <div class="p-4 bg-surface-container-low rounded-xl border border-border">
                            <div class="font-label-md text-label-md text-primary mb-1">Sync All</div>
                            <div class="text-body-sm text-text-secondary">Đồng bộ ghi chú trên mọi thiết bị di động.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Feature 3: AI Assistant -->
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="space-y-6">
                    <div class="text-warning font-label-md text-label-md uppercase tracking-widest">AI Assistant</div>
                    <h2 class="font-headline-lg text-headline-lg">Hỏi bất cứ điều gì, trả lời tức thì</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">MedAI không chỉ là một chatbot, đó là một
                        chuyên gia y tế được huấn luyện trên hàng triệu dữ liệu lâm sàng để hỗ trợ bạn học tập và tra
                        cứu lâm sàng.</p>
                    <div class="bg-surface-container-high/50 p-6 rounded-2xl border border-primary/20 space-y-4">
                        <div class="flex gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold shrink-0">
                                U</div>
                            <div class="bg-surface p-3 rounded-lg border border-border text-body-sm">Giải thích cơ chế
                                cơn đau thắt ngực không ổn định?</div>
                        </div>
                        <div class="flex gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-warning flex items-center justify-center text-white text-xs font-bold shrink-0">
                                AI</div>
                            <div class="bg-warning/10 p-3 rounded-lg border border-warning/20 text-body-sm">Cơn đau thắt
                                ngực không ổn định thường do sự nứt vỡ mảng xơ vữa dẫn đến hình thành huyết khối không
                                hoàn toàn...</div>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden order-first lg:order-none">
                    <img class="w-full" alt="Trợ lý AI phân tích mô hình giải phẫu tim"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuCMZgidLBFyHnk0vrswjJRjSr-4vrn5qUOhjp-K_ae3Z0i0u0WgIANj0aQeDOr146w66qsdxvCDNmqSwFpcRzBkfZKdGCQzurFCR1kiWJ2i8G7xUFOaOtr0Z5fWErXh_pdRwRimRo9kUR_gUXk4ZVGJxOz6I05xE8oGZwA9aPAqMbZQpNFQ2PR-Ecq8zneZ9CDpzIPV2uaPGIW4L3yG8h2Clj16aQjej6puVrcjURyHpoLOC5jqkgOot7FtHFuxgLpiBrA9gJRu2eYV">
                </div>
            </div>
        </div>
        <!-- Feature 4: Analytics -->
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden">
                    <img class="w-full" alt="Bảng phân tích học tập"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDMRt350QoWPMlsBSNXmCbfOmNEV8u97JDBrRf1gFyKu0L-xhboxI2kTFZuB9nHsezRjCwrB3q0kTshAM7Ujj5-DhmesvqpZWSGKHIxVU4r0IMSG5FaE-dSDmL13Mm_WYqlocAoc1yGbS9xCYuGYMQ6s4TDh-kuuBiOHjTskPPilYxvUHBNBNE9cjXhOVX7qfSrI66F9hDqQzahTRNBevPcIn70iNG_ph9TpzI5qJ4iRqqw6ithf_iS3t2HraKAUz43w0QPS2fuybVp">
                </div>
                <div class="space-y-6">
                    <div class="text-primary font-label-md text-label-md uppercase tracking-widest">Phân tích học tập
                    </div>
                    <h2 class="font-headline-lg text-headline-lg">Theo dõi tiến độ, tối ưu hiệu suất</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">Biểu đồ hóa kỹ năng của bạn qua từng chương
                        học. Biết chính xác bạn đang yếu ở Nội khoa hay Ngoại khoa để điều chỉnh chiến lược ôn tập.</p>
                    <div class="flex items-center gap-6">
                        <div class="text-center">
                            <div class="text-headline-sm font-bold text-success">96%</div>
                            <div class="text-label-sm text-text-secondary">Độ chính xác</div>
                        </div>
                        <div class="w-px h-10 bg-border"></div>
                        <div class="text-center">
                            <div class="text-headline-sm font-bold text-secondary">24h</div>
                            <div class="text-label-sm text-text-secondary">Thời gian học/tuần</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive QBank Widget -->
    <section id="sample-question" class="py-16 md:py-24 bg-surface-container-low scroll-mt-20">
        <div class="max-w-3xl mx-auto px-margin-mobile md:px-gutter">
            <div x-data="{ selected: '', checked: false, answer: 'A' }" class="premium-card bg-surface overflow-hidden shadow-2xl">
                <div class="bg-primary-container p-6 text-on-primary-container flex justify-between items-center">
                    <h3 class="font-headline-sm text-headline-sm">Câu hỏi mẫu</h3>
                    <span class="font-label-sm text-label-sm px-2 py-1 rounded bg-white/20">Nội khoa · Tim mạch</span>
                </div>
                <div class="p-6 md:p-8 space-y-6">
                    <div class="font-body-lg text-body-lg font-medium leading-relaxed">
                        Bệnh nhân nam 58 tuổi tiền sử THA, ĐTĐ típ 2, vào viện vì đau ngực trái dữ dội sau xương ức kéo
                        dài 45 phút, không đỡ khi nghỉ ngơi. Điện tâm đồ ghi nhận đoạn ST chênh lên ở V1-V4. Chẩn đoán
                        ưu tiên nhất là:
                    </div>
                    <div class="space-y-3">
                        @foreach (['A' => 'Nhồi máu cơ tim cấp ST chênh lên vùng trước rộng', 'B' => 'Đau thắt ngực ổn định', 'C' => 'Phình tách động mạch chủ ngực', 'D' => 'Viêm màng ngoài tim cấp', 'E' => 'Thuyên tắc phổi cấp'] as $key => $text)
                            <label
                                class="flex items-center gap-4 p-4 rounded-xl border cursor-pointer transition-colors"
                                :class="{
                                    'border-success bg-success/10': checked && answer === '{{ $key }}',
                                    'border-error bg-error/10': checked && selected === '{{ $key }}' && answer !== '{{ $key }}',
                                    'border-border hover:bg-surface-container': !checked || (selected !== '{{ $key }}' && answer !== '{{ $key }}'),
                                }">
                                <input class="w-5 h-5 text-primary border-outline-variant focus:ring-primary"
                                    name="sample_q" type="radio" value="{{ $key }}" x-model="selected"
                                    :disabled="checked">
                                <span class="font-body-md text-body-md text-on-surface">{{ $key }}. {{ $text }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="pt-4 flex justify-end">
                        <button type="button" @click="if (selected) checked = true"
                            class="px-8 py-3 rounded-xl font-label-md text-label-md text-white hover:opacity-90 active:scale-95 transition-all"
                            :class="{
                                'bg-primary': !checked,
                                'bg-success': checked && selected === answer,
                                'bg-error': checked && selected !== answer,
                            }"
                            x-text="!checked ? 'Kiểm tra' : (selected === answer ? 'Chính xác! Xem giải thích' : 'Chưa đúng, thử lại')"
                            @click.away="">Kiểm tra</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-16 md:py-24">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter text-center space-y-16">
            <div class="space-y-4">
                <h2 class="font-headline-lg text-headline-lg">Học viên nói gì về {{ config('app.name') }}?</h2>
                <p class="font-body-lg text-body-lg text-text-secondary max-w-2xl mx-auto">Hàng ngàn bác sĩ tương lai đã
                    bứt phá điểm số nhờ lộ trình ôn tập khoa học.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach ([['name' => 'Nguyễn Văn An', 'role' => 'Sinh viên Y6 - ĐH Y Hà Nội', 'quote' => 'Ngân hàng câu hỏi cực kỳ sát đề thi thực tế. Mình đã đậu CCHN ngay lần thi đầu tiên nhờ MebPro.', 'img' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDfJg-HhjBan8vIVWivC-GW2WvRhJjoAB3kkPut6VHyMAFXlCeR7wuFKc6Olip5imqRDGySZOjwLxRSV5GJD9SUdePEQg3FEZI5QnYg9c8vIsNKeJStAEz4m8cX4WP7KjKEdI57wKhqo9tt0--NpJZ-4BXTm-FcWHWBRDjl28231rHEuSIN4gQjKm-l3Yv8Fm9VPw9VTb1TqTu13WKzKbUqcOUj0v2dm_zOfDVQ0_vWdG8JVEL_RR2cvDsK17pKR_nupL2iocS9U6Tj'], ['name' => 'Trần Thị Mai', 'role' => 'Bác sĩ nội trú Nhi khoa', 'quote' => 'Phần AI giải thích rất dễ hiểu, giúp mình nắm chắc bản chất thay vì chỉ học thuộc lòng đáp án.', 'img' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBgsqfba9CocTxf8S17nTmdHvgMW_D4nNi6GhaPw3TeVaHo0tYEfgPFEZ8tJycKMX2ohBriXUW_urq6Ev9igFK9YCAmMs0i9cX2qPkLPjbwWnnBg3PM9Pn1zTarygbh8M9AmgudA2LY847vJrPhiSVERpzuPt1zQ8As2P_bnlf-ZiEYVj52rKJ6eIKmJnfuJ1zdwJdp-TutHlAwaBIv1jjR02-BzL1R9MK_HV3-LiEf274xXsb_cqrTBxq4fEzxx_LrBTn6E6FA4cGz'], ['name' => 'Lê Minh Đức', 'role' => 'Bác sĩ đa khoa', 'quote' => 'Giao diện đẹp, mượt mà trên cả điện thoại. Mình có thể tranh thủ ôn bài mọi lúc mọi nơi.', 'img' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDh0oAVpsp-78XhhSIT9Tq81R3zdNwGRPFrh8ee8yBAmQT_I6mmFXAA2o4odu0F8G7Bsz8Ogaxj9Y6IaSYo4lS2cbw4kZv7Z6ReRckRYmZTVikPfwxcUac2H1ywvC0cA_ixxUntjBLXsYQVPBC4iRX4DFD4kUZgiv04NMYbeeVvAAKdxpaRVOojAkscY7YkdmrMbRO46Nsb8m4fhbQlPRnwanjaDbAJWKMtSevjSOMgxComsIthL6xmgivq_90bfPr0Wfw-4IvXHYtD']] as $t)
                    <div class="premium-card p-8 text-left space-y-6">
                        <div class="flex gap-1 text-warning">
                            @for ($i = 0; $i < 5; $i++)
                                <span class="material-symbols-outlined"
                                    style="font-variation-settings: 'FILL' 1;">star</span>
                            @endfor
                        </div>
                        <p class="font-body-md text-body-md text-on-surface italic">"{{ $t['quote'] }}"</p>
                        <div class="flex items-center gap-4">
                            <img class="w-12 h-12 rounded-full object-cover" src="{{ $t['img'] }}"
                                alt="{{ $t['name'] }}">
                            <div>
                                <div class="font-label-md text-label-md">{{ $t['name'] }}</div>
                                <div class="text-label-sm text-text-secondary">{{ $t['role'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Pricing -->
    @php
        $homePremiumFeatures = [
            'Toàn bộ QBank & Thư viện',
            'AI Mentor không giới hạn',
            'Mô phỏng thi thật',
            'Phân tích lỗ hổng kiến thức',
            'Ưu tiên hỗ trợ 24/7',
        ];
    @endphp
    <section class="py-16 md:py-24 bg-surface-container-lowest"
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
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="text-center space-y-4 mb-10">
                <h2 class="font-headline-lg text-headline-lg">Lựa chọn gói phù hợp</h2>
                <p class="font-body-lg text-body-lg text-text-secondary">Đầu tư vào kiến thức là khoản đầu tư có lãi nhất.
                </p>
            </div>

            <div class="flex flex-col items-center gap-3 mb-12">
                <p class="font-label-sm text-label-sm text-on-surface-variant">Xem bảng giá theo năm</p>
                <div class="relative inline-flex p-1 pt-3 rounded-2xl bg-surface-container-low border border-border"
                    role="tablist" aria-label="Chọn thời hạn gói năm">
                    <template x-for="y in [1, 2, 3]" :key="y">
                        <button type="button" role="tab" @click="years = y" :aria-selected="years === y"
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
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start relative">
                <!-- Free -->
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

                <!-- Premium yearly -->
                <div class="bg-surface premium-border p-8 rounded-xl flex flex-col relative shadow-2xl md:scale-105 z-10">
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
                        @foreach ($homePremiumFeatures as $feature)
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

            <p class="text-center mt-10">
                <a href="{{ route('landing.pricing') }}"
                    class="font-label-md text-label-md text-primary hover:underline inline-flex items-center gap-1">
                    Xem bảng giá chi tiết
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </p>
        </div>
    </section>

    <!-- FAQ Accordion -->
    <section class="py-16 md:py-24">
        <div class="max-w-3xl mx-auto px-margin-mobile md:px-gutter space-y-8">
            <h2 class="font-headline-lg text-headline-lg text-center">Câu hỏi thường gặp</h2>
            <div class="space-y-4">
                @foreach ([['q' => 'MebPro có hỗ trợ ôn thi Nội trú không?', 'a' => 'Có, MebPro có ngân hàng đề thi dành riêng cho kỳ thi Sau đại học bao gồm Nội trú, Cao học và Chuyên khoa 1 với mức độ khó và phân hóa cao.'], ['q' => 'Dữ liệu câu hỏi được lấy từ đâu?', 'a' => "Hệ thống câu hỏi được đội ngũ bác sĩ nội trú và giảng viên y khoa biên soạn dựa trên các textbook chuẩn như Harrison's, Gray's Anatomy, Sabiston... và các đề thi thật qua các năm."], ['q' => 'Tôi có thể học trên điện thoại không?', 'a' => 'Hoàn toàn có thể. MebPro có phiên bản website mobile mượt mà và ứng dụng trên App Store/Google Play giúp bạn ôn tập mọi lúc mọi nơi.'], ['q' => 'Chính sách hoàn tiền như thế nào?', 'a' => 'Chúng tôi cam kết hoàn tiền 100% trong vòng 7 ngày nếu bạn không hài lòng với chất lượng dịch vụ mà không cần lý do.'], ['q' => 'Hệ thống AI có thể giải đáp các thắc mắc khó không?', 'a' => 'MedAI được tối ưu cho các câu hỏi về cơ chế bệnh sinh, chẩn đoán phân biệt và xử trí lâm sàng theo hướng dẫn (guidelines). Tuy nhiên, bạn nên luôn đối chiếu với y văn chính thống.']] as $faq)
                    <details class="group bg-surface rounded-xl border border-border overflow-hidden">
                        <summary
                            class="flex justify-between items-center p-6 cursor-pointer list-none font-label-md text-label-md text-on-surface">
                            {{ $faq['q'] }}
                            <span
                                class="material-symbols-outlined group-open:rotate-180 transition-transform">expand_more</span>
                        </summary>
                        <div class="px-6 pb-6 text-body-md text-text-secondary border-t border-border/50 pt-4">
                            {{ $faq['a'] }}
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Bottom CTA -->
    <section class="py-20 px-margin-mobile md:px-gutter">
        <div
            class="max-w-container-max mx-auto bg-primary-container rounded-3xl p-12 md:p-20 text-center space-y-8 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
                <svg fill="none" height="100%" preserveAspectRatio="none" viewBox="0 0 100 100" width="100%">
                    <path d="M0 100 Q 25 0 50 100 T 100 0" fill="none" stroke="white" stroke-width="0.5"></path>
                    <path d="M0 0 Q 50 100 100 0" fill="none" stroke="white" stroke-width="0.5"></path>
                </svg>
            </div>
            <h2 class="font-headline-lg text-headline-lg text-on-primary-container relative">Sẵn sàng chinh phục kỳ thi?
            </h2>
            <p class="font-body-lg text-body-lg text-on-primary-container/80 max-w-2xl mx-auto relative">Gia nhập cộng
                đồng hơn 38.000 y bác sĩ đang sử dụng {{ config('app.name') }} để nâng tầm kiến thức mỗi ngày.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center relative">
                <a href="{{ route('register') }}"
                    class="bg-white text-primary px-8 py-4 rounded-xl font-label-md text-label-md shadow-lg hover:bg-primary-fixed transition-all">Đăng
                    ký miễn phí</a>
                <a href="{{ route('landing.contact') }}"
                    class="bg-white/10 text-white border border-white/20 px-8 py-4 rounded-xl font-label-md text-label-md hover:bg-white/20 transition-all">Liên
                    hệ hỗ trợ</a>
            </div>
        </div>
    </section>
</x-layouts.public>
