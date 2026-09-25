@php
    $pages = [
        'profile' => ['Hồ sơ cá nhân', 'Quản lý thông tin tài khoản reviewer.'],
        'security' => ['Bảo mật', 'Quản lý mật khẩu và xác thực hai bước.'],
        'appearance' => ['Giao diện', 'Chọn chế độ sáng, tối hoặc theo hệ thống.'],
    ];
    [$title, $description] = $pages[$tab] ?? $pages['profile'];
    $profileRoute = fn (string $page = 'profile'): string => $page === 'profile'
        ? route('reviewer.profile.show')
        : route('reviewer.profile.show', ['tab' => $page]);
    $inputClass = 'mt-1.5 h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $profileCompletion = (int) round(collect([filled($user->name), filled($user->avatar_path)])->filter()->count() / 2 * 100);
@endphp

<x-layouts.reviewer :title="$title" :pending-count="0">
    <div class="mx-auto w-full max-w-[1040px]">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
            <aside class="lg:w-56 lg:shrink-0">
                <nav class="lg:hidden" aria-label="Tài khoản reviewer">
                    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                        @foreach (['profile' => ['Hồ sơ reviewer', 'person'], 'security' => ['Bảo mật', 'lock'], 'appearance' => ['Giao diện', 'palette']] as $key => [$label, $icon])
                            <a href="{{ $profileRoute($key) }}" @class(['inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 font-label-sm', 'border-primary bg-primary/10 font-semibold text-primary' => $tab === $key, 'border-outline-variant bg-surface text-on-surface-variant' => $tab !== $key])><span class="material-symbols-outlined text-[16px]">{{ $icon }}</span>{{ $label }}</a>
                        @endforeach
                    </div>
                </nav>
                <nav class="hidden space-y-6 lg:block" aria-label="Tài khoản reviewer">
                    <div><p class="mb-2 px-3 font-label-sm font-semibold tracking-wide text-on-surface-variant uppercase">Hồ sơ</p><a href="{{ $profileRoute() }}" @class(['flex items-center gap-2.5 rounded-lg px-3 py-2.5 font-label-md', 'bg-primary/10 font-semibold text-primary' => $tab === 'profile', 'text-on-surface-variant hover:bg-surface-container-low' => $tab !== 'profile'])><span class="material-symbols-outlined text-[20px]">person</span>Hồ sơ reviewer</a></div>
                    <div><p class="mb-2 px-3 font-label-sm font-semibold tracking-wide text-on-surface-variant uppercase">Tài khoản</p><div class="space-y-0.5">@foreach (['security' => ['Bảo mật', 'lock'], 'appearance' => ['Giao diện', 'palette']] as $key => [$label, $icon])<a href="{{ $profileRoute($key) }}" @class(['flex items-center gap-2.5 rounded-lg px-3 py-2.5 font-label-md', 'bg-primary/10 font-semibold text-primary' => $tab === $key, 'text-on-surface-variant hover:bg-surface-container-low' => $tab !== $key])><span class="material-symbols-outlined text-[20px]">{{ $icon }}</span>{{ $label }}</a>@endforeach</div></div>
                </nav>
            </aside>

            <div class="min-w-0 flex-1 space-y-6">
                <header class="space-y-1"><h1 class="font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">{{ $title }}</h1><p class="font-body-md text-on-surface-variant">{{ $description }}</p></header>
                <x-admin.flash />
                @if ($errors->any())<div role="alert" class="rounded-xl border border-error/30 bg-error-container px-4 py-3 text-on-error-container"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                @if ($tab === 'profile')
                    @if ($profileCompletion < 100)
                        <section class="rounded-xl border border-outline-variant bg-surface p-4 md:p-5"><div class="mb-2 flex items-center justify-between"><p class="font-label-md font-semibold">Hoàn thiện hồ sơ</p><span class="font-label-sm font-semibold text-primary">{{ $profileCompletion }}%</span></div><div class="h-2 overflow-hidden rounded-full bg-surface-container-high"><div class="h-full rounded-full bg-primary" style="width: {{ $profileCompletion }}%"></div></div><p class="mt-2 font-body-sm text-on-surface-variant">Tải ảnh đại diện để hoàn thiện hồ sơ reviewer.</p></section>
                    @endif
                    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
                        <div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6"><h2 class="font-title-md">Thông tin tài khoản</h2><p class="mt-0.5 font-body-sm text-on-surface-variant">Ảnh đại diện, tên hiển thị và email đăng nhập.</p></div>
                        <div class="space-y-6 p-5 md:p-6">
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">@include('auth::partials.avatar', ['user' => $user, 'size' => 'lg'])<div><p class="font-headline-sm">{{ $user->name }}</p><p class="font-body-md text-on-surface-variant">{{ $user->email }}</p><span class="mt-2 inline-flex rounded-full bg-primary/10 px-3 py-1 font-label-sm font-medium text-primary">Reviewer</span></div></div>
                            @can('profile.avatar_update')
                                <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest/50 p-4"><form method="post" action="{{ route('reviewer.profile.avatar') }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-end">@csrf @method('PUT')<div class="min-w-0 flex-1"><label for="avatar" class="font-label-sm font-medium text-on-surface-variant">Ảnh đại diện</label><p class="mb-2 font-body-sm text-on-surface-variant">JPG, PNG hoặc WebP — tối đa 2 MB</p><input id="avatar" name="avatar" type="file" required accept="image/jpeg,image/png,image/webp" class="block w-full text-body-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-on-primary"></div><button class="rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary">Tải lên</button></form>@if (filled($user->avatar_path))<form method="post" action="{{ route('reviewer.profile.avatar.destroy') }}" class="mt-3">@csrf @method('DELETE')<button class="font-label-sm text-on-surface-variant hover:text-error">Xóa ảnh hiện tại</button></form>@endif</div>
                            @endcan
                            @can('profile.update')<form method="post" action="{{ route('reviewer.profile.update') }}" class="border-t border-outline-variant pt-5">@csrf @method('PUT')<label for="name" class="font-label-sm font-medium text-on-surface-variant">Tên hiển thị</label><input id="name" name="name" required value="{{ old('name', $user->name) }}" class="{{ $inputClass }}"><div class="mt-4 flex justify-end"><button class="rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary">Lưu thay đổi</button></div></form>@endcan
                        </div>
                    </section>
                @elseif ($tab === 'security')
                    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm"><div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6"><h2 class="font-title-md">Đổi mật khẩu</h2><p class="mt-0.5 font-body-sm text-on-surface-variant">Sử dụng mật khẩu mạnh, tối thiểu 8 ký tự.</p></div><form method="post" action="{{ route('reviewer.profile.password') }}" class="space-y-4 p-5 md:p-6">@csrf @method('PUT')@foreach (['current_password' => ['Mật khẩu hiện tại', 'current-password'], 'password' => ['Mật khẩu mới', 'new-password'], 'password_confirmation' => ['Xác nhận mật khẩu mới', 'new-password']] as $field => [$label, $autocomplete])<div class="max-w-md"><label for="{{ $field }}" class="font-label-sm font-medium text-on-surface-variant">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="password" required autocomplete="{{ $autocomplete }}" class="{{ $inputClass }}"></div>@endforeach<div class="flex justify-end border-t border-outline-variant pt-4"><button class="rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary">Cập nhật mật khẩu</button></div></form></section>
                    @can('profile.two_factor_toggle')<section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm"><div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6"><h2 class="flex items-center gap-2 font-title-md"><span class="material-symbols-outlined text-primary">phonelink_lock</span>Xác thực hai bước (2FA)</h2><p class="mt-0.5 font-body-sm text-on-surface-variant">Bảo vệ tài khoản bằng mã từ ứng dụng Authenticator.</p></div><div class="p-5 md:p-6">@if ($user->hasTwoFactorEnabled())<span class="mb-5 inline-flex rounded-md bg-primary/10 px-2.5 py-1 font-label-sm font-semibold text-primary">Đã bật</span><form method="post" action="{{ route('reviewer.profile.2fa.disable') }}" class="max-w-md space-y-4">@csrf @method('DELETE')<label for="disable_2fa_password" class="font-label-sm text-on-surface-variant">Mật khẩu hiện tại để tắt 2FA</label><input id="disable_2fa_password" name="current_password" type="password" required class="{{ $inputClass }}"><button class="rounded-lg border border-error/40 bg-error-container px-5 py-2.5 font-label-md font-semibold text-on-error-container">Tắt xác thực hai bước</button></form>@else<a href="{{ route('reviewer.profile.2fa.setup') }}" class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary">Bật 2FA</a>@endif</div></section>@endcan
                @else
                    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm" x-data="{ theme: 'system', init() { this.theme = window.MedlearnTheme?.getStoredTheme?.() ?? 'system' }, async setTheme(value) { this.theme = value; if (window.MedlearnTheme?.setTheme) this.theme = await window.MedlearnTheme.setTheme(value) } }" x-init="init()"><div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6"><h2 class="font-title-md">Chế độ giao diện</h2><p class="mt-0.5 font-body-sm text-on-surface-variant">Lưu theo tài khoản và đồng bộ khi đăng nhập.</p></div><div class="p-5 md:p-6"><div class="grid max-w-md grid-cols-3 overflow-hidden rounded-lg border border-outline-variant"><template x-for="option in [{ value: 'light', label: 'Sáng' }, { value: 'dark', label: 'Tối' }, { value: 'system', label: 'Hệ thống' }]" :key="option.value"><button type="button" @click="setTheme(option.value)" x-text="option.label" class="border-r border-outline-variant px-2 py-2.5 font-label-md font-bold last:border-r-0" :class="theme === option.value ? 'bg-primary-container text-on-primary-container' : 'bg-surface text-on-surface-variant'"></button></template></div></div></section>
                @endif
            </div>
        </div>
    </div>
</x-layouts.reviewer>
