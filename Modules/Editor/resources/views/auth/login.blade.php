<x-layouts.auth title="Đăng nhập biên tập" description="Cổng làm việc dành cho biên tập viên nội dung.">
    <x-auth.shell tagline="Cổng biên tập">
        <h2 class="mb-2 font-headline-md text-headline-md text-on-surface">Đăng nhập biên tập</h2>
        <p class="mb-8 font-body-sm text-body-sm text-on-surface-variant">
            Dành cho tài khoản biên tập viên nội dung. Học viên dùng
            <a class="font-label-md text-primary hover:underline" href="{{ route('login') }}">trang đăng nhập học tập</a>.
        </p>
        <x-auth.errors />
        <form class="space-y-5" action="{{ route('editor.login.store') }}" method="post">
            @csrf
            <x-auth.input name="email" label="Email" type="email" placeholder="editor@example.com" required autofocus autocomplete="username" />
            <x-auth.password-input name="password" label="Mật khẩu" required autocomplete="current-password" />
            <x-auth.submit>Đăng nhập</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
