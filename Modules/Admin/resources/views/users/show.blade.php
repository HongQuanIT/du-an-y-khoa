<x-layouts.admin title="{{ $user->name }}">
    <x-admin.page-header :title="$user->name" :description="$user->email">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-outline-variant bg-surface p-5 lg:col-span-2 space-y-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Thông tin</h3>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 font-body-sm text-body-sm">
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">ID</dt>
                    <dd class="text-on-surface">{{ $user->id }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Portal</dt>
                    <dd class="text-on-surface">{{ \App\Support\Enums\Role::tryFromName($user->primaryRoleName())?->portal()->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Vai trò</dt>
                    <dd class="text-on-surface">{{ \App\Support\Enums\Role::tryFromName($user->primaryRoleName())?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Trạng thái</dt>
                    <dd class="text-on-surface">{{ ($user->status ?? \App\Support\Enums\UserStatus::Active)->label() }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Email verified</dt>
                    <dd class="text-on-surface">{{ $user->email_verified_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Chưa' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">2FA</dt>
                    <dd class="text-on-surface">{{ $user->hasTwoFactorEnabled() ? 'Đã bật' : 'Chưa' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Tạo lúc</dt>
                    <dd class="text-on-surface">{{ $user->created_at?->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface p-5 space-y-5">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Thao tác</h3>

            @if (! $canManage)
                <p class="font-body-sm text-body-sm text-on-surface-variant">Bạn không thể quản lý tài khoản này (thiếu quyền hoặc tự thao tác).</p>
            @else
                <form method="post" action="{{ route('admin.users.role', $user) }}" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Đổi portal / vai trò</p>
                    @include('admin::partials.portal-role-picker', [
                        'assignableRoles' => $assignableRoles,
                        'selectedRole' => old('role', $user->primaryRoleName()),
                    ])
                    <button type="submit" class="w-full rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary">Lưu vai trò</button>
                </form>

                <form method="post" action="{{ route('admin.users.status', $user) }}" class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <label class="block font-label-sm text-label-sm text-on-surface-variant" for="status">Trạng thái</label>
                    <select id="status" name="status" class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($user->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="reason" placeholder="Lý do (tuỳ chọn)"
                        class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
                    <button type="submit" class="w-full rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface hover:bg-surface-container-low">Cập nhật trạng thái</button>
                </form>

                <div class="flex flex-col gap-2 border-t border-outline-variant pt-4">
                    <form method="post" action="{{ route('admin.users.reset-password', $user) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface hover:bg-surface-container-low"
                            onclick="return confirm('Gửi email đặt lại mật khẩu?')">Gửi reset mật khẩu</button>
                    </form>
                    @unless ($user->email_verified_at)
                        <form method="post" action="{{ route('admin.users.verify-email', $user) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface hover:bg-surface-container-low">Xác minh email</button>
                        </form>
                    @endunless
                </div>
            @endif
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-outline-variant bg-surface p-5">
        <div class="mb-1 flex flex-wrap items-end justify-between gap-2">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Hoạt động gần đây</h3>
            <p class="font-label-sm text-label-sm text-on-surface-variant">Màn hình đã mở · cùng trang trong 30 phút được gộp một dòng</p>
        </div>
        <ul class="divide-y divide-outline-variant/60">
            @forelse ($activities as $activity)
                @php($row = \App\Support\Audit\UserActivityPresenter::present($activity))
                <li class="py-3 first:pt-1 last:pb-0">
                    <p class="font-label-sm text-label-sm text-on-surface-variant" title="{{ $row['when_exact'] }}">{{ $row['when'] }}</p>
                    <p class="mt-0.5 font-body-md text-body-md text-on-surface">{{ $row['summary'] }}</p>
                    <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $row['detail'] }}</p>
                </li>
            @empty
                <li class="py-6 font-body-sm text-body-sm text-on-surface-variant">Chưa ghi nhận hoạt động gần đây của người dùng này.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.admin>
