<x-layouts.auth title="Xác thực 2FA" description="Nhập mã Authenticator để tiếp tục.">
    <x-auth.shell tagline="Bảo mật tài khoản">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Xác thực hai bước</h2>
        <p class="text-body-sm font-body-sm text-on-surface-variant mb-8">
            Nhập mã 6 số từ ứng dụng Authenticator, hoặc một mã khôi phục.
            Thiết bị này sẽ được ghi nhớ 30 ngày, lần đăng nhập sau sẽ không hỏi lại.
        </p>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('student.2fa.challenge.verify') }}" method="post">
            @csrf
            <x-auth.input name="code" label="Mã xác thực" type="text" autocomplete="one-time-code"
                placeholder="000000 hoặc XXXX-XXXX" required autofocus />
            <x-auth.submit>Xác nhận</x-auth.submit>
        </form>

        <form class="mt-6" method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-label-md font-label-md text-on-surface-variant hover:text-primary hover:underline">
                Đăng xuất
            </button>
        </form>
    </x-auth.shell>
</x-layouts.auth>
