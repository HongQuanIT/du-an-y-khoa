<x-layouts.auth title="Đăng nhập">
    <x-auth.shell tagline="Chào mừng trở lại!">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-8">Đăng nhập</h2>

        @if (session('status'))
            <div class="mb-6 rounded-lg border border-primary/25 bg-primary-fixed/25 px-4 py-3 font-body-md text-body-md text-primary">
                {{ session('status') }}
            </div>
        @endif

        @if (! empty($planPriceId))
            <div class="mb-6 rounded-lg border border-primary/25 bg-primary-fixed/25 px-4 py-3 font-body-sm text-body-sm text-on-surface">
                Sau khi đăng nhập, bạn sẽ được chuyển tới trang xác nhận gói để thanh toán.
            </div>
        @endif

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('login', \Modules\Billing\Support\CheckoutIntent::authQuery($planPriceId ?? null)) }}" method="post">
            @csrf
            @if (! empty($planPriceId))
                <input type="hidden" name="plan_price_id" value="{{ $planPriceId }}">
            @endif

            <x-auth.input name="email" label="Email" type="email" placeholder="bacsi@mebpro.vn" required autofocus
                autocomplete="email" />

            <x-auth.password-input name="password" label="Mật khẩu" required autocomplete="current-password" />

            <div class="flex items-center justify-between py-1">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input class="w-4 h-4 rounded border-border text-primary focus:ring-primary" type="checkbox"
                        name="remember" value="1" @checked(old('remember'))>
                    <span
                        class="text-label-md font-label-md text-on-surface-variant group-hover:text-on-surface transition-colors">Ghi
                        nhớ đăng nhập</span>
                </label>
                <a class="text-label-md font-label-md text-primary hover:underline" href="{{ route('password.request') }}">Quên mật khẩu?</a>
            </div>

            <x-auth.submit>Đăng nhập</x-auth.submit>
        </form>

        <x-auth.social-providers />

        <p class="mt-8 text-center text-body-sm font-body-sm text-on-surface-variant">
            Chưa có tài khoản?
            <a class="text-primary font-label-md hover:underline"
                href="{{ \Modules\Billing\Support\CheckoutIntent::registerUrl($planPriceId ?? null) }}">Đăng ký ngay</a>
        </p>
    </x-auth.shell>
</x-layouts.auth>
