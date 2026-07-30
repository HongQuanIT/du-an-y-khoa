<x-layouts.public title="Về chúng tôi">
    <!-- 1. Hero Section -->
    <section class="bg-[#F0FDFA] py-16 md:py-24 px-margin-mobile md:px-gutter">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="font-display text-3xl sm:text-4xl md:text-display text-primary mb-6">Sứ mệnh của
                {{ config('app.name') }}</h1>
            <p class="font-body-lg text-body-lg text-text-secondary max-w-2xl mx-auto">Giúp mọi sinh viên &amp; bác sĩ Y
                khoa Việt Nam học hiệu quả, thi tự tin với nền tảng công nghệ giáo dục hiện đại nhất.</p>
        </div>
    </section>

    <!-- 2. Câu chuyện ra đời -->
    <section class="py-16 md:py-24 px-margin-mobile md:px-gutter max-w-container-max mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-16 items-center">
            <div class="space-y-6">
                <h2 class="font-headline-lg text-headline-lg text-on-background">Câu chuyện ra đời</h2>
                <p class="font-body-md text-body-md text-text-secondary leading-relaxed">
                    {{ config('app.name') }} được khởi xướng bởi một nhóm các bác sĩ trẻ và chuyên gia công nghệ với một
                    trăn trở chung: Làm thế nào để việc học Y khoa tại Việt Nam trở nên bớt áp lực và hiệu quả hơn? Chúng
                    tôi hiểu rằng khối lượng kiến thức khổng lồ và áp lực từ các kỳ thi nội trú, CCHN luôn là rào cản lớn.
                </p>
                <p class="font-body-md text-body-md text-text-secondary leading-relaxed">
                    Sứ mệnh của chúng tôi là nâng tầm giáo dục y khoa thông qua việc chuẩn hóa nội dung theo hướng cập
                    nhật quốc tế (UpToDate, Harrison) nhưng vẫn sát với thực tế lâm sàng tại Việt Nam.
                    {{ config('app.name') }} không chỉ là một ngân hàng câu hỏi, mà là người bạn đồng hành thông minh
                    trên con đường sự nghiệp của mỗi nhân viên y tế.
                </p>
                <div class="flex items-center gap-4 pt-4">
                    <div class="w-12 h-1 bg-primary rounded-full"></div>
                    <span class="font-label-md text-label-md text-primary uppercase tracking-wider">Học tập không ngừng
                        - Tận tâm cống hiến</span>
                </div>
            </div>
            <div class="relative group">
                <div class="absolute -inset-4 bg-primary/10 rounded-2xl -z-10 transition-transform group-hover:scale-105">
                </div>
                <div class="w-full aspect-[4/3] rounded-xl overflow-hidden shadow-xl">
                    <img class="w-full h-full object-cover" alt="Đội ngũ bác sĩ và chuyên gia giáo dục y khoa"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBradTgp2EYCoME1EiNEBjd7QdQ83DyDhBvMX1WKZ-BkYHohu6DwOX13sP8EQwEN4Wre-gOAev0jBO8f4CYqT6XG06iHQofiWg1zdQlcZgzk595ojV08v3jP7OXkKxYsPhy0ICsiLS8UZA5O1BC-gm-dHlsiEi6HpRCn-7w2hMXpDME982f0M9cIyBa0Cs2VUpic76Rw9dW206ickH6rqcOWPB6GyTRsSmK3p5FaCKGjvNhDseTbmncDA_uVTNF4iRyNNtilMc3ajCw">
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Giá trị cốt lõi -->
    <section class="py-16 md:py-20 bg-surface-container-low px-margin-mobile md:px-gutter">
        <div class="max-w-container-max mx-auto text-center mb-16">
            <h2 class="font-headline-lg text-headline-lg text-on-background mb-4">Giá trị cốt lõi</h2>
            <div class="w-16 h-1 bg-primary mx-auto rounded-full"></div>
        </div>
        <div class="max-w-container-max mx-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8">
            @foreach ([['icon' => 'verified', 'title' => 'Chuẩn hóa nội dung', 'desc' => 'Nội dung được thẩm định bởi hội đồng chuyên môn là các Tiến sĩ, Bác sĩ uy tín.'], ['icon' => 'psychology', 'title' => 'Cá nhân hóa', 'desc' => 'Thuật toán AI phân tích điểm mạnh yếu để gợi ý lộ trình học tập tối ưu.'], ['icon' => 'groups', 'title' => 'Đồng hành', 'desc' => 'Cộng đồng học tập sôi nổi cùng sự hỗ trợ 24/7 từ đội ngũ học thuật.'], ['icon' => 'shield_lock', 'title' => 'Minh bạch & Tin cậy', 'desc' => 'Dữ liệu kết quả chính xác, cam kết bảo mật và hỗ trợ người dùng tối đa.']] as $value)
                <div class="bg-surface p-8 rounded-xl border border-border hover:shadow-lg transition-all text-center">
                    <div
                        class="w-16 h-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="material-symbols-outlined text-3xl">{{ $value['icon'] }}</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm text-on-background mb-3">{{ $value['title'] }}</h3>
                    <p class="font-body-sm text-body-sm text-text-secondary">{{ $value['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 4. Thành tựu -->
    <section class="py-16 bg-white border-y border-border">
        <div
            class="max-w-container-max mx-auto px-margin-mobile md:px-gutter grid grid-cols-2 md:grid-cols-4 gap-8">
            @foreach ([['12.450', 'Câu hỏi bản quyền'], ['38.000+', 'Người học tin dùng'], ['18', 'Chuyên ngành Y khoa'], ['96%', 'Tỉ lệ hài lòng']] as $stat)
                <div class="text-center">
                    <p class="font-display text-[32px] md:text-[40px] leading-tight text-primary font-bold">
                        {{ $stat[0] }}</p>
                    <p class="font-label-md text-label-md text-text-secondary">{{ $stat[1] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 5. Đội ngũ chuyên gia -->
    <section class="py-16 md:py-24 px-margin-mobile md:px-gutter max-w-container-max mx-auto">
        <div class="text-center mb-16">
            <h2 class="font-headline-lg text-headline-lg text-on-background mb-4">Đội ngũ chuyên gia</h2>
            <p class="font-body-md text-body-md text-text-secondary">Hội tụ những chuyên gia đầu ngành trong lĩnh vực
                giáo dục Y khoa.</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-8 md:gap-12">
            @foreach ([['name' => 'TS.BS. Trần Văn Minh', 'role' => 'Cố vấn chuyên môn Nội khoa', 'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLvDkCD578cYSin-_kyksoOYCeWLfQxoh0ZTWBZy5-_zt-SbD2tW9eoMaq_M6YUbfNlnow98krzw39xUwDoSEqzuuGWFyKqA11h-zFcHi4jWoqXPj-lOGj_aN04Fn-6_sXQO29_VVSkgWMZjidwzDkxnxyY89YwqIkGdvnlFoTiwCFh3oYGh5DlIOKGcNeEWdOKEfatN8nRFv7MCM3ZzEUTN2uFLmmnayHgpnZ6BfkW50mM9uu6QUiPMJBTg'], ['name' => 'ThS.BS. Nguyễn Anh Tuấn', 'role' => 'Trưởng ban Nội dung Ngoại khoa', 'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLuRxdaTKCUbtSR1qPPV5uACq8CGIzKijXHk6wE5sBFApFbN_-6hb5MS7DYkIKvBjAz0UngBX6FO3KMnbGHdnxeodWzYbzaB3nV5MawYvq3XEnSEr5vvm3j3pJ_-fNPelD_sgG5o7T0lg_ocm8OpUHyaMJ3Mi2Swz4bGLYNaI65fFRUnRV6sS1ss4KvVH_TnFbQc5r_wo9X7qSzOjOJM8fq4nxYMSGcX-FRJqNdY2VAKeM7wRfBlgC_iw9o'], ['name' => 'BSCKII. Lê Thị Hồng', 'role' => 'Cố vấn Sản Nhi khoa', 'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLtwYR3tTWOwSsruwTUSvSu9mwiYyaBzQQ-j2nNplJLV1M7jqlHf4RVl_P1_1d0AtDr_4dk049zwABVX9p2zAbA6thOd19fQ8cKI-sHw6gFgVicUIO2Gjz9XjWbdkTLiHOipK23p7z4ouSNNoNavmQbpP2avgZ9d_VRvPuX5d2VjJv6KXQsdS04ExugM4JcD9v8ONCi1LfBJGpnW-ht2d2djdgwc-20QDKw2_KhjmhMiVQc8oiqgrUnep7Y'], ['name' => 'PGS.TS. Phạm Hoàn', 'role' => 'Cố vấn Giải phẫu - Mô phôi', 'img' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCw_DbNNSAw_zy8LSm11WqN43b-ZYHxpfo5m2pbBl3NPhiS4GGndTBFKpvhpMsuVlsn11GxahSI7WrDIxs0pMxJBqJcKilh_N_TzrOKld7ZX8s_ElKiz5FN4PnC_dj51e_SBb6W9DCOU0GyTxEWArhD3n-ogSbi407oUPzf4P9Tu9PRCf_ucpklPrSS6XLaAwS8ZAcLrh3FS2IcagNalWlEW2J8-HLNn8K2fH8HXSaPiVXUEAmSKjDnJeSSaJ3yE8KfK_ZABxwi4_PD'], ['name' => 'ThS.BS. Mai Phương', 'role' => 'Giảng viên Anh văn Y khoa', 'img' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDzS3YyBkfMlTWkikdcWNZxS9Dxk-mFUXU7XdALcZCCxAP7SoPxlM-bKf2Bwamqt8vlT8OcWU_A9IGpNtFfxFqGP5qBSh3aazsa1oCFLPJYnt746a8NY7H9jTMRHZymPCQCFWn3qQFxWef34-25K_zNk9zGVm2w7VEaG-BIgvCES8c_x9Bn_cq2TzOGIZNJ0eUFDMNVYn3gYRShrkFhUft-pTqcxqFIJuVLckZ_5Hb1prBm0RB9UYn4eipEZNaxmESj9j5_AxJyWAQU'], ['name' => 'BS. Đặng Quốc Huy', 'role' => 'Trưởng bộ phận Ngân hàng câu hỏi', 'img' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCU8u8aTDrRlZdatPcmDdLf_7WdbB0gxjCHm2nSka8mNqMRvawnV2QFaTnP9HfFWC4E5ndA49K6e7JSjnW_zIBhiO187MROFnGY2aC78SnRwv5Jms23buI0yDv76mainelos6eOxHvRKpOZL_EKwd2i3a4Lt7u34iXJfTv1sS__uQo3kqZ8rtd6h2m6YtxUSPQGcAR0JIRXV6Ss-qNjptyHDoYM65rU2nLvkl_4dLAH2X1R71Tje6gpNmo0Ja_ZodfYSP_BD2NPPzL_']] as $expert)
                <div class="text-center group">
                    <div
                        class="w-28 h-28 md:w-32 md:h-32 mx-auto mb-6 rounded-full overflow-hidden border-4 border-white shadow-md group-hover:scale-105 transition-transform">
                        <img alt="{{ $expert['name'] }}" class="w-full h-full object-cover" src="{{ $expert['img'] }}">
                    </div>
                    <h4 class="font-headline-sm text-headline-sm text-on-background">{{ $expert['name'] }}</h4>
                    <p class="font-body-sm text-body-sm text-primary font-medium">{{ $expert['role'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 6. Đối tác -->
    <section class="py-16 bg-white border-t border-border overflow-hidden">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <p class="text-center font-label-md text-label-md text-text-secondary uppercase tracking-[0.2em] mb-12">Đối
                tác đồng hành</p>
            <div
                class="flex flex-wrap justify-center items-center gap-12 md:gap-24 opacity-50 grayscale hover:grayscale-0 transition-all">
                @foreach (['ĐH Y HÀ NỘI', 'ĐH Y DƯỢC TP.HCM', 'BV BẠCH MAI', 'BV CHỢ RẪY', 'ĐH Y DƯỢC HUẾ'] as $partner)
                    <div class="h-12 flex items-center">
                        <span class="font-bold text-lg md:text-xl text-on-surface">{{ $partner }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- 7. CTA Section -->
    <section class="py-16 md:py-24 px-margin-mobile md:px-gutter">
        <div
            class="max-w-container-max mx-auto bg-primary rounded-[2rem] p-12 md:p-20 text-center relative overflow-hidden shadow-2xl">
            <div class="relative z-10">
                <h2 class="font-display text-3xl md:text-[48px] text-on-primary mb-8">Cùng {{ config('app.name') }}
                    chinh phục kỳ thi</h2>
                <p class="font-body-lg text-body-lg text-on-primary-container max-w-2xl mx-auto mb-10">Hàng ngàn sinh
                    viên đã cải thiện điểm số chỉ sau 30 ngày luyện tập. Bắt đầu ngay hôm nay!</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}"
                        class="px-10 py-4 bg-white text-primary font-bold rounded-xl shadow-lg hover:bg-on-primary-container transition-all flex items-center justify-center gap-2">
                        Đăng ký miễn phí
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                    <a href="{{ route('landing.features') }}"
                        class="px-10 py-4 border border-white/30 text-white font-bold rounded-xl hover:bg-white/10 transition-all text-center">
                        Tìm hiểu thêm
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
