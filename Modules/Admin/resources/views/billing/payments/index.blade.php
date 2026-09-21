@php
    use Illuminate\Support\Str;
    use Modules\Billing\Support\MoneyFormatter;

    $statusMeta = [
        'pending' => [
            'label' => 'Chờ thanh toán',
            'dot' => 'bg-on-surface-variant',
            'class' => 'border-outline-variant text-on-surface',
        ],
        'completed' => [
            'label' => 'Thành công',
            'dot' => 'bg-on-surface-variant',
            'class' => 'border-outline-variant text-on-surface',
        ],
        'failed' => [
            'label' => 'Thất bại',
            'dot' => 'bg-on-surface-variant',
            'class' => 'border-outline-variant text-on-surface',
        ],
        'expired' => [
            'label' => 'Hết hạn',
            'dot' => 'bg-on-surface-variant',
            'class' => 'border-outline-variant text-on-surface-variant',
        ],
    ];
@endphp

<x-layouts.admin title="Thanh toán">
    <div x-data="adminPaymentFilter()" class="space-y-6">
    <x-admin.page-header title="Thanh toán"
        description="Theo dõi mọi phiên checkout — chờ thanh toán, thành công, thất bại và hết hạn.">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.billing.payments.index'))
<form method="get" action="{{ route('admin.billing.payments.index') }}"
        role="search" aria-labelledby="payment-filter-heading" aria-describedby="payment-filter-description"
        @submit.prevent="applyFilters()"
        class="mb-6 space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
        <div>
            <h2 id="payment-filter-heading" class="font-label-lg font-semibold text-on-surface">Tìm kiếm thanh toán</h2>
            <p id="payment-filter-description" class="mt-1 font-body-sm text-on-surface-variant">Thu hẹp phiên thanh toán theo trạng thái và cổng thanh toán.</p>
        </div>
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(220px,320px)_minmax(220px,320px)_auto]">
            <div class="min-w-0">
                <x-admin.multi-select-filter name="status" label="Trạng thái" placeholder="Tất cả"
                :options="collect($statusLabels)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])->values()->all()"
                :selected="$filters['status']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="provider" label="Cổng thanh toán" placeholder="Tất cả"
                :options="collect(['fake', 'vnpay', 'momo', 'zalopay'])->map(fn ($value) => ['id' => $value, 'label' => strtoupper($value)])->all()"
                :selected="$filters['provider']" />
            </div>
            <div class="flex self-end gap-2 sm:col-span-2 xl:col-auto">
                <button type="submit" :disabled="loading" aria-label="Tìm kiếm thanh toán"
                class="inline-flex h-11 w-36 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 font-label-md font-medium text-on-primary transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="loading ? 'progress_activity' : 'search'">search</span>
                    <span class="whitespace-nowrap" x-text="loading ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
                </button>
                <button type="button" @click="resetFilters(@js(route('admin.billing.payments.index')))" :disabled="loading" aria-label="Xoá bộ lọc thanh toán"
                class="inline-flex h-11 w-28 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span><span>Xoá</span>
                </button>
            </div>
        </div>
    </form>
@endif

    <div id="payments-results-region">
    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <caption class="sr-only">Danh sách phiên thanh toán của học viên</caption>
                <thead>
                    <tr class="border-b border-outline-variant bg-surface-container-low/80">
                        <th scope="col" class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Phiên</th>
                        <th scope="col" class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Học viên</th>
                        <th scope="col" class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Gói</th>
                        <th scope="col" class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Số tiền</th>
                        <th scope="col" class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Cổng</th>
                        <th scope="col" class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Trạng thái</th>
                        <th scope="col" class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Thời gian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/70">
                    @forelse ($sessions as $session)
                        @php
                            $payment = $session->payments->first();
                            $meta = $statusMeta[$session->status] ?? [
                                'label' => $session->status,
                                'dot' => 'bg-on-surface-variant',
                                'class' => 'border-outline-variant text-on-surface-variant',
                            ];
                        @endphp
                        <tr class="transition-colors hover:bg-surface-container-low">
                            <td class="px-4 py-3.5 align-middle">
                                <p class="font-label-md font-semibold text-on-surface">#{{ $session->id }}</p>
                                <p class="mt-0.5 font-mono text-[11px] text-on-surface-variant" title="{{ $session->uuid }}">
                                    {{ Str::limit($session->uuid, 8, '…') }}
                                </p>
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                <p class="font-label-md font-medium text-on-surface">{{ $session->user?->name ?? '—' }}</p>
                                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $session->user?->email ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                <p class="font-body-sm text-on-surface">{{ $session->planPrice?->plan?->name ?? 'Premium' }}</p>
                                @if ($session->planPrice?->label)
                                    <p class="mt-0.5 font-body-sm text-on-surface-variant">{{ $session->planPrice->label }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 align-middle whitespace-nowrap font-label-md font-semibold text-on-surface">
                                {{ MoneyFormatter::vnd($session->totalCents()) }}
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                <span class="inline-flex items-center rounded-md bg-surface-container-low px-2 py-1 font-label-sm font-medium uppercase tracking-wide text-on-surface-variant">
                                    {{ $session->gateway }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                <span class="inline-flex max-w-full items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 font-label-sm font-medium {{ $meta['class'] }}">
                                    <span class="size-1.5 shrink-0 rounded-full {{ $meta['dot'] }}"></span>
                                    {{ $meta['label'] }}
                                </span>
                                @if ($payment?->provider_payment_id)
                                    <p class="mt-1.5 max-w-[11rem] truncate font-mono text-[11px] text-on-surface-variant" title="{{ $payment->provider_payment_id }}">
                                        {{ $payment->provider_payment_id }}
                                    </p>
                                @elseif ($session->gateway_order_id)
                                    <p class="mt-1.5 max-w-[11rem] truncate font-mono text-[11px] text-on-surface-variant" title="{{ $session->gateway_order_id }}">
                                        {{ $session->gateway_order_id }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 align-middle whitespace-nowrap">
                                <p class="font-body-sm text-on-surface">
                                    {{ $session->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                </p>
                                @if ($session->completed_at)
                                    <p class="mt-0.5 font-label-sm text-on-surface-variant">
                                        Hoàn tất {{ $session->completed_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    </p>
                                @elseif ($session->status === 'expired' && $session->expires_at)
                                    <p class="mt-0.5 font-label-sm text-on-surface-variant">
                                        Hết hạn {{ $session->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    </p>
                                @elseif ($session->status === 'pending' && $session->expires_at)
                                    <p class="mt-0.5 font-label-sm text-on-surface-variant">
                                        Hết hạn lúc {{ $session->expires_at->timezone(config('app.timezone'))->format('H:i d/m') }}
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <p class="font-label-md font-medium text-on-surface">Chưa có phiên thanh toán</p>
                                <p class="mt-1 font-body-sm text-on-surface-variant">Các lần checkout từ học viên sẽ xuất hiện tại đây.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($sessions->hasPages())
        <div class="mt-4" id="payments-pagination">{{ $sessions->links() }}</div>
    @endif
    </div>

    <script>
        function adminPaymentFilter() {
            return {
                loading: false,
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
                    window.dispatchEvent(new CustomEvent('payment-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải danh sách thanh toán');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('payments-results-region');
                        const current = document.getElementById('payments-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả thanh toán');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách thanh toán. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#payments-pagination a').forEach((link) => {
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
