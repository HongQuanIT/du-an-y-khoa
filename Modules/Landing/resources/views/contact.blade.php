<x-layouts.public title="Liên hệ">
    <div class="py-16 px-margin-mobile md:px-gutter max-w-container-max mx-auto w-full">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-12">
            <!-- Left Column: Contact Info -->
            <div class="md:col-span-5 flex flex-col space-y-8">
                <div>
                    <h1 class="text-headline-lg font-headline-lg text-on-background mb-4">Liên hệ với chúng tôi</h1>
                    <p class="text-body-lg font-body-lg text-text-secondary">
                        {{ config('app.name') }} luôn sẵn sàng lắng nghe và hỗ trợ bạn. Hãy liên hệ với chúng tôi qua
                        các kênh dưới đây hoặc điền vào form bên cạnh.
                    </p>
                </div>

                <ul class="space-y-6">
                    @foreach ([['icon' => 'mail', 'label' => 'Email', 'value' => 'hotro@medpro.vn', 'href' => 'mailto:hotro@medpro.vn'], ['icon' => 'call', 'label' => 'Hotline', 'value' => '1900 1234', 'href' => 'tel:19001234'], ['icon' => 'location_on', 'label' => 'Địa chỉ', 'value' => 'Tầng 5, Toà nhà ABC, Hà Nội', 'href' => null], ['icon' => 'schedule', 'label' => 'Giờ làm việc', 'value' => 'T2–T6, 8:00–17:30', 'href' => null]] as $item)
                        <li class="flex items-start">
                            <div
                                class="flex-shrink-0 bg-surface-container w-12 h-12 rounded-full flex items-center justify-center mr-4">
                                <span class="material-symbols-outlined text-primary-container"
                                    style="font-variation-settings: 'FILL' 1;">{{ $item['icon'] }}</span>
                            </div>
                            <div>
                                <p class="text-label-md font-label-md text-on-surface">{{ $item['label'] }}</p>
                                @if ($item['href'])
                                    <a href="{{ $item['href'] }}"
                                        class="text-body-md font-body-md text-primary-container hover:underline">{{ $item['value'] }}</a>
                                @else
                                    <p class="text-body-md font-body-md text-text-secondary">{{ $item['value'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div>
                    <p class="text-label-md font-label-md text-on-surface mb-3">Kết nối với chúng tôi</p>
                    <div class="flex space-x-4">
                        @foreach (['Facebook', 'Google', 'LinkedIn', 'YouTube'] as $social)
                            <a href="#" aria-label="{{ $social }}"
                                class="w-10 h-10 rounded-full bg-surface border border-border flex items-center justify-center text-primary-container hover:bg-primary-container hover:text-on-primary transition-all duration-200 shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">public</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="w-full h-48 bg-surface-container rounded-xl overflow-hidden border border-border relative">
                    <img class="w-full h-full object-cover" alt="Bản đồ văn phòng tại Hà Nội"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBItZ0hKQLUEejsds47JqQH3IuvKtmwXKTilR1D-Qqwz1djdkoCVKsGWLaH_F2YaD9ManNRQKLNYdULJ3GfPAjVXd6vbgUmuCI-VbHKKR5jW73y1hiYPY11p5ByC0idhXfrT0-jM5vXzEocTPYJL5m7JulRnGK_28B5DdcEgLAu7_ZJ1qqzo51BeWdJ4P5Xq05fKA_LCFpzd0RPEw-A9SMXMOp44mI7LXDrRODxaPI5m7QJlofjqcqe_lGb66nKBS4hXY85OWQZGENK">
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="bg-surface/80 backdrop-blur-sm px-4 py-2 rounded-lg shadow-sm border border-border/50">
                            <span class="text-label-sm font-label-sm text-on-surface flex items-center">
                                <span class="material-symbols-outlined text-primary-container mr-1 text-sm">map</span>
                                Bản đồ văn phòng
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Contact Form -->
            <div class="md:col-span-7">
                <div class="bg-surface border border-border rounded-xl p-6 md:p-8 shadow-sm">
                    <h2 class="text-headline-sm font-headline-sm text-on-background mb-6">Gửi tin nhắn cho chúng tôi</h2>
                    <form action="#" class="space-y-5" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="name">Họ
                                    tên <span class="text-error">*</span></label>
                                <input
                                    class="w-full h-12 bg-background border border-border rounded-lg px-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors"
                                    id="name" name="name" placeholder="Nhập họ và tên của bạn" required type="text">
                            </div>
                            <div>
                                <label class="block text-label-sm font-label-sm text-on-surface mb-1.5"
                                    for="email">Email <span class="text-error">*</span></label>
                                <input
                                    class="w-full h-12 bg-background border border-border rounded-lg px-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors"
                                    id="email" name="email" placeholder="example@email.com" required type="email">
                            </div>
                        </div>
                        <div>
                            <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="subject">Chủ đề
                                <span class="text-error">*</span></label>
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
                            <label class="block text-label-sm font-label-sm text-on-surface mb-1.5"
                                for="message">Nội dung <span class="text-error">*</span></label>
                            <textarea
                                class="w-full bg-background border border-border rounded-lg p-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors resize-y min-h-[120px]"
                                id="message" name="message" placeholder="Nhập chi tiết nội dung bạn cần hỗ trợ..." required
                                rows="5"></textarea>
                        </div>
                        <div class="flex items-start pt-2">
                            <div class="flex items-center h-5">
                                <input
                                    class="w-5 h-5 bg-background border-border rounded text-primary-container focus:ring-primary-container cursor-pointer"
                                    id="privacy" name="privacy" required type="checkbox">
                            </div>
                            <div class="ml-3">
                                <label class="text-body-sm font-body-sm text-text-secondary cursor-pointer select-none"
                                    for="privacy">
                                    Tôi đồng ý với <a class="text-primary-container hover:underline" href="#">chính sách
                                        bảo mật</a> của {{ config('app.name') }} khi gửi thông tin này.
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
            </div>
        </div>
    </div>
</x-layouts.public>
