@php
    use Modules\Billing\Support\MoneyFormatter;
    use Modules\Partner\Support\PartnerPeriodFilter;

    $sortUrl = function (string $sort) use ($queryParams, $filters): string {
        $dir = $filters['sort'] === $sort && $filters['dir'] === 'desc' ? 'asc' : 'desc';

        return route('admin.partners.index', array_merge($queryParams, [
            'sort' => $sort,
            'dir' => $dir,
        ]));
    };

    $sortMark = function (string $sort) use ($filters): string {
        if ($filters['sort'] !== $sort) {
            return '';
        }

        return $filters['dir'] === 'asc' ? ' ↑' : ' ↓';
    };
@endphp

<x-layouts.admin title="Cộng tác viên">
    <x-admin.page-header title="Cộng tác viên"
        description="Hiệu suất theo kỳ; mã còn hiệu lực là trạng thái hiện tại." />

    <x-admin.flash />

    <form method="get" action="{{ route('admin.partners.index') }}"
        class="mb-6 space-y-4 rounded-xl border border-outline-variant bg-surface p-4"
        x-data="{ preset: @js($period['preset']) }">
        <div class="flex flex-wrap gap-2">
            @foreach ($presets as $preset)
                @if ($preset !== PartnerPeriodFilter::PRESET_CUSTOM)
                    <a href="{{ route('admin.partners.index', array_filter([
                            'preset' => $preset,
                            'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
                            'q' => $filters['q'] !== '' ? $filters['q'] : null,
                            'sort' => $filters['sort'],
                            'dir' => $filters['dir'],
                        ])) }}"
                        class="rounded-lg px-3 py-2 font-label-md text-label-md {{ $period['preset'] === $preset ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container' }}">
                        {{ PartnerPeriodFilter::presetLabel($preset) }}
                    </a>
                @endif
            @endforeach
            <button type="button" @click="preset = 'custom'"
                class="rounded-lg px-3 py-2 font-label-md text-label-md"
                :class="preset === 'custom' ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'">
                Tuỳ chọn
            </button>
        </div>

        <input type="hidden" name="preset" :value="preset">

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" x-show="preset === 'custom'" x-cloak>
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="from">Từ ngày</label>
                <input id="from" name="from" type="date"
                    value="{{ $period['preset'] === 'custom' ? $period['from']->toDateString() : '' }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="to">Đến ngày</label>
                <input id="to" name="to" type="date"
                    value="{{ $period['preset'] === 'custom' ? $period['to']->toDateString() : now()->toDateString() }}"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="status">Trạng thái</label>
                <select id="status" name="status"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
                    <option value="all" @selected($filters['status'] === 'all')>Tất cả</option>
                    <option value="active" @selected($filters['status'] === 'active')>Hoạt động</option>
                    <option value="suspended" @selected($filters['status'] === 'suspended')>Tạm dừng</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="q">Tìm CTV</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}"
                    placeholder="Tên hiển thị, tên hoặc email"
                    class="w-full rounded-lg bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
            <div class="flex items-end gap-2">
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
                <button type="submit"
                    class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">Áp dụng</button>
                <a href="{{ route('admin.partners.index') }}"
                    class="rounded-lg px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
            </div>
        </div>

        <p class="font-label-md text-on-surface">
            Đang xem: <span class="font-semibold">{{ $period['label'] }}</span>
            <span class="font-label-sm text-on-surface-variant">
                — Đăng ký / doanh số / hoa hồng theo kỳ · Mã còn hiệu lực = hiện tại
            </span>
        </p>
    </form>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-on-surface-variant">CTV (lọc)</p>
            <p class="mt-1 font-headline-sm text-headline-sm">{{ number_format($totals['partners']) }}</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-on-surface-variant">Đăng ký trong kỳ</p>
            <p class="mt-1 font-headline-sm text-headline-sm">{{ number_format($totals['referrals']) }}</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-on-surface-variant">Doanh số kỳ</p>
            <p class="mt-1 font-headline-sm text-headline-sm">{{ MoneyFormatter::vnd((int) $totals['gross_cents']) }}</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-on-surface-variant">Hoa hồng kỳ</p>
            <p class="mt-1 font-headline-sm text-headline-sm">{{ MoneyFormatter::vnd((int) $totals['commission_cents']) }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_NAME) }}" class="hover:text-primary">
                            CTV{{ $sortMark(PartnerPeriodFilter::SORT_NAME) }}
                        </a>
                    </th>
                    <th class="px-4 py-3">Tài khoản</th>
                    <th class="px-4 py-3">
                        Mã còn hiệu lực
                        <span class="block font-label-sm font-normal normal-case text-on-surface-variant/80">hiện tại</span>
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_REFERRALS) }}" class="hover:text-primary">
                            Đăng ký kỳ{{ $sortMark(PartnerPeriodFilter::SORT_REFERRALS) }}
                        </a>
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_GROSS) }}" class="hover:text-primary">
                            Doanh số{{ $sortMark(PartnerPeriodFilter::SORT_GROSS) }}
                        </a>
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_COMMISSION) }}" class="hover:text-primary">
                            Hoa hồng{{ $sortMark(PartnerPeriodFilter::SORT_COMMISSION) }}
                        </a>
                    </th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partners as $partner)
                    @php
                        $activeCodes = $partner->status->value === 'active'
                            ? (int) $partner->active_codes_count
                            : 0;
                        $gross = (int) ($partner->period_gross_cents ?? 0);
                        $commission = (int) ($partner->period_commission_cents ?? 0);
                        $referrals = (int) ($partner->period_referrals_count ?? 0);
                    @endphp
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3 font-label-md">{{ $partner->display_name }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $partner->user?->name }}<br>{{ $partner->user?->email }}
                        </td>
                        <td class="px-4 py-3">{{ number_format($activeCodes) }}</td>
                        <td class="px-4 py-3">{{ number_format($referrals) }}</td>
                        <td class="px-4 py-3">{{ MoneyFormatter::vnd($gross) }}</td>
                        <td class="px-4 py-3 font-label-md">{{ MoneyFormatter::vnd($commission) }}</td>
                        <td class="px-4 py-3">{{ $partner->status->label() }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.partners.show', $partner) }}" class="text-primary hover:underline">Chi tiết</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-on-surface-variant">Không có CTV khớp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($partners->total() > 0)
                <tfoot class="border-t border-outline-variant bg-surface-container-low font-label-md">
                    <tr>
                        <td class="px-4 py-3" colspan="3">Tổng ({{ number_format($totals['partners']) }} CTV khớp lọc)</td>
                        <td class="px-4 py-3">{{ number_format($totals['referrals']) }}</td>
                        <td class="px-4 py-3">{{ MoneyFormatter::vnd((int) $totals['gross_cents']) }}</td>
                        <td class="px-4 py-3">{{ MoneyFormatter::vnd((int) $totals['commission_cents']) }}</td>
                        <td class="px-4 py-3" colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="mt-4">{{ $partners->links() }}</div>
</x-layouts.admin>
