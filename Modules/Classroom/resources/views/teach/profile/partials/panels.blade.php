@php
    $inputClass = 'h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $labelClass = 'font-label-sm text-label-sm font-medium text-on-surface-variant';
    $cardHeaderClass = 'border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6';
    $cardBodyClass = 'p-5 md:p-6';
    $cardClass = 'overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm';

    $titles = [
        'Giảng viên',
        'Bác sĩ',
        'Trợ giảng',
        'Chuyên gia lâm sàng',
        'Khác',
    ];
@endphp

@if ($tab === 'profile')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h3 class="font-title-md text-title-md text-on-surface">Ảnh &amp; giới thiệu</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Avatar và bio ngắn hiện khi bạn host buổi chữa đề.
            </p>
        </div>
        <div class="{{ $cardBodyClass }} space-y-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                @include('auth::partials.avatar', ['user' => $user, 'size' => 'lg'])
                <div class="min-w-0 flex-1 space-y-2">
                    <p class="font-headline-sm text-headline-sm text-on-surface">{{ $user->name }}</p>
                    <p class="font-body-md text-body-md text-on-surface-variant">{{ $user->email }}</p>
                    <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-medium text-primary">
                        Giảng viên
                    </span>
                </div>
            </div>

            <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest/50 p-4">
                <form method="post" action="{{ route('teach.profile.avatar') }}" enctype="multipart/form-data"
                    class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    @method('PUT')
                    <div class="min-w-0 flex-1">
                        <label for="avatar" class="{{ $labelClass }}">Ảnh đại diện</label>
                        <p class="mb-2 font-body-sm text-body-sm text-on-surface-variant">JPG, PNG hoặc WebP — tối đa 2 MB</p>
                        <input id="avatar" name="avatar" type="file" required accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-body-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:font-label-md file:text-label-md file:text-on-primary hover:file:opacity-90">
                    </div>
                    <button type="submit"
                        class="shrink-0 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Tải lên
                    </button>
                </form>
                @if (filled($user->getAttributes()['avatar_path'] ?? null))
                    <form method="post" action="{{ route('teach.profile.avatar.destroy') }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="font-label-sm text-label-sm text-on-surface-variant underline-offset-2 hover:text-error hover:underline">
                            Xóa ảnh hiện tại
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section class="{{ $cardClass }} mt-6">
        <div class="{{ $cardHeaderClass }}">
            <h3 class="font-title-md text-title-md text-on-surface">Thông tin giảng dạy</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Chức danh, chuyên ngành và cơ sở — giúp học viên nhận diện host.
            </p>
        </div>
        <form method="post" action="{{ route('teach.profile.update') }}" class="{{ $cardBodyClass }} space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label for="name" class="{{ $labelClass }}">Tên hiển thị</label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}"
                        class="{{ $inputClass }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="career_role" class="{{ $labelClass }}">Chức danh</label>
                    <select id="career_role" name="career_role" class="{{ $inputClass }}">
                        <option value="">— Chọn —</option>
                        @foreach ($titles as $title)
                            <option value="{{ $title }}" @selected(old('career_role', $user->getAttributes()['career_role'] ?? null) === $title)>{{ $title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="specialty" class="{{ $labelClass }}">Chuyên ngành</label>
                    <input id="specialty" name="specialty" type="text" value="{{ old('specialty', $user->getAttributes()['specialty'] ?? '') }}"
                        placeholder="VD: Nội khoa, Tim mạch"
                        class="{{ $inputClass }}">
                </div>
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label for="institution" class="{{ $labelClass }}">Cơ sở / trường</label>
                    <input id="institution" name="institution" type="text"
                        value="{{ old('institution', $user->getAttributes()['institution'] ?? '') }}"
                        placeholder="VD: Đại học Y Dược TP.HCM"
                        class="{{ $inputClass }}">
                </div>
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label for="headline" class="{{ $labelClass }}">Giới thiệu ngắn</label>
                    <textarea id="headline" name="headline" rows="3" maxlength="280"
                        placeholder="2–3 câu về kinh nghiệm chữa đề / lĩnh vực chuyên môn"
                        class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 font-body-md text-body-md text-on-surface transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ old('headline', $user->getAttributes()['headline'] ?? '') }}</textarea>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Tối đa 280 ký tự.</p>
                </div>
            </div>

            <div class="flex justify-end border-t border-outline-variant pt-4">
                <button type="submit"
                    class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Lưu hồ sơ
                </button>
            </div>
        </form>
    </section>

