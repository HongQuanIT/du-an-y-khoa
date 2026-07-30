<x-layouts.auth title="Đăng ký">
    <x-auth.shell tagline="Bắt đầu hành trình của bạn!">
        <div class="mb-8">
            <h2 class="font-headline-md text-headline-md text-on-background mb-2">Tạo tài khoản</h2>
            <p class="font-body-sm text-body-sm text-text-secondary">Khởi đầu sự nghiệp y tế chuyên nghiệp của bạn ngay
                hôm nay.</p>
        </div>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('register') }}" method="post"
            x-data="{
                password: '',
                get score() {
                    if (this.password === '') return 0;
                    let score = 0;
                    if (this.password.length >= 8) score++;
                    if (/[a-z]/.test(this.password) && /[A-Z]/.test(this.password)) score++;
                    if (/\d/.test(this.password)) score++;
                    if (/[^A-Za-z0-9]/.test(this.password)) score++;
                    return score;
                },
                get label() {
                    return ['', 'Rất yếu', 'Yếu', 'Khá', 'Mạnh'][this.score];
                },
                get barTone() {
                    return ['bg-surface-variant', 'bg-error', 'bg-warning', 'bg-warning', 'bg-success'][this.score];
                },
                get textTone() {
                    return ['text-on-surface-variant', 'text-error', 'text-warning', 'text-warning', 'text-success'][this.score];
                },
            }">
            @csrf

            <x-auth.input name="name" label="Họ và tên" placeholder="Nguyễn Văn An" required autofocus
                autocomplete="name" />

            <x-auth.input name="email" label="Email" type="email" placeholder="bacsi@mebpro.vn" required
                autocomplete="email" />

            <x-auth.password-input name="password" label="Mật khẩu" x-model="password" required
                autocomplete="new-password">
                <div class="mt-2 space-y-1.5" x-show="password.length > 0" x-cloak>
                    <div class="flex gap-1 h-1.5">
                        <template x-for="step in 4" :key="step">
                            <div class="w-1/4 rounded-full transition-colors"
                                :class="score >= step ? barTone : 'bg-surface-variant'"></div>
                        </template>
                    </div>
                    <span class="font-label-sm text-label-sm flex items-center gap-1" :class="textTone">
                        <span class="material-symbols-outlined text-[14px]" style='font-variation-settings: "FILL" 1;'
                            x-text="score === 4 ? 'check_circle' : 'info'">info</span>
                        <span x-text="label"></span>
                    </span>
                    <p class="font-label-sm text-label-sm text-text-secondary">Tối thiểu 8 ký tự, gồm chữ và số.</p>
                </div>
            </x-auth.password-input>

            <x-auth.password-input name="password_confirmation" label="Nhập lại mật khẩu" required
                autocomplete="new-password" />

            <div class="flex items-start gap-3 py-2">
                <input class="mt-1 w-4 h-4 text-primary border-border rounded focus:ring-primary" id="terms" name="terms"
                    type="checkbox" value="1" @checked(old('terms')) required>
                <label class="font-body-sm text-body-sm text-on-surface-variant leading-tight" for="terms">
                    Tôi đồng ý với <a class="text-primary font-medium hover:underline" href="#">Điều khoản</a>
                    &amp; <a class="text-primary font-medium hover:underline" href="#">Chính sách bảo mật</a>
                    của {{ config('app.name') }}.
                </label>
            </div>

            <x-auth.submit>Đăng ký</x-auth.submit>
        </form>

        <x-auth.social-providers />

        <p class="mt-8 text-center text-body-sm font-body-sm text-on-surface-variant">
            Đã có tài khoản? <a class="text-primary font-bold hover:underline" href="{{ route('login') }}">Đăng nhập</a>
        </p>
    </x-auth.shell>
</x-layouts.auth>
