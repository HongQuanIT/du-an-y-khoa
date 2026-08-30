<x-layouts.admin title="CTV — {{ $partner->display_name }}">
    <x-admin.page-header title="{{ $partner->display_name }}"
        description="{{ $partner->user?->email }}" />

    <x-admin.flash />

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-error/30 bg-error/10 px-4 py-3 font-body-sm text-body-sm text-error">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.partners.update', $partner) }}" class="mb-8 grid max-w-xl grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2">
        @csrf
        @method('PUT')
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm" for="display_name">Tên hiển thị</label>
            <input id="display_name" name="display_name" value="{{ old('display_name', $partner->display_name) }}"
                class="w-full rounded-lg bg-surface-container-low px-3 py-2">
        </div>
        <div>
            <label class="mb-1 block font-label-sm" for="default_commission_rate_percent">% hoa hồng mặc định</label>
            <input id="default_commission_rate_percent" name="default_commission_rate_percent" type="number" step="0.01"
                value="{{ old('default_commission_rate_percent', $partner->commissionRatePercent()) }}"
                class="w-full rounded-lg bg-surface-container-low px-3 py-2">
        </div>
        <div>
            <label class="mb-1 block font-label-sm" for="status">Trạng thái</label>
            <select id="status" name="status" class="w-full rounded-lg bg-surface-container-low px-3 py-2">
                <option value="active" @selected($partner->status->value === 'active')>Hoạt động</option>
                <option value="suspended" @selected($partner->status->value === 'suspended')>Tạm dừng</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary">Lưu CTV</button>
        </div>
    </form>

    <section class="mb-8 rounded-xl border border-outline-variant bg-surface p-4">
        <h2 class="mb-1 font-title-md text-title-md">Mã mời</h2>
        <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant">
            Chỉ admin tạo và cấu hình mã cho CTV này. CTV chỉ xem và copy link.
            Nếu để trống ngày hết hạn / lượt dùng, hệ thống áp dụng mặc định từ
            <a href="{{ route('admin.settings.index') }}" class="text-primary hover:underline">Cài đặt → Cộng tác viên</a>
            (hiện tại: hết hạn
            {{ \Modules\Partner\Support\PartnerSettings::defaultInviteExpiresDays() > 0
                ? \Modules\Partner\Support\PartnerSettings::defaultInviteExpiresDays().' ngày'
                : 'không tự hết hạn' }},
            lượt dùng
            {{ \Modules\Partner\Support\PartnerSettings::defaultInviteMaxUses() ?? 'không giới hạn' }}).
        </p>

        <form method="post" action="{{ route('admin.partners.codes.store', $partner) }}"
            class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <div>
                <label class="mb-1 block font-label-sm" for="code">Mã</label>
                <input id="code" name="code" required maxlength="32" value="{{ old('code') }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2 uppercase" placeholder="CTV2026">
            </div>
            <div>
                <label class="mb-1 block font-label-sm" for="label">Nhãn</label>
                <input id="label" name="label" value="{{ old('label') }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block font-label-sm" for="commission_rate_percent">% hoa hồng (tuỳ chọn)</label>
                <input id="commission_rate_percent" name="commission_rate_percent" type="number" step="0.01" min="0" max="100"
                    value="{{ old('commission_rate_percent') }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2"
                    placeholder="Mặc định {{ $partner->commissionRatePercent() }}">
            </div>
            <div>
                <label class="mb-1 block font-label-sm" for="starts_at">Ngày hiệu lực</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at') }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block font-label-sm" for="expires_at">Ngày hết hạn</label>
                <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2">
                <p class="mt-1 font-label-sm text-on-surface-variant">Trống = áp dụng mặc định hệ thống (hoặc không hết hạn nếu mặc định = 0).</p>
            </div>
            <div>
                <label class="mb-1 block font-label-sm" for="max_uses">Lượt dùng tối đa</label>
                <input id="max_uses" name="max_uses" type="number" min="1" value="{{ old('max_uses') }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2" placeholder="Theo mặc định hệ thống">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary">Tạo mã</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-outline-variant">
            <table class="min-w-full text-left font-body-sm">
                <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                    <tr>
                        <th class="px-4 py-3">Mã / Link</th>
                        <th class="px-4 py-3">Hiệu lực</th>
                        <th class="px-4 py-3">Lượt</th>
                        <th class="px-4 py-3">%</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partner->inviteCodes as $code)
                        <tr class="border-b border-outline-variant/60 align-top">
                            <td class="px-4 py-3">
                                <div class="font-label-md font-bold tracking-wide">{{ $code->code }}</div>
                                @if ($code->label)
                                    <div class="text-on-surface-variant">{{ $code->label }}</div>
                                @endif
                                <div class="mt-1 break-all font-label-sm text-primary">{{ $code->registerUrl() }}</div>
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant">
                                @if ($code->starts_at)
                                    Từ {{ $code->starts_at->format('d/m/Y H:i') }}<br>
                                @endif
                                @if ($code->expires_at)
                                    Đến {{ $code->expires_at->format('d/m/Y H:i') }}
                                @else
                                    Không hết hạn
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $code->use_count }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}</td>
                            <td class="px-4 py-3">{{ number_format($code->effectiveRateBps() / 100, 1) }}%</td>
                            <td class="px-4 py-3">
                                {{ $code->is_active ? 'Bật' : 'Tắt' }}
                                @if (! $code->isCurrentlyValid())
                                    <span class="text-warning">(không hiệu lực)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 space-y-2">
                                <form method="post" action="{{ route('admin.partners.codes.toggle', [$partner, $code]) }}">
                                    @csrf
                                    <button type="submit" class="text-primary hover:underline">
                                        {{ $code->is_active ? 'Tắt' : 'Bật' }}
                                    </button>
                                </form>
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-on-surface-variant hover:underline">Sửa</summary>
                                    <form method="post" action="{{ route('admin.partners.codes.update', [$partner, $code]) }}"
                                        class="mt-2 grid max-w-md gap-2 rounded-lg border border-outline-variant p-3">
                                        @csrf
                                        @method('PUT')
                                        <input name="label" value="{{ $code->label }}" placeholder="Nhãn"
                                            class="rounded-lg bg-surface-container-low px-3 py-2">
                                        <input name="starts_at" type="datetime-local"
                                            value="{{ $code->starts_at?->format('Y-m-d\\TH:i') }}"
                                            class="rounded-lg bg-surface-container-low px-3 py-2">
                                        <input name="expires_at" type="datetime-local"
                                            value="{{ $code->expires_at?->format('Y-m-d\\TH:i') }}"
                                            class="rounded-lg bg-surface-container-low px-3 py-2">
                                        <input name="max_uses" type="number" min="1" value="{{ $code->max_uses }}"
                                            placeholder="Lượt dùng" class="rounded-lg bg-surface-container-low px-3 py-2">
                                        <input name="commission_rate_percent" type="number" step="0.01" min="0" max="100"
                                            value="{{ $code->commission_rate_bps !== null ? $code->commission_rate_bps / 100 : '' }}"
                                            placeholder="% hoa hồng" class="rounded-lg bg-surface-container-low px-3 py-2">
                                        <button type="submit" class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary">Lưu mã</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-on-surface-variant">Chưa có mã mời.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <h2 class="mb-3 font-title-md text-title-md">Người được mời</h2>
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Mã</th>
                    <th class="px-4 py-3">Gói</th>
                    <th class="px-4 py-3">Đăng ký</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($referrals as $row)
                    <tr class="border-b border-outline-variant/60">
                        <td class="px-4 py-3">
                            {{ $row['attribution']->referredUser?->name }}<br>
                            <span class="text-on-surface-variant">{{ $row['attribution']->referredUser?->email }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $row['attribution']->inviteCode?->code }}</td>
                        <td class="px-4 py-3">
                            {{ $row['plan_name'] }}
                            @if ($row['ends_at'])
                                <div class="text-on-surface-variant">{{ $row['ends_at']->format('d/m/Y') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $row['attribution']->attributed_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-on-surface-variant">Chưa có referral.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $referrals->links() }}</div>
</x-layouts.admin>
