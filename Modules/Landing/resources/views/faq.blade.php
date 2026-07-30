<x-layouts.public title="Câu hỏi thường gặp">
    <!-- Header Section -->
    <header class="py-16 md:py-24 text-center px-margin-mobile md:px-gutter">
        <div class="max-w-3xl mx-auto">
            <h1 class="font-headline-lg text-headline-lg text-on-surface mb-4">Câu hỏi thường gặp</h1>
            <p class="font-body-lg text-body-lg text-text-secondary mb-8">Chúng tôi có thể giúp gì cho bạn? Tìm kiếm câu
                trả lời nhanh chóng cho các vấn đề thường gặp.</p>
            <div class="relative max-w-2xl mx-auto">
                <span
                    class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary">search</span>
                <input
                    class="w-full pl-12 pr-4 h-12 bg-surface border border-border rounded-xl font-body-md text-body-md text-on-surface focus:outline-none focus:border-primary-container focus:ring-1 focus:ring-primary-container transition-all shadow-sm"
                    placeholder="Nhập câu hỏi hoặc từ khóa..." type="text">
            </div>
        </div>
    </header>

    <!-- Main Content Grid -->
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter pb-16 md:pb-24">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Left Sidebar (Categories) -->
            <aside class="w-full md:w-1/4 flex-shrink-0">
                <div class="bg-surface rounded-xl border border-border p-4 md:sticky md:top-24">
                    <h2 class="font-label-md text-label-md text-text-secondary uppercase tracking-wider mb-4 px-3">Danh
                        mục hỗ trợ</h2>
                    <nav class="space-y-1">
                        @foreach ([['icon' => 'person', 'label' => 'Tài khoản & đăng ký', 'active' => true], ['icon' => 'payments', 'label' => 'Gói & thanh toán', 'active' => false], ['icon' => 'school', 'label' => 'Tính năng học tập', 'active' => false], ['icon' => 'medical_services', 'label' => 'Nội dung y khoa', 'active' => false], ['icon' => 'security', 'label' => 'Kỹ thuật & bảo mật', 'active' => false]] as $cat)
                            <a @class([
                                'flex items-center px-3 py-2 rounded-lg font-label-md text-label-md transition-colors',
                                'bg-[#F1F5F9] border-l-2 border-primary-container text-primary-container' => $cat['active'],
                                'text-text-secondary hover:bg-background hover:text-on-surface' => !$cat['active'],
                            ]) href="#">
                                <span class="material-symbols-outlined mr-3">{{ $cat['icon'] }}</span>
                                {{ $cat['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            <!-- Right Content (Accordions) -->
            <section class="w-full md:w-3/4">
                <h2 class="font-headline-md text-headline-md text-on-surface mb-6">Tài khoản &amp; đăng ký</h2>
                <div class="space-y-4">
                    @foreach ([['q' => 'Làm sao để đăng ký tài khoản?', 'a' => 'Để đăng ký tài khoản, bạn vui lòng nhấp vào nút "Đăng ký" ở góc trên cùng bên phải của trang web. Sau đó, điền đầy đủ thông tin cá nhân bao gồm họ tên, địa chỉ email, và mật khẩu. Bạn cũng có thể đăng ký nhanh bằng tài khoản Google hoặc Facebook. Sau khi hoàn tất, hệ thống sẽ gửi một email xác nhận đến địa chỉ email bạn đã cung cấp.'], ['q' => 'Tôi quên mật khẩu thì phải làm thế nào?', 'a' => 'Nếu bạn quên mật khẩu, hãy nhấp vào liên kết "Quên mật khẩu?" tại trang Đăng nhập. Nhập địa chỉ email mà bạn đã dùng để đăng ký tài khoản. Hệ thống sẽ gửi cho bạn một email chứa liên kết để đặt lại mật khẩu mới.'], ['q' => 'MedPro có hỗ trợ thanh toán qua chuyển khoản không?', 'a' => 'Có, chúng tôi hiện đang hỗ trợ thanh toán qua hình thức chuyển khoản ngân hàng. Khi bạn tiến hành thanh toán, hãy chọn phương thức "Chuyển khoản ngân hàng". Tài khoản của bạn sẽ được kích hoạt trong vòng 1-2 giờ làm việc.'], ['q' => 'Tôi có thể thay đổi địa chỉ email đăng nhập không?', 'a' => 'Hiện tại, vì lý do bảo mật, bạn không thể tự ý thay đổi địa chỉ email đã đăng ký. Nếu bạn thực sự cần thay đổi email do lý do đặc biệt, vui lòng liên hệ trực tiếp với bộ phận Chăm sóc khách hàng và cung cấp các thông tin xác minh cần thiết.']] as $faq)
                        <details class="group bg-surface border border-border rounded-xl overflow-hidden">
                            <summary
                                class="w-full flex justify-between items-center p-6 text-left cursor-pointer list-none">
                                <span
                                    class="font-headline-sm text-headline-sm text-on-surface pr-4">{{ $faq['q'] }}</span>
                                <span
                                    class="material-symbols-outlined text-text-secondary transition-transform group-open:rotate-180 shrink-0">expand_more</span>
                            </summary>
                            <div
                                class="px-6 pb-6 pt-4 text-text-secondary font-body-md text-body-md border-t border-border">
                                {{ $faq['a'] }}
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <!-- CTA Section -->
    <section class="max-w-container-max mx-auto px-margin-mobile md:px-gutter pb-16 md:pb-24">
        <div class="bg-surface-container-low border border-border rounded-2xl p-8 md:p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white border border-border mb-6">
                <span class="material-symbols-outlined text-primary-container text-3xl">support_agent</span>
            </div>
            <h3 class="font-headline-md text-headline-md text-on-surface mb-3">Vẫn cần trợ giúp?</h3>
            <p class="font-body-md text-body-md text-text-secondary mb-8 max-w-lg mx-auto">Nếu bạn không tìm thấy câu trả
                lời ở đây, đừng ngần ngại liên hệ với đội ngũ hỗ trợ của chúng tôi. Chúng tôi luôn sẵn sàng hỗ trợ bạn
                24/7.</p>
            <a class="inline-flex items-center justify-center bg-primary-container text-on-primary px-6 py-3 rounded-xl font-label-md text-label-md hover:bg-primary transition-colors shadow-sm"
                href="{{ route('landing.contact') }}">
                Liên hệ hỗ trợ
                <span class="material-symbols-outlined ml-2 text-sm">arrow_forward</span>
            </a>
        </div>
    </section>
</x-layouts.public>
