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
                <form method="post" action="{{ route('admin.users.role', $user) }}" class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <label class="block font-label-sm text-label-sm text-on-surface-variant" for="role">Đổi vai trò</label>
                    <select id="role" name="role" class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
                        @foreach ($assignableRoles as $role)
                            <option value="{{ $role->value }}" @selected($user->primaryRoleName() === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
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
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Audit gần đây</h3>
            @can('audit.view')
                <a href="{{ route('admin.audit.index', ['actor_id' => '']) }}" class="font-label-md text-label-md text-primary hover:underline">Mở Audit</a>
            @endcan
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left font-body-sm text-body-sm">
                <thead class="border-b border-outline-variant font-label-md text-label-md text-on-surface-variant">
                    <tr>
                        <th class="py-2 pe-4">Thời gian</th>
                        <th class="py-2 pe-4">Action</th>
                        <th class="py-2">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($audits as $log)
                        <tr class="border-b border-outline-variant/50">
                            <td class="py-2 pe-4 whitespace-nowrap text-on-surface-variant">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pe-4"><a href="{{ route('admin.audit.show', $log) }}" class="text-primary hover:underline">{{ $log->action }}</a></td>
                            <td class="py-2 text-on-surface-variant truncate max-w-xs">{{ $log->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-on-surface-variant">Chưa có nhật ký trên user này.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
