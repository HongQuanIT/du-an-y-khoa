<x-layouts.public title="Tính năng">
    <!-- Hero Section -->
    <section class="pt-16 md:pt-24 pb-16 md:pb-20 px-margin-mobile md:px-gutter max-w-container-max mx-auto text-center">
        <h1 class="font-display text-3xl sm:text-4xl md:text-display text-on-surface mb-8 max-w-4xl mx-auto leading-tight">
            Đột phá kết quả học tập với bộ công cụ Y khoa toàn diện
        </h1>
        <p class="font-body-lg text-body-lg text-text-secondary max-w-2xl mx-auto mb-12">
            {{ config('app.name') }} kết hợp trí tuệ nhân tạo và phương pháp học tập khoa học giúp sinh viên y khoa và
            bác sĩ trẻ tối ưu hóa thời gian ôn luyện.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4 sm:gap-6">
            <a href="{{ route('register') }}"
                class="bg-primary text-on-primary px-8 md:px-10 py-4 md:py-5 rounded-xl font-headline-sm text-headline-sm hover:shadow-xl hover:shadow-primary/20 transition-all text-center">Dùng
                thử miễn phí</a>
            <a href="#"
                class="bg-white border border-border text-on-surface px-8 md:px-10 py-4 md:py-5 rounded-xl font-headline-sm text-headline-sm hover:bg-surface-container-low transition-all text-center">Xem
                video hướng dẫn</a>
        </div>
    </section>

    <!-- Features Bento Grid -->
    <section class="px-margin-mobile md:px-gutter pb-24 md:pb-32 max-w-container-max mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <!-- QBank (Large) -->
            <div
                class="md:col-span-8 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary overflow-hidden group">
                <div class="flex flex-col md:flex-row gap-10 items-center h-full">
                    <div class="flex-1">
                        <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                            <span class="material-symbols-outlined text-primary text-4xl">database</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md mb-4">Ngân hàng câu hỏi (QBank)</h3>
                        <p class="text-body-md text-text-secondary mb-6 leading-relaxed">Hàng ngàn câu hỏi chuẩn hóa kèm
                            giải thích chi tiết, cập nhật theo phác đồ mới nhất từ các nguồn uy tín toàn cầu.</p>
                        <div class="flex flex-wrap gap-3">
                            <span
                                class="bg-primary-fixed/30 text-primary text-label-sm px-4 py-1.5 rounded-full font-bold">#USMLE</span>
                            <span
                                class="bg-primary-fixed/30 text-primary text-label-sm px-4 py-1.5 rounded-full font-bold">#NộiKhoa</span>
                            <span
                                class="bg-primary-fixed/30 text-primary text-label-sm px-4 py-1.5 rounded-full font-bold">#NgoạiKhoa</span>
                        </div>
                    </div>
                    <div
                        class="flex-1 w-full h-64 md:h-[320px] bg-slate-50 rounded-2xl overflow-hidden border border-border shadow-inner relative">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                            alt="Giao diện QBank"
                            src="https://lh3.googleusercontent.com/aida/AP1WRLv_p2rdQZm0QQ4HvbOfjWopS8En2e5kGHO6v7tyXV3e0jRVKf5mWIcMZc5_oq8ifygyXE5b7z672n2t_r74rpWb4TDXrHyM7iKQSkSlnUJdXgJoq6OE4tprXxLOP0gxlo2YJtAemTUVE3g03K1IgJ_25DBeV9a9anSstVhbpiLRASFVWDnT9UedXkXeGddCz1blvvS5VY3Yh4NyV09frO4ywaIMeH0HGe-veAamQR8wvchErRMU7KYFjDM">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/5 to-transparent"></div>
                    </div>
                </div>
            </div>

            <!-- Study/Exam Mode -->
            <div
                class="md:col-span-4 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary flex flex-col justify-between">
                <div>
                    <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <span class="material-symbols-outlined text-primary text-4xl">timer</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm mb-4">Chế độ Study/Exam</h3>
                    <p class="text-body-md text-text-secondary leading-relaxed">Linh hoạt giữa việc học sâu không áp lực
                        thời gian và chế độ thi thử mô phỏng thực tế để rèn luyện tâm lý.</p>
                </div>
                <div class="mt-8 flex justify-center py-6">
                    <div class="flex items-center gap-6 bg-surface-container-low p-4 rounded-2xl border border-border/50">
                        <div
                            class="w-14 h-14 bg-primary rounded-xl flex items-center justify-center text-white shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined">menu_book</span>
                        </div>
                        <div class="w-16 h-1 bg-border rounded-full relative">
                            <div class="absolute inset-y-0 left-0 w-1/2 bg-primary rounded-full"></div>
                        </div>
                        <div
                            class="w-14 h-14 bg-white rounded-xl border border-border flex items-center justify-center text-on-surface-variant">
                            <span class="material-symbols-outlined">history_edu</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flashcards -->
            <div
                class="md:col-span-4 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary">
                <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-4xl">style</span>
                </div>
                <h3 class="font-headline-sm text-headline-sm mb-4">Flashcards Spaced Repetition</h3>
                <p class="text-body-md text-text-secondary leading-relaxed mb-6">Thuật toán lặp lại ngắt quãng (SRS)
                    thông minh giúp tối ưu hóa việc ghi nhớ kiến thức dài hạn, chống lại đường cong quên lãng.</p>
                <div
                    class="h-32 bg-surface-container-lowest rounded-xl border border-dashed border-border flex items-center justify-center gap-4">
                    <div
                        class="w-16 h-24 bg-white border border-border rounded shadow-sm rotate-[-6deg] flex items-center justify-center text-primary/40">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div
                        class="w-16 h-24 bg-white border border-border rounded shadow-sm relative z-10 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined">school</span>
                    </div>
                    <div
                        class="w-16 h-24 bg-white border border-border rounded shadow-sm rotate-[6deg] flex items-center justify-center text-primary/40">
                        <span class="material-symbols-outlined">clinical_notes</span>
                    </div>
                </div>
            </div>

            <!-- AI Tutor (Large) -->
            <div
                class="md:col-span-8 bg-primary-container text-white border border-transparent rounded-2xl p-8 md:p-10 feature-card overflow-hidden group">
                <div class="flex flex-col md:flex-row gap-10 h-full relative">
                    <div class="flex-1 relative z-10">
                        <div
                            class="bg-white/20 w-14 h-14 rounded-xl flex items-center justify-center mb-6 backdrop-blur-sm">
                            <span class="material-symbols-outlined text-white text-4xl"
                                style="font-variation-settings: 'FILL' 1;">smart_toy</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md mb-4">AI Tutor Thông Minh</h3>
                        <p class="opacity-90 mb-10 text-body-lg leading-relaxed">Giải thích cơ chế bệnh sinh phức tạp,
                            phân tích case lâm sàng và trả lời thắc mắc tức thì như một trợ giảng chuyên khoa 24/7.</p>
                        <a href="{{ route('register') }}"
                            class="inline-block bg-white text-primary px-8 py-3.5 rounded-xl font-label-md hover:bg-opacity-95 transition-all shadow-lg">Trải
                            nghiệm ngay</a>
                    </div>
                    <div
                        class="flex-1 w-full h-64 md:h-[280px] bg-black/10 rounded-2xl overflow-hidden border border-white/10 backdrop-blur-sm self-center">
                        <img class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000"
                            alt="Mô hình giải phẫu AI"
                            src="https://lh3.googleusercontent.com/aida/AP1WRLtbNmWPQx0hanFuFxyjoLB6UuYj8xfD2PrSKgG6y03TG-muRS6Ok3IzkXZGcFVpnyYHPYtLMSYxz7zj0LWIyfxSchUkzFMZjl5S0luGeUiUPdWq8nOf2_obIgrnivwAfpWXjH6hn57WgKqrDicTEt-Di6Lak7jpNiRA3cUPENMQVy1PjuWJ45M13ahCy72-05ljEzvhmLbd3Lv029xyui5gBtt3WivCKiJNGYuif2mxdCuLQ6dPKlltirM">
                    </div>
                </div>
            </div>

            <!-- Analytics (Medium) -->
            <div
                class="md:col-span-6 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary group">
                <div class="flex justify-between items-start mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary text-3xl">insights</span>
                        </div>
                        <h3 class="font-headline-sm text-headline-sm">Phân tích &amp; Heatmap</h3>
                    </div>
                    <span class="bg-success/10 text-success text-label-md px-3 py-1 rounded-full font-bold shrink-0">+24%
                        hiệu quả</span>
                </div>
                <div class="relative rounded-2xl overflow-hidden border border-border mb-6">
                    <img class="w-full h-56 object-cover" alt="Bảng phân tích"
                        src="https://lh3.googleusercontent.com/aida/AP1WRLstF7jgbA9N0LiPoCV0AG4CueRmU_QaD2_V6JFv2o60GXvhyo0qCqwgWZ6oBxCvU1PsgRFh22SkTEbXekugIPhUz_yJsBcLe6gW-_1R7bVwj643mKNwGexQ2shVQT4sO_tOWXg3rcCCd-uM8hpht0nlW1aSWN0XOUgxmHvNaBrJbVJ7TD9822SZCtlkBU3FN2VPIUdHt4wRw76HwoKZyMTxrZzPt8MfMtGHLdbr-6nvJkaHEJbudnhXv-j0">
                    <div class="absolute inset-0 bg-gradient-to-t from-white/20 to-transparent"></div>
                </div>
                <p class="text-body-md text-text-secondary leading-relaxed">Theo dõi tiến độ học tập trực quan qua biểu
                    đồ nhiệt và dự đoán khả năng đỗ kỳ thi dựa trên dữ liệu hiệu suất cá nhân.</p>
            </div>

            <!-- Library (Medium) -->
            <div
                class="md:col-span-6 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary group">
                <div class="flex justify-between items-start mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary text-3xl">library_books</span>
                        </div>
                        <h3 class="font-headline-sm text-headline-sm">Thư viện liên kết chéo</h3>
                    </div>
                    <span
                        class="material-symbols-outlined text-text-secondary group-hover:text-primary transition-colors cursor-pointer">open_in_new</span>
                </div>
                <div class="relative rounded-2xl overflow-hidden border border-border mb-6">
                    <img class="w-full h-56 object-cover" alt="Thư viện y khoa"
                        src="https://lh3.googleusercontent.com/aida/AP1WRLsP1dQbJrTKbdKx7DhFaFDyG_BXJjiYS1f2xravLRoVLpXo5oJqnIWdbsVLIqOXxj-243qHayEF7BQbioFC5MJsD7b12tVCMwQz9Zqa2rWLdfan4JggLdfvjFSrFx-FvA5NI66GY9kalnp1G7BqQlDlO_FU33ENhv-aYtnZAOPRi-z-Zttp0S5LQbUnF0um-0I6jJtm5-nu9JPSNZB3o28Ig30vNaTWBiy50U5rJSXRo1bqic4y9rdVHqI">
                </div>
                <p class="text-body-md text-text-secondary leading-relaxed">Tra cứu ngay các thuật ngữ, giải phẫu và tài
                    liệu liên quan trong khi làm bài mà không cần phải rời khỏi màn hình ôn luyện.</p>
            </div>

            <!-- Personalized Path -->
            <div
                class="md:col-span-4 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary">
                <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-4xl">route</span>
                </div>
                <h3 class="font-headline-sm text-headline-sm mb-4">Lộ trình cá nhân hóa</h3>
                <p class="text-body-md text-text-secondary leading-relaxed">Thuật toán tự động đề xuất nội dung ôn tập
                    trọng tâm dựa trên các lỗ hổng kiến thức được hệ thống phát hiện qua quá trình làm bài.</p>
            </div>

            <!-- Exam Simulation (Large) -->
            <div
                class="md:col-span-8 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary flex items-center gap-12">
                <div class="flex-1">
                    <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <span class="material-symbols-outlined text-primary text-4xl">fact_check</span>
                    </div>
                    <h3 class="font-headline-md text-headline-md mb-4">Mô phỏng thi thật</h3>
                    <p class="text-body-md text-text-secondary leading-relaxed">Giao diện, áp lực thời gian và quy trình
                        sát 99% so với các kỳ thi Nội trú, USMLE và chứng chỉ hành nghề thực tế.</p>
                </div>
                <div
                    class="hidden md:flex flex-shrink-0 w-44 h-44 rounded-full border-[10px] border-primary/10 items-center justify-center relative">
                    <div
                        class="absolute inset-0 rounded-full border-[10px] border-primary border-t-transparent border-l-transparent rotate-[45deg]">
                    </div>
                    <div class="text-center">
                        <span class="text-primary font-bold text-4xl block">99%</span>
                        <span class="text-label-sm text-text-secondary uppercase tracking-widest font-bold">Tương
                            đồng</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-primary/5 py-16 md:py-24 px-margin-mobile md:px-gutter text-center border-y border-border">
        <div class="max-w-3xl mx-auto">
            <h2 class="font-headline-lg text-3xl md:text-display text-on-surface mb-6">Sẵn sàng chinh phục kỳ thi Y
                khoa?</h2>
            <p class="text-body-lg text-text-secondary mb-12">Tham gia cùng hơn 50.000 sinh viên và bác sĩ đang nâng tầm
                kiến thức mỗi ngày cùng {{ config('app.name') }}.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ route('register') }}"
                    class="bg-primary text-white px-12 py-5 rounded-xl font-headline-sm hover:shadow-2xl hover:shadow-primary/30 transition-all hover:-translate-y-1 text-center">Đăng
                    ký tài khoản miễn phí</a>
            </div>
            <p class="mt-6 text-label-sm text-text-secondary flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-success">verified</span>
                Bắt đầu ngay, không yêu cầu thẻ tín dụng.
            </p>
        </div>
    </section>
</x-layouts.public>
