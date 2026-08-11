<x-layouts.auth title="Đặt lại mật khẩu">
    <x-auth.shell tagline="Tạo mật khẩu mới cho tài khoản">
        <h2 class="font-headline-md text-headline-md text-on-surface mb-8">Đặt lại mật khẩu</h2>

        <x-auth.errors />

        <form class="space-y-5" action="{{ route('password.update') }}" method="post">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <x-auth.input name="email" label="Email" type="email" placeholder="bacsi@mebpro.vn" required
                autocomplete="email" :value="$email ?? ''" />

            <x-auth.password-input name="password" label="Mật khẩu mới" required autocomplete="new-password"
                placeholder="Tối thiểu 8 ký tự" />

            <x-auth.password-input name="password_confirmation" label="Xác nhận mật khẩu mới" required
                autocomplete="new-password" />

            <x-auth.submit>Lưu mật khẩu mới</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
