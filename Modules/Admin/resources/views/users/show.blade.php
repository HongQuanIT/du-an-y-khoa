<x-layouts.admin title="{{ $user->name }}">
    <x-admin.page-header :title="$user->name" :description="$user->email">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-12">
        <section class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm xl:col-span-8" aria-labelledby="user-information-heading">
            <div class="mb-5 flex items-center gap-3 border-b border-outline-variant pb-4">
                <span class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                    <span class="material-symbols-outlined text-[22px]">person</span>
                </span>
                <div>
                    <h2 id="user-information-heading" class="font-headline-sm text-headline-sm text-on-surface">Thông tin tài khoản</h2>
                    <p class="mt-0.5 font-body-sm text-on-surface-variant">Thông tin định danh và quyền truy cập hiện tại.</p>
                </div>
            </div>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-5 font-body-sm sm:grid-cols-2">
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Mã người dùng</dt>
                    <dd class="mt-1 font-medium text-on-surface">#{{ $user->id }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Cổng truy cập</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ \App\Support\Enums\Role::tryFromName($user->primaryRoleName())?->portal()->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Vai trò</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ \App\Support\Enums\Role::tryFromName($user->primaryRoleName())?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Trạng thái</dt>
                    <dd class="mt-1"><span class="inline-flex rounded-full bg-surface-container px-2.5 py-1 font-label-sm font-medium text-on-surface">{{ ($user->status ?? \App\Support\Enums\UserStatus::Active)->label() }}</span></dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Xác minh email</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ $user->email_verified_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Chưa xác minh' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Xác thực hai bước</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ $user->hasTwoFactorEnabled() ? 'Đã bật' : 'Chưa bật' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Ngày tạo</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ $user->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </section>

        <aside class="space-y-4 xl:col-span-4" aria-labelledby="account-actions-heading">
            <h2 id="account-actions-heading" class="sr-only">Thao tác quản lý tài khoản</h2>

            @if (! $canManage)
                <div class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
                    <h3 class="font-label-lg font-semibold text-on-surface">Không thể thao tác</h3>
                    <p class="mt-2 font-body-sm text-on-surface-variant">Bạn không có quyền quản lý tài khoản này hoặc đây là tài khoản của chính bạn.</p>
                </div>
            @else
                <form method="post" action="{{ route('admin.users.role', $user) }}" class="space-y-4 rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <div>
                        <h3 class="font-label-lg font-semibold text-on-surface">Quyền truy cập</h3>
                        <p class="mt-1 font-body-sm text-on-surface-variant">Chọn cổng truy cập và vai trò của người dùng.</p>
                    </div>
                    @include('admin::partials.portal-role-picker', [
                        'assignableRoles' => $assignableRoles,
                        'selectedRole' => old('role', $user->primaryRoleName()),
                    ])
                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 font-label-md font-medium text-on-primary transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                        Lưu quyền truy cập
                    </button>
                </form>

                <form method="post" action="{{ route('admin.users.status', $user) }}" class="space-y-4 rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <div>
                        <h3 class="font-label-lg font-semibold text-on-surface">Trạng thái tài khoản</h3>
                        <p class="mt-1 font-body-sm text-on-surface-variant">Kiểm soát khả năng đăng nhập và sử dụng hệ thống.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="status">Trạng thái mới</label>
                        <select id="status" name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(($user->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="status-reason">Lý do thay đổi <span class="font-normal">(tùy chọn)</span></label>
                        <input id="status-reason" type="text" name="reason" placeholder="Nhập lý do"
                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-lg border border-outline-variant px-4 font-label-md font-medium text-on-surface transition hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-primary/20">Cập nhật trạng thái</button>
                </form>

                <section class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm" aria-labelledby="security-actions-heading">
                    <div class="mb-4">
                        <h3 id="security-actions-heading" class="font-label-lg font-semibold text-on-surface">Bảo mật tài khoản</h3>
                        <p class="mt-1 font-body-sm text-on-surface-variant">Hỗ trợ người dùng khôi phục quyền truy cập.</p>
                    </div>
                    <div class="flex flex-col gap-2">
                    <form method="post" action="{{ route('admin.users.reset-password', $user) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant px-4 font-label-md font-medium text-on-surface transition hover:bg-surface-container-low"
                            onclick="return confirm('Gửi email đặt lại mật khẩu?')">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">key</span>
                            Gửi email đặt lại mật khẩu
                        </button>
                    </form>
                    @unless ($user->email_verified_at)
                        <form method="post" action="{{ route('admin.users.verify-email', $user) }}">
                            @csrf
                            <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant px-4 font-label-md font-medium text-on-surface transition hover:bg-surface-container-low">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">mark_email_read</span>
                                Xác minh email
                            </button>
                        </form>
                    @endunless
                    </div>
                </section>
            @endif
        </aside>
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
