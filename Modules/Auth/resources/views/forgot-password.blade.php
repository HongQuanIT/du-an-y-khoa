<x-layouts.auth title="Quên mật khẩu">
    <x-auth.shell tagline="Khôi phục quyền truy cập tài khoản">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Quên mật khẩu?</h2>
        <p class="mb-8 font-body-sm text-body-sm text-on-surface-variant">
            Nhập email đăng ký — chúng tôi sẽ gửi liên kết đặt lại mật khẩu.
        </p>

        @if (session('status'))
            <div class="mb-6 rounded-lg border border-primary/25 bg-primary-fixed/25 px-4 py-3 font-body-md text-body-md text-primary">
                {{ session('status') }}
            </div>
        @endif

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('password.email') }}" method="post">
            @csrf

            <x-auth.input name="email" label="Email" type="email" placeholder="bacsi@mebpro.vn" required
                autocomplete="email" :value="$email ?? ''" />

            <x-auth.submit>Gửi liên kết đặt lại</x-auth.submit>
        </form>

        <p class="mt-8 text-center text-body-sm font-body-sm text-on-surface-variant">
            <a class="text-primary font-label-md hover:underline" href="{{ route('login') }}">Quay lại đăng nhập</a>
        </p>
    </x-auth.shell>
</x-layouts.auth>
