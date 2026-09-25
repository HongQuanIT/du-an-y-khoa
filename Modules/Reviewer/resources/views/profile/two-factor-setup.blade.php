<x-layouts.reviewer title="Thiết lập 2FA">
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="font-headline-lg">Bật xác thực hai bước</h1>
        @if ($errors->any())<p class="text-error">{{ $errors->first() }}</p>@endif
        <div class="rounded-xl border border-outline-variant bg-surface p-6">
            <p class="mb-4">Quét mã QR bằng ứng dụng Authenticator rồi nhập mã xác nhận.</p>
            <img src="{{ $qr }}" alt="Mã QR 2FA" class="mb-4 size-56 bg-white p-2">
            <p class="mb-4 break-all font-mono">{{ $secret }}</p>
            <form method="post" action="{{ route('reviewer.profile.2fa.confirm') }}" class="space-y-4">
                @csrf
                <label for="code" class="block">Mã xác thực</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="rounded-lg border border-outline-variant p-2">
                <button class="block rounded-lg bg-primary px-4 py-2 text-on-primary">Xác nhận</button>
            </form>
        </div>
    </div>
</x-layouts.reviewer>
