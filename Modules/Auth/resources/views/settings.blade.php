@php
    $tab = $tab ?? 'contact';
    $navActive = match ($tab) {
        'membership' => 'membership',
        'invoices' => 'invoices',
        'redeem' => 'redeem',
        'notes' => 'notes',
        'org-license' => 'org-license',
        default => 'contact',
    };
    $pageTitle = match ($tab) {
        'membership' => 'Gói & giấy phép',
        'invoices' => 'Hóa đơn',
        'redeem' => 'Đổi mã',
        'notes' => 'Ghi chú & khác',
        'org-license' => 'Giấy phép tổ chức',
        'security' => 'Liên hệ & cài đặt',
        'notifications' => 'Liên hệ & cài đặt',
        default => 'Liên hệ & cài đặt',
    };
    $membership = $membership ?? ['plan_name' => 'Free', 'description' => 'Quyền truy cập cơ bản', 'ends_at' => null, 'source' => null];
    $invoices = $invoices ?? collect();
    $orgMembers = $orgMembers ?? collect();
    $contactPanel = match ($tab) {
        'security' => 'security',
        'notifications' => 'notifications',
        default => 'contact',
    };
@endphp

<x-layouts.app :title="$pageTitle">
    <div class="mx-auto w-full max-w-[920px] space-y-8 px-margin-mobile py-8 md:px-margin-desktop md:py-10">
        @include('auth::partials.account-nav', ['active' => $navActive])

        <div>
            <h1 class="mb-2 font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">{{ $pageTitle }}</h1>
            @if ($navActive === 'contact')
                <p class="font-body-lg text-body-lg text-on-surface-variant">
                    Quản lý email, mật khẩu và tùy chọn thông báo.
                </p>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-primary/25 bg-primary-fixed/25 px-4 py-3 font-body-md text-body-md text-primary">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-error/30 bg-error-container px-4 py-3 font-body-md text-body-md text-on-error-container">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (in_array($tab, ['contact', 'security', 'notifications', 'account'], true))
            <div class="space-y-6">
                {{-- Sub-nav — each panel has its own URL ?tab= --}}
                <nav class="flex flex-wrap gap-2 border-b border-outline-variant pb-3" aria-label="Liên hệ & cài đặt">
                    <a href="{{ route('settings.edit', ['tab' => 'contact']) }}"
                        @class([
                            'rounded-md px-3 py-1.5 font-label-md text-label-md transition-colors',
                            'bg-primary/10 font-semibold text-primary' => $contactPanel === 'contact',
                            'text-on-surface-variant hover:bg-surface-container-low' => $contactPanel !== 'contact',
                        ])>
                        Thông tin liên hệ
                    </a>
                    <a href="{{ route('settings.edit', ['tab' => 'security']) }}"
                        @class([
                            'rounded-md px-3 py-1.5 font-label-md text-label-md transition-colors',
                            'bg-primary/10 font-semibold text-primary' => $contactPanel === 'security',
                            'text-on-surface-variant hover:bg-surface-container-low' => $contactPanel !== 'security',
                        ])>
                        Bảo mật
                    </a>
                    <a href="{{ route('settings.edit', ['tab' => 'notifications']) }}"
                        @class([
                            'rounded-md px-3 py-1.5 font-label-md text-label-md transition-colors',
                            'bg-primary/10 font-semibold text-primary' => $contactPanel === 'notifications',
                            'text-on-surface-variant hover:bg-surface-container-low' => $contactPanel !== 'notifications',
                        ])>
                        Thông báo
                    </a>
                </nav>

                @if ($contactPanel === 'contact')
                <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                    @include('auth::partials.avatar-upload', ['user' => $user])

                    <form method="post" action="{{ route('settings.profile') }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="contact">
                        <h2 class="flex items-center gap-2 font-title-md text-title-md text-on-surface">
                            <span class="material-symbols-outlined text-primary text-[20px]">mail</span>
                            Thông tin liên hệ
                        </h2>
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <label for="name" class="font-label-sm text-label-sm text-on-surface-variant">Tên hiển thị</label>
                                <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}"
                                    class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="email" class="font-label-sm text-label-sm text-on-surface-variant">Email</label>
                                <input id="email" type="email" disabled value="{{ $user->email }}"
                                    class="h-10 cursor-not-allowed rounded-md border border-outline-variant bg-surface-container-low px-3 font-body-md text-body-md text-on-surface-variant">
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 border-t border-outline-variant pt-5">
                            <a href="{{ route('profile.show') }}"
                                class="rounded-md border border-outline px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                                Hủy
                            </a>
                            <button type="submit"
                                class="rounded-md bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                                Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </section>
                @elseif ($contactPanel === 'security')
                <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                    <form method="post" action="{{ route('settings.password') }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        <h2 class="flex items-center gap-2 font-title-md text-title-md text-on-surface">
                            <span class="material-symbols-outlined text-primary text-[20px]">lock</span>
                            Đổi mật khẩu
                        </h2>
                        <div class="max-w-md space-y-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="current_password" class="font-label-sm text-label-sm text-on-surface-variant">Mật khẩu hiện tại</label>
                                <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                                    class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 @error('current_password') border-error @enderror"
                                    placeholder="••••••••">
                                @error('current_password')
                                    <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                                @enderror
                                <p class="mt-1">
                                    <a href="{{ route('password.request') }}"
                                        class="font-label-sm text-label-sm text-primary hover:underline">
                                        Quên mật khẩu?
                                    </a>
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="password" class="font-label-sm text-label-sm text-on-surface-variant">Mật khẩu mới</label>
                                <input id="password" name="password" type="password" required autocomplete="new-password"
                                    class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 @error('password') border-error @enderror"
                                    placeholder="Tối thiểu 8 ký tự">
                                @error('password')
                                    <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="password_confirmation" class="font-label-sm text-label-sm text-on-surface-variant">Xác nhận mật khẩu mới</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                    class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-outline-variant pt-5">
                            <button type="submit"
                                class="rounded-md bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                                Cập nhật mật khẩu
                            </button>
                        </div>
                    </form>
                </section>

                @unless (\App\Support\Auth\Staff::isStaff(auth()->user()))
                <section class="mt-6 rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                    <h2 class="mb-2 flex items-center gap-2 font-title-md text-title-md text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[20px]">phonelink_lock</span>
                        Xác thực hai bước (2FA)
                    </h2>
                    <p class="mb-6 max-w-xl font-body-md text-body-md text-on-surface-variant">
                        Tăng bảo mật bằng mã từ ứng dụng Authenticator. Mặc định không bắt buộc — chỉ cần khi bạn bật.
                    </p>

                    @if (auth()->user()->hasTwoFactorEnabled())
                        <div class="mb-5 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-md bg-primary/10 px-2.5 py-1 font-label-sm text-label-sm font-semibold text-primary">
                                Đã bật
                            </span>
                        </div>
                        <form method="post" action="{{ route('settings.2fa.disable') }}" class="max-w-md space-y-4">
                            @csrf
                            @method('DELETE')
                            <div class="flex flex-col gap-1.5">
                                <label for="disable_2fa_password" class="font-label-sm text-label-sm text-on-surface-variant">
                                    Mật khẩu hiện tại để tắt 2FA
                                </label>
                                <input id="disable_2fa_password" name="current_password" type="password" required autocomplete="current-password"
                                    class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 @error('current_password') border-error @enderror"
                                    placeholder="••••••••">
                                @error('current_password')
                                    <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                class="rounded-md border border-error/40 bg-error-container px-5 py-2.5 font-label-md text-label-md font-semibold text-on-error-container hover:opacity-90">
                                Tắt xác thực hai bước
                            </button>
                        </form>
                    @else
                        <a href="{{ route('settings.2fa.setup') }}"
                            class="inline-flex rounded-md bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                            Bật 2FA
                        </a>
                    @endif
                </section>
                @endunless
                @elseif ($contactPanel === 'notifications')
                <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                    <form method="post" action="{{ route('settings.notifications') }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        <h2 class="flex items-center gap-2 font-title-md text-title-md text-on-surface">
                            <span class="material-symbols-outlined text-primary text-[20px]">notifications</span>
                            Tùy chọn thông báo
                        </h2>
                        <div class="space-y-3">
                            @foreach ([
                                'email_session' => ['title' => 'Email phiên học', 'hint' => 'Nhận tóm tắt khi hoàn thành phiên Q-Bank.'],
                                'email_plan' => ['title' => 'Nhắc kế hoạch học', 'hint' => 'Thông báo nhiệm vụ Study Plan đến hạn.'],
                                'email_product' => ['title' => 'Cập nhật sản phẩm', 'hint' => 'Tin tức tính năng mới và mẹo học tập.'],
                                'push_reminders' => ['title' => 'Nhắc trong ứng dụng', 'hint' => 'Hiển thị badge thông báo trên thanh trên.'],
                            ] as $key => $meta)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant p-4 hover:bg-surface-container-low">
                                    <input type="checkbox" name="{{ $key }}" value="1"
                                        class="mt-1 size-4 rounded border-outline text-primary focus:ring-primary"
                                        @checked(old($key, $prefs[$key] ?? false))>
                                    <span>
                                        <span class="block font-label-md text-label-md font-semibold text-on-surface">{{ $meta['title'] }}</span>
                                        <span class="block font-body-sm text-body-sm text-on-surface-variant">{{ $meta['hint'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex justify-end border-t border-outline-variant pt-5">
                            <button type="submit"
                                class="rounded-md bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                                Lưu tùy chọn
                            </button>
                        </div>
                    </form>
                </section>
                @endif
            </div>
        @elseif ($tab === 'membership')
            <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                <p class="mb-1 font-label-md text-label-md font-semibold text-on-surface">Gói hiện tại</p>
                <p class="mb-5 font-body-md text-body-md text-on-surface-variant">
                    {{ $membership['plan_name'] }} — {{ $membership['description'] }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('landing.pricing') }}"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        <span class="material-symbols-outlined text-[18px]">stars</span>
                        Xem bảng giá / nâng cấp
                    </a>
                    <a href="{{ route('settings.edit', ['tab' => 'redeem']) }}"
                        class="inline-flex items-center gap-2 rounded-md border border-outline px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                        Đổi mã
                    </a>
                    <a href="{{ route('settings.edit', ['tab' => 'org-license']) }}"
                        class="inline-flex items-center gap-2 rounded-md border border-outline px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                        Giấy phép tổ chức
                    </a>
                </div>
            </section>
        @elseif ($tab === 'org-license')
            <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
                @forelse ($orgMembers as $member)
                    @php
                        $institution = $member->institution;
                        $validUntil = $institution?->valid_until?->locale('vi')->isoFormat('D [tháng] M YYYY');
                    @endphp
                    <div @class(['flex flex-col gap-4 px-5 py-5 md:flex-row md:items-center md:gap-6 md:px-6', 'border-t border-outline-variant' => ! $loop->first])>
                        <div class="flex min-w-0 flex-1 items-start gap-3 md:items-center">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-secondary-container/70 text-primary">
                                <span class="material-symbols-outlined text-[22px]">account_balance</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-body-md text-body-md font-medium text-on-surface" title="{{ $institution?->name }}">
                                        {{ $institution?->name }}
                                    </p>
                                    @if ($member->isVerified())
                                        <span class="inline-flex shrink-0 items-center rounded-full bg-primary-fixed/60 px-2.5 py-0.5 font-label-sm text-label-sm font-semibold tracking-wide text-primary uppercase">
                                            Đã xác minh
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $member->email }}</p>
                            </div>
                        </div>

                        @if ($validUntil)
                            <p class="shrink-0 font-label-sm text-label-sm tracking-wide text-on-surface-variant uppercase md:max-w-[220px] md:text-right">
                                Có hiệu lực đến ngày {{ $validUntil }}.
                            </p>
                        @endif

                        <form method="post" action="{{ route('settings.org-license.renew') }}" class="shrink-0 self-start md:self-center">
                            @csrf
                            <input type="hidden" name="member_id" value="{{ $member->id }}">
                            <button type="submit"
                                class="rounded-md border border-outline bg-surface px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low">
                                Kiểm tra gia hạn
                            </button>
                        </form>
                    </div>
                @empty
                @endforelse

                @if ($orgMembers->isNotEmpty())
                    <div class="border-t border-outline-variant"></div>
                @endif

                <div class="px-5 py-5 md:px-6">
                    <p class="mb-4 max-w-xl font-body-md text-body-md text-on-surface">
                        Kích hoạt giấy phép dành cho tổ chức của bạn bằng email thuộc miền được cấp phép.
                    </p>
                    <form method="post" action="{{ route('settings.org-license') }}" class="flex max-w-lg flex-col gap-3 sm:flex-row sm:items-start">
                        @csrf
                        <div class="flex-1">
                            <input type="email" name="institution_email" required
                                value="{{ old('institution_email', $user->email) }}"
                                placeholder="email@truong.edu.vn"
                                class="h-10 w-full rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 @error('institution_email') border-error @enderror">
                            @error('institution_email')
                                <p class="mt-1 font-body-sm text-body-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit"
                            class="shrink-0 rounded-md border border-outline bg-surface px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low">
                            Thêm giấy phép mới
                        </button>
                    </form>
                </div>
            </section>
        @elseif ($tab === 'invoices')
            <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                @if ($invoices->isEmpty())
                    <p class="font-body-md text-body-md text-on-surface-variant">Chưa có hóa đơn nào.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-left">
                            <thead>
                                <tr class="border-b border-outline-variant font-label-sm text-label-sm text-on-surface-variant">
                                    <th class="pb-3 pr-4">Số hóa đơn</th>
                                    <th class="pb-3 pr-4">Mô tả</th>
                                    <th class="pb-3 pr-4">Ngày</th>
                                    <th class="pb-3 pr-4">Số tiền</th>
                                    <th class="pb-3">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody class="font-body-md text-body-md text-on-surface">
                                @foreach ($invoices as $invoice)
                                    <tr class="border-b border-outline-variant/60 last:border-0">
                                        <td class="py-3 pr-4 font-mono text-body-sm">{{ $invoice->number }}</td>
                                        <td class="py-3 pr-4">{{ $invoice->description }}</td>
                                        <td class="py-3 pr-4">{{ $invoice->issued_at->locale('vi')->isoFormat('D/M/YYYY') }}</td>
                                        <td class="py-3 pr-4">{{ number_format($invoice->amount_cents / 100, 0, ',', '.') }} {{ $invoice->currency }}</td>
                                        <td class="py-3 capitalize">{{ $invoice->status === 'paid' ? 'Đã thanh toán' : $invoice->status }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @elseif ($tab === 'redeem')
            <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                <h2 class="mb-4 font-title-md text-title-md text-on-surface">Đổi mã kích hoạt</h2>
                <p class="mb-5 font-body-md text-body-md text-on-surface-variant">
                    Nhập mã từ trường / tổ chức hoặc mã khuyến mãi để kích hoạt quyền truy cập.
                </p>
                <form method="post" action="{{ route('settings.redeem') }}" class="flex max-w-md flex-col gap-3 sm:flex-row">
                    @csrf
                    <input type="text" name="code" required value="{{ old('code') }}" placeholder="Nhập mã..."
                        class="h-10 flex-1 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 @error('code') border-error @enderror">
                    <button type="submit"
                        class="rounded-md bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Đổi mã
                    </button>
                </form>
                @error('code')
                    <p class="mt-2 font-body-sm text-body-sm text-error">{{ $message }}</p>
                @enderror
            </section>
        @else
            <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 md:p-8">
                <form method="post" action="{{ route('settings.notes') }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <h2 class="font-title-md text-title-md text-on-surface">Ghi chú cá nhân</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">
                        Ghi chú riêng cho tài khoản — chỉ bạn mới thấy.
                    </p>
                    <textarea name="account_notes" rows="6" maxlength="5000"
                        placeholder="Ghi chú học tập, mục tiêu, nhắc nhở..."
                        class="w-full rounded-md border border-outline-variant bg-surface px-3 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ old('account_notes', $user->account_notes) }}</textarea>
                    <div class="flex justify-end border-t border-outline-variant pt-5">
                        <button type="submit"
                            class="rounded-md bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                            Lưu ghi chú
                        </button>
                    </div>
                </form>
                <a href="{{ route('profile.show') }}" class="mt-4 inline-block font-label-md text-label-md text-primary hover:underline">
                    Về hồ sơ nghề nghiệp & học tập
                </a>
            </section>
        @endif
    </div>
</x-layouts.app>
