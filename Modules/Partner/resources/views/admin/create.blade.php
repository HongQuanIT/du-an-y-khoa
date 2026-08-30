<x-layouts.admin title="Thêm cộng tác viên">
    <x-admin.page-header title="Thêm cộng tác viên"
        description="Tạo tài khoản mới hoặc gắn role partner cho user hiện có." />

    <x-admin.flash />

    <form method="post" action="{{ route('admin.partners.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-outline-variant bg-surface p-6"
        x-data="{ mode: '{{ old('mode', $prefillMode ?? 'new') }}' }">
        @csrf
        <div class="flex gap-4">
            <label class="inline-flex items-center gap-2 font-body-sm">
                <input type="radio" name="mode" value="new" x-model="mode"> Tạo user mới
            </label>
            <label class="inline-flex items-center gap-2 font-body-sm">
                <input type="radio" name="mode" value="existing" x-model="mode"> Gắn user có sẵn
            </label>
        </div>

        <div class="space-y-3" x-show="mode === 'new'">
            <div>
                <label class="mb-1 block font-label-sm" for="name">Họ tên</label>
                <input id="name" name="name" value="{{ old('name') }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block font-label-sm" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block font-label-sm" for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" class="w-full rounded-lg bg-surface-container-low px-3 py-2">
            </div>
        </div>

        <div x-show="mode === 'existing'">
            <label class="mb-1 block font-label-sm" for="user_id">User ID</label>
            <input id="user_id" name="user_id" type="number" value="{{ old('user_id', $prefillUserId ?? '') }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2">
        </div>

        <div>
            <label class="mb-1 block font-label-sm" for="display_name">Tên hiển thị CTV</label>
            <input id="display_name" name="display_name" required value="{{ old('display_name') }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2">
        </div>
        <div>
            <label class="mb-1 block font-label-sm" for="default_commission_rate_percent">% hoa hồng mặc định</label>
            <input id="default_commission_rate_percent" name="default_commission_rate_percent" type="number" step="0.01" min="0" max="100"
                value="{{ old('default_commission_rate_percent', $defaultCommissionPercent) }}"
                class="w-full rounded-lg bg-surface-container-low px-3 py-2">
            <p class="mt-1 font-label-sm text-on-surface-variant">
                Để trống sẽ dùng cấu hình hệ thống ({{ $defaultCommissionPercent }}%). Sửa tại Cài đặt → Cộng tác viên.
            </p>
        </div>

        <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary">Tạo</button>
    </form>
</x-layouts.admin>
