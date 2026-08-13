<x-layouts.auth title="Mã khôi phục 2FA" description="Lưu mã khôi phục 2FA — chỉ hiển thị một lần.">
    <x-auth.shell tagline="Bảo mật tài khoản">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Lưu mã khôi phục</h2>
        <p class="text-body-sm font-body-sm text-on-surface-variant mb-6">
            Mỗi mã chỉ dùng một lần nếu bạn mất thiết bị Authenticator. Sao chép và cất giữ an toàn — trang này không hiện lại.
        </p>

        <ul class="mb-8 grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($codes as $code)
                <li class="rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 font-mono text-sm tracking-wide text-on-surface">
                    {{ $code }}
                </li>
            @endforeach
        </ul>

        <form action="{{ route('settings.2fa.recovery.finish') }}" method="post">
            @csrf
            <x-auth.submit>Đã lưu — về Cài đặt</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
