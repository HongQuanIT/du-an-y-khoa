<x-layouts.auth title="Đặt lại mật khẩu">
    <x-auth.shell tagline="Đặt mật khẩu mới">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-8">Đặt lại mật khẩu</h2>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('password.update') }}" method="post">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <x-auth.input name="email" label="Email" type="email" :value="$email" required autocomplete="email" />
            <x-auth.password-input name="password" label="Mật khẩu mới" required autocomplete="new-password" />
            <x-auth.password-input name="password_confirmation" label="Xác nhận mật khẩu" required autocomplete="new-password" />

            <x-auth.submit>Cập nhật mật khẩu</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
