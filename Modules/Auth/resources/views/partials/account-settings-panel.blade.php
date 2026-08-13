@php
    $membership = $membership ?? ['plan_name' => 'Free', 'description' => 'Quyền truy cập cơ bản', 'ends_at' => null, 'source' => null];
    $invoices = $invoices ?? collect();
    $orgMembers = $orgMembers ?? collect();

    $inputClass = 'h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $labelClass = 'font-label-sm text-label-sm font-medium text-on-surface-variant';
    $cardHeaderClass = 'border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6';
    $cardBodyClass = 'p-5 md:p-6';
    $cardClass = 'overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm';
@endphp

@if ($tab === 'contact')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h2 class="font-title-md text-title-md text-on-surface">Thông tin liên hệ</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Email đăng nhập không thể thay đổi tại đây. Ảnh đại diện được quản lý tại tab
                <a href="{{ route('profile.show') }}" class="font-medium text-primary hover:underline">Hồ sơ cá nhân</a>.
            </p>
        </div>

        <form method="post" action="{{ route('settings.profile') }}" class="{{ $cardBodyClass }} space-y-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="contact">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="name" class="{{ $labelClass }}">Tên hiển thị</label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}"
                        class="{{ $inputClass }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="email" class="{{ $labelClass }}">Email</label>
                    <input id="email" type="email" disabled value="{{ $user->email }}"
                        class="h-10 w-full cursor-not-allowed rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-md text-body-md text-on-surface-variant">
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Liên hệ hỗ trợ nếu cần đổi email.</p>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
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
            <h2 class="font-title-md text-title-md text-on-surface">Đổi mật khẩu</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Sử dụng mật khẩu mạnh, tối thiểu 8 ký tự.
            </p>
        </div>

        <form method="post" action="{{ route('settings.password') }}" class="{{ $cardBodyClass }} space-y-5">
            @csrf
            @method('PUT')

            <div class="max-w-md space-y-4">
                <div class="flex flex-col gap-1.5">
                    <label for="current_password" class="{{ $labelClass }}">Mật khẩu hiện tại</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                        placeholder="••••••••"
                        class="{{ $inputClass }} @error('current_password') border-error @enderror">
                    @error('current_password')
                        <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                    @enderror
                    <p class="mt-1">
                        <a href="{{ route('password.request') }}" class="font-label-sm text-label-sm text-primary hover:underline">
                            Quên mật khẩu?
                        </a>
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="password" class="{{ $labelClass }}">Mật khẩu mới</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        placeholder="Tối thiểu 8 ký tự"
                        class="{{ $inputClass }} @error('password') border-error @enderror">
                    @error('password')
                        <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="password_confirmation" class="{{ $labelClass }}">Xác nhận mật khẩu mới</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        placeholder="Nhập lại mật khẩu mới"
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

    @unless (\App\Support\Auth\Staff::isStaff(auth()->user()))
        <section class="{{ $cardClass }} mt-6">
            <div class="{{ $cardHeaderClass }}">
                <h2 class="flex items-center gap-2 font-title-md text-title-md text-on-surface">
                    <span class="material-symbols-outlined text-[20px] text-primary">phonelink_lock</span>
                    Xác thực hai bước (2FA)
                </h2>
                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                    Tăng bảo mật bằng mã từ ứng dụng Authenticator. Mặc định không bắt buộc — chỉ cần khi bạn bật.
                </p>
            </div>

            <div class="{{ $cardBodyClass }}">
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
                            <label for="disable_2fa_password" class="{{ $labelClass }}">
                                Mật khẩu hiện tại để tắt 2FA
                            </label>
                            <input id="disable_2fa_password" name="current_password" type="password" required autocomplete="current-password"
                                placeholder="••••••••"
                                class="{{ $inputClass }} @error('current_password') border-error @enderror">
                            @error('current_password')
                                <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit"
                            class="rounded-lg border border-error/40 bg-error-container px-5 py-2.5 font-label-md text-label-md font-semibold text-on-error-container hover:opacity-90">
                            Tắt xác thực hai bước
                        </button>
                    </form>
                @else
                    <a href="{{ route('settings.2fa.setup') }}"
                        class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Bật 2FA
                    </a>
                @endif
            </div>
        </section>
    @endunless

