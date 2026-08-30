<x-layouts.admin title="Tạo người dùng">
    <x-admin.page-header title="Tạo người dùng"
        description="Chọn portal trước, rồi chọn role. Permission lấy từ ma trận role — không tick từng quyền.">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @if ($assignableRoles === [])
        <p class="rounded-xl border border-outline-variant bg-surface p-5 font-body-sm text-body-sm text-on-surface-variant">
            Bạn không có quyền gán vai trò cho người dùng mới.
        </p>
    @else
        <form method="post" action="{{ route('admin.users.store') }}"
            class="max-w-2xl space-y-6 rounded-xl border border-outline-variant bg-surface p-6">
            @csrf

            @include('admin::partials.portal-role-picker', [
                'assignableRoles' => $assignableRoles,
                'selectedRole' => old('role'),
            ])

            <div class="space-y-3 border-t border-outline-variant pt-5">
                <p class="font-label-sm text-label-sm text-on-surface-variant">3. Thông tin tài khoản</p>
                <div>
                    <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="name">Họ tên</label>
                    <input id="name" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
                    @error('name')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                        class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
                    @error('email')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" required minlength="8"
                        class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
                    @error('password')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <p class="font-body-sm text-body-sm text-on-surface-variant">
                Nếu chọn Cộng tác viên, sau khi tạo tài khoản bạn sẽ được chuyển sang hoàn tất hồ sơ CTV (tên hiển thị, % hoa hồng).
            </p>

            <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary">Tạo người dùng</button>
        </form>
    @endif
</x-layouts.admin>
