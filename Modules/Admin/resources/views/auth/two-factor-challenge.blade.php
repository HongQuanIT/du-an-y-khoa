<x-layouts.auth title="Xác thực 2FA" description="Nhập mã Authenticator để vào khu vực quản trị.">
    <x-auth.shell tagline="Bảo mật quản trị">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Xác thực hai bước</h2>
        <p class="text-body-sm font-body-sm text-on-surface-variant mb-8">
            Nhập mã 6 số từ ứng dụng Authenticator, hoặc một mã khôi phục.
        </p>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('admin.2fa.challenge.verify') }}" method="post">
            @csrf
            <x-auth.input name="code" label="Mã xác thực" type="text" autocomplete="one-time-code"
                placeholder="000000 hoặc XXXX-XXXX" required autofocus />
            <x-auth.submit>Xác nhận</x-auth.submit>
        </form>

        <form class="mt-6" method="post" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="text-label-md font-label-md text-on-surface-variant hover:text-primary hover:underline">
                Đăng xuất
            </button>
        </form>
    </x-auth.shell>
</x-layouts.auth>
