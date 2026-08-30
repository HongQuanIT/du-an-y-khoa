<x-layouts.auth title="Đăng nhập cộng tác viên" description="Cổng dành cho cộng tác viên giới thiệu.">
    <x-auth.shell tagline="Cổng cộng tác viên">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Đăng nhập CTV</h2>
        <p class="text-body-sm font-body-sm text-on-surface-variant mb-8">
            Chỉ dành cho tài khoản Cộng tác viên. Học viên dùng
            <a class="text-primary font-label-md hover:underline" href="{{ route('login') }}">đăng nhập học tập</a>.
        </p>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('partner.login.store') }}" method="post">
            @csrf

            <x-auth.input name="email" label="Email" type="email" placeholder="partner@example.com" required autofocus
                autocomplete="username" />

            <x-auth.password-input name="password" label="Mật khẩu" required autocomplete="current-password" />

            <x-auth.submit>Đăng nhập</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