@elseif ($tab === 'contact')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h3 class="font-title-md text-title-md text-on-surface">Thông tin liên hệ</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Email đăng nhập do quản trị cấp — không đổi tại đây.
            </p>
        </div>
        <form method="post" action="{{ route('teach.profile.contact') }}" class="{{ $cardBodyClass }} space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="contact_name" class="{{ $labelClass }}">Tên hiển thị</label>
                    <input id="contact_name" name="name" type="text" required value="{{ old('name', $user->name) }}"
                        class="{{ $inputClass }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="contact_email" class="{{ $labelClass }}">Email</label>
                    <input id="contact_email" type="email" disabled value="{{ $user->email }}"
                        class="h-10 w-full cursor-not-allowed rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-md text-body-md text-on-surface-variant">
                </div>
            </div>

            <div class="flex justify-end border-t border-outline-variant pt-4">
                <button type="submit"
                    class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Lưu thay đổi
                </button>
            </div>
        </form>
    </section>

@elseif ($tab === 'security')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h3 class="font-title-md text-title-md text-on-surface">Đổi mật khẩu</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Dùng mật khẩu mạnh, tối thiểu 8 ký tự.
            </p>
        </div>
        <form method="post" action="{{ route('teach.profile.password') }}" class="{{ $cardBodyClass }} space-y-5">
            @csrf
            @method('PUT')

            <div class="max-w-md space-y-4">
                <div class="flex flex-col gap-1.5">
                    <label for="current_password" class="{{ $labelClass }}">Mật khẩu hiện tại</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                        class="{{ $inputClass }} @error('current_password') border-error @enderror">
                    @error('current_password')
                        <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="password" class="{{ $labelClass }}">Mật khẩu mới</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        class="{{ $inputClass }} @error('password') border-error @enderror">
                    @error('password')
                        <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="password_confirmation" class="{{ $labelClass }}">Xác nhận mật khẩu mới</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        class="{{ $inputClass }}">
                </div>
            </div>

            <div class="flex justify-end border-t border-outline-variant pt-4">
                <button type="submit"
                    class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Cập nhật mật khẩu
                </button>
            </div>
        </form>
    </section>

@elseif ($tab === 'appearance')
    <section class="{{ $cardClass }}"
        x-data="{
            theme: 'system',
            init() {
                this.theme = window.MedlearnTheme?.getStoredTheme?.() ?? 'system';
            },
            async setTheme(value) {
                this.theme = value;
                if (window.MedlearnTheme?.setTheme) {
                    this.theme = await window.MedlearnTheme.setTheme(value);
                }
            },
        }"
        x-init="init()">
        <div class="{{ $cardHeaderClass }}">
            <h3 class="font-title-md text-title-md text-on-surface">Chế độ giao diện</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Lưu theo tài khoản — đồng bộ mọi thiết bị khi đăng nhập.
            </p>
        </div>
        <div class="{{ $cardBodyClass }}">
            <fieldset>
                <legend class="{{ $labelClass }}">Giao diện</legend>
                <div class="mt-2 grid max-w-md grid-cols-3 overflow-hidden rounded-lg border border-outline-variant">
                    <template x-for="option in [{ value: 'light', label: 'Sáng' }, { value: 'dark', label: 'Tối' }, { value: 'system', label: 'Hệ thống' }]" :key="option.value">
                        <button type="button" @click="setTheme(option.value)" x-text="option.label"
                            class="border-r border-outline-variant px-2 py-2.5 font-label-md text-label-md font-bold last:border-r-0"
                            :class="theme === option.value ? 'bg-primary-container text-on-primary-container' : 'bg-surface text-on-surface-variant'"></button>
                    </template>
                </div>
            </fieldset>
        </div>
    </section>
@endif
