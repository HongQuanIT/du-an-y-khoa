<x-layouts.auth title="Đăng nhập giảng viên" description="Cổng đăng nhập dành cho giảng viên chữa đề.">
    <x-auth.shell tagline="Không gian giảng viên">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Đăng nhập giảng viên</h2>
        <p class="text-body-sm font-body-sm text-on-surface-variant mb-8">
            Chỉ dành cho tài khoản được gán vai trò Giảng viên. Học viên dùng
            <a class="text-primary font-label-md hover:underline" href="{{ route('login') }}">đăng nhập học tập</a>;
            quản trị dùng
            <a class="text-primary font-label-md hover:underline" href="{{ route('admin.login') }}">cổng quản trị</a>.
        </p>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('teach.login.store') }}" method="post">
            @csrf

            <x-auth.input name="email" label="Email" type="email" placeholder="instructor@example.com" required autofocus
                autocomplete="username" />

            <x-auth.password-input name="password" label="Mật khẩu" required autocomplete="current-password" />

            <x-auth.submit>Đăng nhập</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
