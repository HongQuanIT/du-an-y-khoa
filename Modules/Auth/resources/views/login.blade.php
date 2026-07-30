<x-layouts.auth title="Đăng nhập">
    <div class="flex min-h-screen">
        <!-- Left Column: Branding & Illustration (ẩn trên mobile) -->
        <div class="hidden lg:flex lg:w-1/2 bg-primary-container p-12 flex-col justify-between relative overflow-hidden">
            <div class="z-10">
                <a href="{{ route('landing.home') }}"
                    class="text-white font-headline-lg text-headline-lg font-extrabold tracking-tight">{{ config('app.name') }}</a>
                <p class="text-on-primary-container text-body-lg font-body-lg mt-2">Chào mừng trở lại!</p>
            </div>
            <div class="relative z-10 flex flex-col items-center">
                <div
                    class="w-full rounded-xl overflow-hidden shadow-2xl transform hover:scale-[1.02] transition-transform duration-500 border border-white/20">
                    <img alt="Giao diện học tập y khoa chuyên nghiệp" class="w-full object-cover"
                        src="https://lh3.googleusercontent.com/aida/AP1WRLv_p2rdQZm0QQ4HvbOfjWopS8En2e5kGHO6v7tyXV3e0jRVKf5mWIcMZc5_oq8ifygyXE5b7z672n2t_r74rpWb4TDXrHyM7iKQSkSlnUJdXgJoq6OE4tprXxLOP0gxlo2YJtAemTUVE3g03K1IgJ_25DBeV9a9anSstVhbpiLRASFVWDnT9UedXkXeGddCz1blvvS5VY3Yh4NyV09frO4ywaIMeH0HGe-veAamQR8wvchErRMU7KYFjDM">
                </div>
                <div class="mt-8 text-center text-white/90">
                    <p class="font-headline-sm text-headline-sm">Làm chủ kiến thức y khoa</p>
                    <p class="text-body-md opacity-80 mt-2">Nền tảng ôn luyện thông minh dành cho bác sĩ tương lai</p>
                </div>
            </div>
            <div class="absolute top-[-10%] right-[-10%] w-64 h-64 bg-primary rounded-full blur-3xl opacity-30"></div>
            <div
                class="absolute bottom-[-5%] left-[-5%] w-80 h-80 bg-on-primary-fixed-variant rounded-full blur-3xl opacity-20">
            </div>
        </div>

        <!-- Right Column: Login Form -->
        <div class="w-full lg:w-1/2 bg-surface flex flex-col justify-center p-6 sm:p-12 lg:p-16">
            <div class="max-w-md mx-auto w-full">
                <div class="lg:hidden mb-8 text-center">
                    <a href="{{ route('landing.home') }}"
                        class="font-headline-lg text-headline-lg font-extrabold text-primary tracking-tight">{{ config('app.name') }}</a>
                </div>
                <h2 class="font-headline-md text-headline-md text-on-surface mb-8">Đăng nhập</h2>

                <form class="space-y-5" action="#" method="post" x-data="{ show: false }">
                    <div class="space-y-1.5">
                        <label class="text-label-md font-label-md text-on-surface-variant" for="email">Email</label>
                        <input
                            class="w-full px-4 py-2.5 border border-border rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                            id="email" name="email" placeholder="bacsi@mebpro.vn" type="email">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-label-md font-label-md text-on-surface-variant" for="password">Mật
                            khẩu</label>
                        <div class="relative">
                            <input
                                class="w-full px-4 py-2.5 pr-12 border border-border rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                                id="password" name="password" :type="show ? 'text' : 'password'"
                                placeholder="••••••••">
                            <button type="button" @click="show = !show"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary">
                                <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input class="w-4 h-4 rounded border-border text-primary focus:ring-primary" type="checkbox"
                                name="remember">
                            <span
                                class="text-label-md font-label-md text-on-surface-variant group-hover:text-on-surface transition-colors">Ghi
                                nhớ đăng nhập</span>
                        </label>
                        <a class="text-label-md font-label-md text-primary hover:underline" href="#">Quên mật khẩu?</a>
                    </div>
                    <button
                        class="w-full bg-primary-container text-white py-3 rounded-xl font-label-md text-label-md flex items-center justify-center gap-3 hover:opacity-90 active:scale-[0.98] transition-all shadow-md"
                        type="submit">
                        Đăng nhập
                    </button>
                </form>

                <div class="relative my-8">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-border"></div>
                    </div>
                    <div class="relative flex justify-center text-label-sm font-label-sm">
                        <span class="bg-surface px-4 text-on-surface-variant uppercase tracking-wider">Hoặc tiếp tục
                            với</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    @foreach (['Google', 'Facebook', 'LinkedIn'] as $provider)
                        <button type="button"
                            class="flex justify-center items-center py-2.5 border border-border rounded-xl hover:bg-surface-container-low transition-colors active:scale-95"
                            title="{{ $provider }}">
                            <span class="material-symbols-outlined text-on-surface-variant">public</span>
                        </button>
                    @endforeach
                </div>

                <p class="mt-8 text-center text-body-sm font-body-sm text-on-surface-variant">
                    Chưa có tài khoản?
                    <a class="text-primary font-label-md hover:underline" href="{{ route('register') }}">Đăng ký
                        ngay</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.auth>
