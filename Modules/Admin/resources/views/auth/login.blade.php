<x-layouts.auth title="Đăng nhập quản trị" description="Cổng đăng nhập dành cho quản trị viên và biên tập nội dung.">
    <x-auth.shell tagline="Khu vực quản trị">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Đăng nhập quản trị</h2>
        <p class="text-body-sm font-body-sm text-on-surface-variant mb-8">
            Chỉ dành cho Admin, Super Admin và Content Editor. Học viên dùng
            <a class="text-primary font-label-md hover:underline" href="{{ route('login') }}">trang đăng nhập học tập</a>.
        </p>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('admin.login.store') }}" method="post">
            @csrf

            <x-auth.input name="email" label="Email" type="email" placeholder="admin@example.com" required autofocus
                autocomplete="username" />

            <x-auth.password-input name="password" label="Mật khẩu" required autocomplete="current-password" />

            <x-auth.submit>Đăng nhập</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
