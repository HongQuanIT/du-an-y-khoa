<div class="bg-surface border border-border rounded-xl p-6 md:p-8 shadow-sm">
    <h2 class="text-headline-sm font-headline-sm text-on-background mb-6">Gửi tin nhắn cho chúng tôi</h2>
    <form action="#" class="space-y-5" method="POST">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="name">Họ tên <span class="text-error">*</span></label>
                <input
                    class="w-full h-12 bg-background border border-border rounded-lg px-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors"
                    id="name" name="name" placeholder="Nhập họ và tên của bạn" required type="text">
            </div>
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="email">Email <span class="text-error">*</span></label>
                <input
                    class="w-full h-12 bg-background border border-border rounded-lg px-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors"
                    id="email" name="email" placeholder="example@email.com" required type="email">
            </div>
        </div>
        <div>
            <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="subject">Chủ đề <span class="text-error">*</span></label>
            <div class="relative">
                <select
                    class="w-full h-12 bg-background border border-border rounded-lg pl-4 pr-10 text-body-md text-on-background appearance-none focus:ring-0 focus:border-primary-container transition-colors cursor-pointer"
                    id="subject" name="subject" required>
                    <option disabled selected value="">Chọn chủ đề liên hệ</option>
                    <option value="account">Hỗ trợ tài khoản</option>
                    <option value="payment">Thanh toán</option>
                    <option value="partnership">Hợp tác</option>
                    <option value="other">Khác</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                    <span class="material-symbols-outlined text-text-secondary">expand_more</span>
                </div>
            </div>
        </div>
        <div>
            <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="message">Nội dung <span class="text-error">*</span></label>
            <textarea
                class="w-full bg-background border border-border rounded-lg p-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors resize-y min-h-[120px]"
                id="message" name="message" placeholder="Nhập chi tiết nội dung bạn cần hỗ trợ..." required rows="5"></textarea>
        </div>
        <div class="flex items-start pt-2">
            <div class="flex items-center h-5">
                <input
                    class="w-5 h-5 bg-background border-border rounded text-primary-container focus:ring-primary-container cursor-pointer"
                    id="privacy" name="privacy" required type="checkbox">
            </div>
            <div class="ml-3">
                <label class="text-body-sm font-body-sm text-text-secondary cursor-pointer select-none" for="privacy">
                    Tôi đồng ý với <a class="text-primary-container hover:underline" href="{{ route('landing.privacy') }}">chính sách bảo mật</a> của {{ config('app.name') }} khi gửi thông tin này.
                </label>
            </div>
        </div>
        <div class="pt-4">
            <button
                class="w-full md:w-auto px-8 py-3 bg-primary-container text-on-primary rounded-xl font-label-md hover:bg-primary hover:shadow-md transition-all duration-200 flex items-center justify-center"
                type="submit">
                <span>Gửi liên hệ</span>
                <span class="material-symbols-outlined ml-2 text-sm">send</span>
            </button>
        </div>
    </form>
</div>
