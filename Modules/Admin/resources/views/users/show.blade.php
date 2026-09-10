<x-layouts.admin :title="'Chi tiết học viên — '.$user->name">
    <x-admin.page-header title="Chi tiết học viên" description="Thông tin tài khoản, hồ sơ học tập và lịch sử hoạt động.">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @php
        $linkedGoogleAccount = $user->socialAccounts->firstWhere('provider', \Modules\Auth\Enums\SocialProvider::Google->value);
        $linkedFacebookAccount = $user->socialAccounts->firstWhere('provider', \Modules\Auth\Enums\SocialProvider::Facebook->value);
        $loginMethods = [
            [
                'label' => 'Email/password',
                'description' => $user->email,
                'linked' => filled($user->password_set_at),
                'linkedText' => 'Đã thiết lập',
                'unlinkedText' => 'Chưa thiết lập',
                'icon' => 'mail',
            ],
            [
                'label' => 'Google',
                'description' => $linkedGoogleAccount?->provider_email ?? 'Chưa liên kết Google',
                'linked' => filled($linkedGoogleAccount),
                'linkedText' => 'Đã liên kết',
                'unlinkedText' => 'Chưa liên kết',
                'icon' => 'g_mobiledata',
                'lastLoginAt' => $linkedGoogleAccount?->last_login_at,
            ],
            [
                'label' => 'Facebook',
                'description' => $linkedFacebookAccount?->provider_email ?? 'Chưa liên kết Facebook',
                'linked' => filled($linkedFacebookAccount),
                'linkedText' => 'Đã liên kết',
                'unlinkedText' => 'Chưa liên kết',
                'icon' => 'public',
                'lastLoginAt' => $linkedFacebookAccount?->last_login_at,
            ],
        ];
    @endphp

    <section class="mb-5 overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm" aria-labelledby="learner-summary-heading">
        <div class="h-1.5 bg-primary"></div>
        <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="Ảnh đại diện của {{ $user->name }}"
                    class="size-20 shrink-0 rounded-full border-2 border-surface object-cover shadow-sm">
            @else
                <span class="flex size-20 shrink-0 items-center justify-center rounded-full bg-primary/10 font-headline-md font-bold uppercase text-primary" aria-hidden="true">
                    {{ mb_substr(trim($user->name), 0, 1) }}
                </span>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="learner-summary-heading" class="font-headline-md text-headline-md font-bold text-on-surface">{{ $user->name }}</h2>
                    <span class="inline-flex rounded-full bg-surface-container px-2.5 py-1 font-label-sm font-medium text-on-surface">
                        {{ ($user->status ?? \App\Support\Enums\UserStatus::Active)->label() }}
                    </span>
                </div>
                <p class="mt-1 break-all font-body-md text-body-md text-on-surface-variant">{{ $user->email }}</p>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-body-sm text-on-surface-variant">
                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">badge</span>Mã #{{ $user->id }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">school</span>{{ \App\Support\Enums\Role::tryFromName($user->primaryRoleName())?->label() ?? 'Chưa có vai trò' }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">login</span>{{ $user->last_login_method?->label() ?? 'Chưa ghi nhận đăng nhập' }}</span>
                </div>
            </div>

            <a href="mailto:{{ $user->email }}"
                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-outline-variant px-4 font-label-md font-medium text-on-surface transition hover:border-primary hover:text-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">mail</span>
                Gửi email
            </a>
        </div>
    </section>

    <nav class="mb-6 flex gap-2 overflow-x-auto rounded-xl border border-outline-variant bg-surface p-2" aria-label="Điều hướng chi tiết học viên">
        <a href="#account-information" class="whitespace-nowrap rounded-lg px-3 py-2 font-label-sm font-medium text-on-surface-variant hover:bg-surface-container-low hover:text-primary">Tài khoản</a>
        <a href="#learner-profile" class="whitespace-nowrap rounded-lg px-3 py-2 font-label-sm font-medium text-on-surface-variant hover:bg-surface-container-low hover:text-primary">Hồ sơ học viên</a>
        <a href="#account-security" class="whitespace-nowrap rounded-lg px-3 py-2 font-label-sm font-medium text-on-surface-variant hover:bg-surface-container-low hover:text-primary">Bảo mật</a>
        <a href="#recent-activity" class="whitespace-nowrap rounded-lg px-3 py-2 font-label-sm font-medium text-on-surface-variant hover:bg-surface-container-low hover:text-primary">Hoạt động gần đây</a>
    </nav>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-12">
        <section id="account-information" class="scroll-mt-24 rounded-xl border border-outline-variant bg-surface p-5 shadow-sm xl:col-span-8" aria-labelledby="user-information-heading">
            <div class="mb-5 flex items-center gap-3 border-b border-outline-variant pb-4">
                <span class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary" aria-hidden="true">
                    <span class="material-symbols-outlined text-[22px]">manage_accounts</span>
                </span>
                <div>
                    <h2 id="user-information-heading" class="font-headline-sm text-headline-sm text-on-surface">Thông tin tài khoản</h2>
                    <p class="mt-0.5 font-body-sm text-on-surface-variant">Thông tin định danh và quyền truy cập hiện tại.</p>
                </div>
            </div>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-5 font-body-sm sm:grid-cols-2">
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Họ và tên</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Email đăng nhập</dt>
                    <dd class="mt-1 break-all font-medium text-on-surface">{{ $user->email }}</dd>
                </div>
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
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Phương thức đăng nhập gần nhất</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ $user->last_login_method?->label() ?? 'Chưa ghi nhận' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Thời gian đăng nhập gần nhất</dt>
                    <dd class="mt-1 font-medium text-on-surface">{{ $user->last_login_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Chưa ghi nhận' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-label-sm text-on-surface-variant">Phương thức đăng nhập</dt>
                    <dd class="mt-3 grid gap-2 md:grid-cols-3">
                        @foreach ($loginMethods as $method)
                            <div class="rounded-lg border border-outline-variant bg-surface-container-low p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">{{ $method['icon'] }}</span>
                                        <span class="font-label-md font-semibold text-on-surface">{{ $method['label'] }}</span>
                                    </div>
                                    @if ($method['linked'])
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-label-sm font-semibold text-primary">
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">check_circle</span>
                                            {{ $method['linkedText'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex shrink-0 rounded-full bg-surface-container-high px-2 py-0.5 text-label-sm font-medium text-on-surface-variant">
                                            {{ $method['unlinkedText'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 truncate text-body-sm text-on-surface-variant" title="{{ $method['description'] }}">
                                    {{ $method['description'] }}
                                </p>
                                @if ($method['linked'] && isset($method['lastLoginAt']))
                                    <p class="mt-1 text-label-sm text-on-surface-variant">
                                        Đăng nhập gần nhất: {{ $method['lastLoginAt']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Chưa ghi nhận' }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </dd>
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

                <section id="account-security" class="scroll-mt-24 rounded-xl border border-outline-variant bg-surface p-5 shadow-sm" aria-labelledby="security-actions-heading">
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

    <section id="learner-profile" class="mt-6 scroll-mt-24 rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-outline-variant pb-4">
            <div><h2 class="font-headline-sm text-on-surface">Hồ sơ học viên</h2><p class="mt-0.5 text-body-sm text-on-surface-variant">Thông tin phân khúc được thu thập khi onboarding.</p></div>
            @if ($user->learnerProfile?->onboarding_completed_at)<span class="rounded-full bg-primary/10 px-3 py-1 text-label-sm font-semibold text-primary">Đã hoàn thiện</span>@elseif($user->learnerProfile)<span class="rounded-full bg-warning/10 px-3 py-1 text-label-sm font-semibold text-warning">Chưa hoàn thiện</span>@else<span class="rounded-full bg-surface-container px-3 py-1 text-label-sm text-on-surface-variant">Tài khoản cũ</span>@endif
        </div>
        @if ($user->learnerProfile)
            <dl class="grid gap-x-8 gap-y-4 text-body-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-label-sm text-on-surface-variant">Quốc gia</dt><dd class="mt-1 font-medium">{{ $user->learnerProfile->country?->name ?? '—' }}</dd></div>
                <div><dt class="text-label-sm text-on-surface-variant">Tỉnh/Thành phố</dt><dd class="mt-1 font-medium">{{ $user->learnerProfile->administrativeUnit?->name ?? '—' }}</dd></div>
                <div><dt class="text-label-sm text-on-surface-variant">Trường</dt><dd class="mt-1 font-medium">{{ $user->learnerProfile->institution?->name ?? '—' }}</dd></div>
                <div><dt class="text-label-sm text-on-surface-variant">Chức danh</dt><dd class="mt-1 font-medium">{{ $user->learnerProfile->profession?->name ?? '—' }}</dd></div>
                <div><dt class="text-label-sm text-on-surface-variant">Năm học</dt><dd class="mt-1 font-medium">{{ $user->learnerProfile->educationStage?->name ?? '—' }}</dd></div>
                <div><dt class="text-label-sm text-on-surface-variant">Phương thức đăng ký</dt><dd class="mt-1 font-medium">{{ match ($user->learnerProfile->registration_method) { 'google' => 'Google', 'facebook' => 'Facebook', default => 'Email/Mật khẩu' } }}</dd></div>
                <div><dt class="text-label-sm text-on-surface-variant">Hoàn tất hồ sơ lúc</dt><dd class="mt-1 font-medium">{{ $user->learnerProfile->onboarding_completed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Chưa hoàn tất' }}</dd></div>
            </dl>

        @else
            <p class="text-body-sm text-on-surface-variant">Tài khoản được tạo trước khi áp dụng onboarding nên chưa có dữ liệu phân khúc.</p>
        @endif
    </section>

    <section id="recent-activity" class="mt-6 scroll-mt-24 rounded-xl border border-outline-variant bg-surface p-5">
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