@elseif ($tab === 'notifications')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h2 class="font-title-md text-title-md text-on-surface">Tùy chọn thông báo</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Bật hoặc tắt từng loại thông báo theo nhu cầu học tập.
            </p>
        </div>

        <form method="post" action="{{ route('settings.notifications') }}" class="{{ $cardBodyClass }} space-y-5">
            @csrf
            @method('PUT')

            <div class="space-y-3">
                @foreach ([
                    'email_session' => ['title' => 'Email phiên học', 'hint' => 'Tóm tắt khi hoàn thành phiên Q-Bank.'],
                    'email_plan' => ['title' => 'Nhắc kế hoạch học', 'hint' => 'Nhiệm vụ Study Plan đến hạn.'],
                    'email_product' => ['title' => 'Cập nhật sản phẩm', 'hint' => 'Tính năng mới và mẹo học tập.'],
                    'push_reminders' => ['title' => 'Nhắc trong ứng dụng', 'hint' => 'Badge thông báo trên thanh điều hướng.'],
                ] as $key => $meta)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-outline-variant p-4 transition-colors hover:border-primary/30 hover:bg-surface-container-lowest">
                        <input type="checkbox" name="{{ $key }}" value="1"
                            class="mt-1 size-4 rounded border-outline text-primary focus:ring-primary"
                            @checked(old($key, $prefs[$key] ?? false))>
                        <span>
                            <span class="block font-label-md text-label-md font-semibold text-on-surface">{{ $meta['title'] }}</span>
                            <span class="mt-0.5 block font-body-sm text-body-sm text-on-surface-variant">{{ $meta['hint'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="flex justify-end border-t border-outline-variant pt-4">
                <button type="submit"
                    class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Lưu tùy chọn
                </button>
            </div>
        </form>
    </section>

@elseif ($tab === 'membership')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h2 class="font-title-md text-title-md text-on-surface">Gói hiện tại</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Quản lý gói đăng ký và quyền truy cập nội dung Premium.
            </p>
        </div>

        <div class="{{ $cardBodyClass }} space-y-5">
            <div class="flex flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest/50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-headline-sm text-headline-sm text-on-surface">{{ $membership['plan_name'] }}</p>
                    <p class="mt-1 font-body-md text-body-md text-on-surface-variant">{{ $membership['description'] }}</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-semibold text-primary">
                    Đang sử dụng
                </span>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('landing.pricing') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]">stars</span>
                    Xem bảng giá / nâng cấp
                </a>
                <a href="{{ route('profile.show', ['tab' => 'redeem']) }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-outline-variant px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                    Đổi mã kích hoạt
                </a>
            </div>
        </div>
    </section>

@elseif ($tab === 'org-license')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h2 class="font-title-md text-title-md text-on-surface">Giấy phép tổ chức</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Kích hoạt bằng email thuộc miền được cấp phép (trường, bệnh viện).
            </p>
        </div>

        <div class="divide-y divide-outline-variant/60">
            @forelse ($orgMembers as $member)
                @php
                    $institution = $member->institution;
                    $validUntil = $institution?->valid_until?->locale('vi')->isoFormat('D [tháng] M YYYY');
                @endphp
                <div class="flex flex-col gap-4 px-5 py-5 md:flex-row md:items-center md:gap-6 md:px-6">
                    <div class="flex min-w-0 flex-1 items-start gap-3 md:items-center">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined text-[22px]">account_balance</span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-body-md text-body-md font-medium text-on-surface" title="{{ $institution?->name }}">
                                    {{ $institution?->name }}
                                </p>
                                @if ($member->isVerified())
                                    <span class="inline-flex shrink-0 items-center rounded-full bg-primary/10 px-2.5 py-0.5 font-label-sm text-label-sm font-medium text-primary">
                                        Đã xác minh
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $member->email }}</p>
                            @if ($validUntil)
                                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                                    Có hiệu lực đến {{ $validUntil }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <form method="post" action="{{ route('settings.org-license.renew') }}" class="shrink-0 self-start md:self-center">
                        @csrf
                        <input type="hidden" name="member_id" value="{{ $member->id }}">
                        <button type="submit"
                            class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low">
                            Kiểm tra gia hạn
                        </button>
                    </form>
                </div>
            @empty
                <div class="px-5 py-8 text-center md:px-6">
                    <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                        <span class="material-symbols-outlined text-[28px]">account_balance</span>
                    </div>
                    <p class="font-body-md text-body-md text-on-surface-variant">Chưa có giấy phép tổ chức nào.</p>
                </div>
            @endforelse

            <div class="px-5 py-5 md:px-6">
                <p class="mb-4 font-body-md text-body-md text-on-surface">
                    Thêm giấy phép mới bằng email tổ chức của bạn.
                </p>
                <form method="post" action="{{ route('settings.org-license') }}" class="flex max-w-lg flex-col gap-3 sm:flex-row sm:items-start">
                    @csrf
                    <div class="flex-1">
                        <input type="email" name="institution_email" required
                            value="{{ old('institution_email', $user->email) }}"
                            placeholder="email@truong.edu.vn"
                            class="{{ $inputClass }} @error('institution_email') border-error @enderror">
                        @error('institution_email')
                            <p class="mt-1 font-body-sm text-body-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit"
                        class="shrink-0 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Kích hoạt
                    </button>
                </form>
            </div>
        </div>
    </section>

@elseif ($tab === 'invoices')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h2 class="font-title-md text-title-md text-on-surface">Lịch sử hóa đơn</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Theo dõi các giao dịch thanh toán trên tài khoản.
            </p>
        </div>

        <div class="{{ $cardBodyClass }}">
            @if ($invoices->isEmpty())
                <div class="flex flex-col items-center rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest/50 px-6 py-10 text-center">
                    <div class="mb-3 flex size-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                        <span class="material-symbols-outlined text-[28px]">receipt_long</span>
                    </div>
                    <p class="font-body-md text-body-md font-medium text-on-surface">Chưa có hóa đơn</p>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                        Hóa đơn sẽ xuất hiện sau khi bạn thanh toán hoặc đổi mã.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full min-w-[520px] text-left">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-lowest font-label-sm text-label-sm text-on-surface-variant">
                                <th class="px-4 py-3">Số hóa đơn</th>
                                <th class="px-4 py-3">Mô tả</th>
                                <th class="px-4 py-3">Ngày</th>
                                <th class="px-4 py-3">Số tiền</th>
                                <th class="px-4 py-3">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="font-body-md text-body-md text-on-surface">
                            @foreach ($invoices as $invoice)
                                <tr class="border-b border-outline-variant/60 last:border-0">
                                    <td class="px-4 py-3 font-mono text-body-sm">{{ $invoice->number }}</td>
                                    <td class="px-4 py-3">{{ $invoice->description }}</td>
                                    <td class="px-4 py-3">{{ $invoice->issued_at->locale('vi')->isoFormat('D/M/YYYY') }}</td>
                                    <td class="px-4 py-3">{{ number_format($invoice->amount_cents / 100, 0, ',', '.') }} {{ $invoice->currency }}</td>
                                    <td class="px-4 py-3">
                                        @if ($invoice->status === 'paid')
                                            <span class="inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 font-label-sm text-label-sm font-medium text-primary">
                                                Đã thanh toán
                                            </span>
                                        @else
                                            <span class="capitalize">{{ $invoice->status }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

@elseif ($tab === 'redeem')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h2 class="font-title-md text-title-md text-on-surface">Đổi mã kích hoạt</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Nhập mã từ trường, tổ chức hoặc chương trình khuyến mãi.
            </p>
        </div>

        <div class="{{ $cardBodyClass }}">
            <form method="post" action="{{ route('settings.redeem') }}" class="flex max-w-lg flex-col gap-3 sm:flex-row">
                @csrf
                <input type="text" name="code" required value="{{ old('code') }}" placeholder="Nhập mã kích hoạt..."
                    class="{{ $inputClass }} @error('code') border-error @enderror">
                <button type="submit"
                    class="shrink-0 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Đổi mã
                </button>
            </form>
            @error('code')
                <p class="mt-2 font-body-sm text-body-sm text-error">{{ $message }}</p>
            @enderror
        </div>
    </section>

@elseif ($tab === 'notes')
    <section class="{{ $cardClass }}">
        <div class="{{ $cardHeaderClass }}">
            <h2 class="font-title-md text-title-md text-on-surface">Ghi chú cá nhân</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Lưu nhắc nhở học tập — chỉ bạn mới thấy nội dung này.
            </p>
        </div>

        <form method="post" action="{{ route('settings.notes') }}" class="{{ $cardBodyClass }} space-y-5">
            @csrf
            @method('PUT')
            <textarea name="account_notes" rows="8" maxlength="5000"
                placeholder="Ghi chú học tập, mục tiêu, nhắc nhở..."
                class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ old('account_notes', $user->account_notes) }}</textarea>
            <div class="flex justify-end border-t border-outline-variant pt-4">
                <button type="submit"
                    class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Lưu ghi chú
                </button>
            </div>
        </form>
    </section>
@endif
