<x-layouts.auth title="Thiết lập 2FA" description="Bật xác thực hai bước bắt buộc cho tài khoản quản trị.">
    <x-auth.shell tagline="Bảo mật quản trị">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Bật xác thực 2 bước</h2>
        <p class="text-body-sm font-body-sm text-on-surface-variant mb-6">
            Quét mã QR bằng ứng dụng Authenticator (Google, Microsoft, 1Password…), rồi nhập mã 6 số để xác nhận.
        </p>

        <x-auth.errors />

        <div class="mb-6 flex flex-col items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-low p-4">
            <img src="{{ $qr }}" alt="Mã QR 2FA" class="size-56 rounded-lg bg-white p-2" width="224" height="224">
            <div class="text-center">
                <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Hoặc nhập khóa thủ công</p>
                <code class="font-mono text-sm tracking-wider text-on-surface break-all">{{ $secret }}</code>
            </div>
        </div>

        <form class="space-y-5" action="{{ route('admin.2fa.confirm') }}" method="post">
            @csrf
            <x-auth.input name="code" label="Mã xác thực (6 số)" type="text" inputmode="numeric" autocomplete="one-time-code"
                placeholder="000000" required autofocus maxlength="6" />
            <x-auth.submit>Xác nhận và tiếp tục</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
