<x-layouts.reviewer title="Hồ sơ">
    <x-admin.page-header title="Hồ sơ reviewer" description="Quản lý tên hiển thị và mật khẩu." />
    <x-admin.flash />
    @if ($errors->any())<p class="mb-4 text-error">{{ $errors->first() }}</p>@endif
    <div class="grid max-w-3xl gap-6 md:grid-cols-2">
        @can('profile.update')
            <form method="post" action="{{ route('reviewer.profile.update') }}" class="space-y-4 rounded-xl border border-outline-variant bg-surface p-5">
                @csrf @method('PUT')
                <h2 class="font-headline-sm">Thông tin tài khoản</h2>
                <p class="text-on-surface-variant">{{ $user->email }}</p>
                <label for="name" class="block">Tên hiển thị</label>
                <input id="name" name="name" required value="{{ old('name', $user->name) }}" class="w-full rounded-lg border border-outline-variant p-2">
                <button class="rounded-lg bg-primary px-4 py-2 text-on-primary">Lưu thay đổi</button>
            </form>
        @endcan
        @can('profile.password_update')
            <form method="post" action="{{ route('reviewer.profile.password') }}" class="space-y-4 rounded-xl border border-outline-variant bg-surface p-5">
                @csrf @method('PUT')
                <h2 class="font-headline-sm">Đổi mật khẩu</h2>
                <label for="current_password" class="block">Mật khẩu hiện tại</label>
                <input id="current_password" name="current_password" type="password" required class="w-full rounded-lg border border-outline-variant p-2">
                <label for="password" class="block">Mật khẩu mới</label>
                <input id="password" name="password" type="password" required class="w-full rounded-lg border border-outline-variant p-2">
                <label for="password_confirmation" class="block">Xác nhận mật khẩu</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-lg border border-outline-variant p-2">
                <button class="rounded-lg bg-primary px-4 py-2 text-on-primary">Đổi mật khẩu</button>
            </form>
        @endcan
    </div>
    <div class="mt-6 grid max-w-3xl gap-6 md:grid-cols-2">
        @can('profile.avatar_update')
            <section class="space-y-4 rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-headline-sm">Ảnh đại diện</h2>
                @if ($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="Ảnh đại diện" class="size-20 rounded-full object-cover">@endif
                <form method="post" action="{{ route('reviewer.profile.avatar') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf @method('PUT')
                    <input name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required class="block w-full">
                    <button class="rounded-lg bg-primary px-4 py-2 text-on-primary">Cập nhật ảnh</button>
                </form>
                @if ($user->avatar_path)
                    <form method="post" action="{{ route('reviewer.profile.avatar.destroy') }}">@csrf @method('DELETE')<button class="text-error">Xóa ảnh đại diện</button></form>
                @endif
            </section>
        @endcan
        @can('profile.two_factor_toggle')
            <section class="space-y-4 rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-headline-sm">Xác thực hai bước</h2>
                @if ($user->hasTwoFactorEnabled())
                    <p>Đã bật 2FA.</p>
                    <form method="post" action="{{ route('reviewer.profile.2fa.disable') }}" class="space-y-3">
                        @csrf @method('DELETE')
                        <label for="disable_password" class="block">Mật khẩu hiện tại</label>
                        <input id="disable_password" name="current_password" type="password" required class="w-full rounded-lg border border-outline-variant p-2">
                        <button class="rounded-lg border border-error px-4 py-2 text-error">Tắt 2FA</button>
                    </form>
                @else
                    <p>Tăng bảo mật tài khoản bằng ứng dụng Authenticator.</p>
                    <a href="{{ route('reviewer.profile.2fa.setup') }}" class="inline-block rounded-lg bg-primary px-4 py-2 text-on-primary">Bật 2FA</a>
                @endif
            </section>
        @endcan
    </div>
</x-layouts.reviewer>
