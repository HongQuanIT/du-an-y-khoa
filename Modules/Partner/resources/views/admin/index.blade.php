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
    <div x-data="adminPartnerFilter(@js($period['preset']))" class="space-y-6">
    <x-admin.page-header title="Cộng tác viên"
        description="Hiệu suất theo kỳ; mã còn hiệu lực là trạng thái hiện tại." />

    <x-admin.flash />

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.partners.index'))
<form method="get" action="{{ route('admin.partners.index') }}" role="search"
        aria-labelledby="partner-filter-heading" aria-describedby="partner-filter-description"
        @submit.prevent="applyFilters()"
        class="space-y-5 rounded-xl border border-outline-variant bg-surface p-4">
        <div>
            <h2 id="partner-filter-heading" class="font-label-lg font-semibold text-on-surface">Tìm kiếm cộng tác viên</h2>
            <p id="partner-filter-description" class="mt-1 font-body-sm text-on-surface-variant">Chọn kỳ báo cáo, trạng thái hoặc tìm theo tên và email cộng tác viên.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($presets as $preset)
                @if ($preset !== PartnerPeriodFilter::PRESET_CUSTOM)
                    <button type="button" @click="preset = @js($preset)"
                        class="rounded-lg px-3 py-2 font-label-md text-label-md transition-colors focus-visible:ring-2 focus-visible:ring-primary/40"
                        :class="preset === @js($preset) ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'">
                        {{ PartnerPeriodFilter::presetLabel($preset) }}
                    </button>
                @endif
            @endforeach
            <button type="button" @click="preset = 'custom'"
                class="rounded-lg px-3 py-2 font-label-md text-label-md"
                :class="preset === 'custom' ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'">
                Tuỳ chọn
            </button>
        </div>

        <input type="hidden" name="preset" :value="preset">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-show="preset === 'custom'" x-cloak>
            <div>
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="from">Từ ngày</label>
                <input id="from" name="from" type="date"
                    value="{{ $period['preset'] === 'custom' ? $period['from']->toDateString() : '' }}"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
            </div>
            <div>
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="to">Đến ngày</label>
                <input id="to" name="to" type="date"
                    value="{{ $period['preset'] === 'custom' ? $period['to']->toDateString() : now()->toDateString() }}"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
            </div>
        </div>

        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(220px,300px)_minmax(280px,1fr)_auto]">
            <div class="min-w-0">
                <x-admin.multi-select-filter name="status" label="Trạng thái" placeholder="Tất cả"
                    :options="[
                        ['id' => 'active', 'label' => 'Hoạt động'],
                        ['id' => 'suspended', 'label' => 'Tạm dừng'],
                    ]"
                    :selected="$filters['status']" />
            </div>
            <div class="min-w-0">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="q">Tìm cộng tác viên</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}"
                    placeholder="Tên hiển thị, tên hoặc email"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none placeholder:text-on-surface-variant focus-visible:ring-2 focus-visible:ring-primary/40">
            </div>
            <div class="flex self-end gap-2 sm:col-span-2 xl:col-auto">
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
                <button type="submit" :disabled="loading" aria-label="Tìm kiếm cộng tác viên"
                    class="inline-flex h-11 w-36 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 font-label-md font-medium text-on-primary transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="loading ? 'progress_activity' : 'search'">search</span>
                    <span class="whitespace-nowrap" x-text="loading ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
                </button>
                <button type="button" @click="resetFilters(@js(route('admin.partners.index')))" :disabled="loading" aria-label="Xoá bộ lọc cộng tác viên"
                    class="inline-flex h-11 w-28 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span><span>Xoá</span>
                </button>
            </div>
        </div>

        <p id="partner-period-description" class="font-label-md text-on-surface">
            Đang xem: <span class="font-semibold">{{ $period['label'] }}</span>
            <span class="font-label-sm text-on-surface-variant">
                — Đăng ký / doanh số / hoa hồng theo kỳ · Mã còn hiệu lực = hiện tại
            </span>
        </p>
    </form>
@endif

    <div id="partner-results-region">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
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

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <caption class="sr-only">Danh sách hiệu suất cộng tác viên theo kỳ được chọn</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th scope="col" class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_NAME) }}" class="hover:text-primary">
                            CTV{{ $sortMark(PartnerPeriodFilter::SORT_NAME) }}
                        </a>
                    </th>
                    <th scope="col" class="px-4 py-3">Tài khoản</th>
                    <th scope="col" class="px-4 py-3">
                        Mã còn hiệu lực
                        <span class="block font-label-sm font-normal normal-case text-on-surface-variant/80">hiện tại</span>
                    </th>
                    <th scope="col" class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_REFERRALS) }}" class="hover:text-primary">
                            Đăng ký kỳ{{ $sortMark(PartnerPeriodFilter::SORT_REFERRALS) }}
                        </a>
                    </th>
                    <th scope="col" class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_GROSS) }}" class="hover:text-primary">
                            Doanh số{{ $sortMark(PartnerPeriodFilter::SORT_GROSS) }}
                        </a>
                    </th>
                    <th scope="col" class="px-4 py-3">
                        <a href="{{ $sortUrl(PartnerPeriodFilter::SORT_COMMISSION) }}" class="hover:text-primary">
                            Hoa hồng{{ $sortMark(PartnerPeriodFilter::SORT_COMMISSION) }}
                        </a>
                    </th>
                    <th scope="col" class="px-4 py-3">Trạng thái</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Thao tác</span></th>
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
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.partners.show'))
<a href="{{ route('admin.partners.show', $partner) }}" class="text-primary hover:underline">Chi tiết</a>
@endif
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
    </div>

    @if ($partners->hasPages())
        <div class="mt-4" id="partner-pagination">{{ $partners->links() }}</div>
    @endif
    </div>

    <script>
        function adminPartnerFilter(initialPreset) {
            return {
                loading: false,
                preset: initialPreset,
                filterForm() { return document.querySelector('form[role="search"]'); },
                async applyFilters() {
                    const form = this.filterForm();
                    if (!form) return;
                    const url = new URL(form.action, window.location.origin);
                    const params = new URLSearchParams(new FormData(form));
                    params.delete('page');
                    url.search = params.toString();
                    await this.fetchResults(url.toString());
                },
                async resetFilters(url) {
                    const form = this.filterForm();
                    form?.reset();
                    this.preset = 'this_month';
                    window.dispatchEvent(new CustomEvent('partner-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải danh sách cộng tác viên');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('partner-results-region');
                        const current = document.getElementById('partner-results-region');
                        const nextPeriod = parsed.getElementById('partner-period-description');
                        const currentPeriod = document.getElementById('partner-period-description');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả cộng tác viên');
                        current.replaceWith(next);
                        if (nextPeriod && currentPeriod) currentPeriod.replaceWith(nextPeriod);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách cộng tác viên. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#partner-pagination a').forEach((link) => {
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            if (link.href) this.fetchResults(link.href);
                        });
                    });
                },
                init() { this.bindPagination(); },
            };
        }
    </script>
    </div>
</x-layouts.admin>
