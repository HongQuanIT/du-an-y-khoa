<x-layouts.admin title="Lịch sử Premium">
    <div x-data="adminSubscriptionFilter()" class="space-y-6">
    <x-admin.page-header title="Lịch sử Premium"
        description="Các lần kích hoạt gói trả phí của học viên — theo SKU và nguồn.">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.billing.subscriptions.index'))
<form id="subscription-filter-form" method="get" action="{{ route('admin.billing.subscriptions.index') }}"
        role="search" aria-label="Tìm kiếm lịch sử Premium"
        @submit.prevent="applyFilters()"
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <div class="md:col-span-3">
            <label for="q" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</label>
            <div class="relative">
                <input id="q" name="q" value="{{ $filters['q'] }}" type="search"
                    placeholder="Tên hoặc email" autocomplete="off"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                <span class="material-symbols-outlined pointer-events-none absolute top-2.5 left-2.5 text-[20px] text-on-surface-variant/70" aria-hidden="true">search</span>
            </div>
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="status"
                label="Trạng thái"
                placeholder="Tất cả"
                :options="[
                    ['id' => 'active', 'label' => 'Đang hiệu lực', 'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                    ['id' => 'expired', 'label' => 'Đã hết hạn', 'tone' => 'bg-surface-container-high text-on-surface-variant border-outline-variant'],
                ]"
                :selected="$filters['status']"
            />
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="plan"
                label="Gói"
                placeholder="Tất cả"
                :options="$plans->map(fn ($plan) => ['id' => $plan->id, 'label' => $plan->name])->all()"
                :selected="$filters['plan']"
            />
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="sku"
                label="SKU"
                placeholder="Tất cả"
                :options="collect([['id' => 'unassigned', 'label' => 'Chưa gắn SKU']])->merge($prices->map(fn ($price) => ['id' => $price->id, 'label' => $price->plan?->name.' — '.$price->label]))->all()"
                :selected="$filters['sku']"
            />
        </div>

        <div class="min-w-0 md:col-span-3">
            <x-admin.multi-select-filter
                name="source"
                label="Nguồn"
                placeholder="Tất cả"
                :options="collect($sourceLabels)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])->values()->all()"
                :selected="$filters['source']"
            />
        </div>

        <div class="md:col-span-12 flex justify-end border-t border-outline-variant pt-4">
            <div class="w-full max-w-xs">
                <x-admin.filter-action-buttons
                    fill
                    :reset-url="route('admin.billing.subscriptions.index')"
                    search-aria-label="Tìm kiếm lịch sử Premium"
                    reset-aria-label="Xoá bộ lọc lịch sử Premium"
                />
            </div>
        </div>
    </form>
@endif

    <div id="subscriptions-results-region">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm">
            <caption class="sr-only">Danh sách lịch sử kích hoạt Premium của học viên</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th scope="col" class="px-4 py-3">Học viên</th>
                    <th scope="col" class="px-4 py-3">Gói</th>
                    <th scope="col" class="px-4 py-3">SKU</th>
                    <th scope="col" class="px-4 py-3">Nguồn</th>
                    <th scope="col" class="px-4 py-3">Bắt đầu</th>
                    <th scope="col" class="px-4 py-3">Kết thúc</th>
                    <th scope="col" class="px-4 py-3">Trạng thái</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/60">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="px-4 py-3">
                            @if ($canViewUsers && $subscription->user)
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.users.show'))
<a href="{{ route('admin.users.show', $subscription->user) }}"
                                    class="font-label-md text-primary hover:underline">{{ $subscription->user->name }}</a>
@endif
                                <p class="font-body-sm text-on-surface-variant">{{ $subscription->user->email }}</p>
                            @else
                                <p class="font-label-md text-on-surface">{{ $subscription->user?->name ?? '—' }}</p>
                                <p class="font-body-sm text-on-surface-variant">{{ $subscription->user?->email }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface">{{ $subscription->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($subscription->planPrice)
                                <p class="text-on-surface">{{ $subscription->planPrice->label }}</p>
                                <p class="font-mono text-xs text-on-surface-variant">{{ $subscription->planPrice->slug }}</p>
                            @else
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Chưa gắn SKU</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $sourceLabels[$subscription->source] ?? $subscription->source }}
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $subscription->starts_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $subscription->ends_at?->format('d/m/Y') ?? 'Không giới hạn' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($subscription->isActive())
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-800 border-emerald-200">Hiệu lực</span>
                            @else
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium bg-surface-container-high text-on-surface-variant border-outline-variant">Hết hạn</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">Không có bản ghi Premium khớp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($subscriptions->hasPages())
        <div class="mt-4" id="subscriptions-pagination">
            {{ $subscriptions->links() }}
        </div>
    @endif
    </div>

    <script>
        function adminSubscriptionFilter() {
            return {
                loading: false,
                filterForm() { return document.getElementById('subscription-filter-form'); },
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
                    const query = form?.querySelector('[name="q"]');
                    if (query) query.value = '';
                    window.dispatchEvent(new CustomEvent('subscription-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải lịch sử Premium');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('subscriptions-results-region');
                        const current = document.getElementById('subscriptions-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả lịch sử Premium');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải lịch sử Premium. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#subscriptions-pagination a').forEach((link) => {
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
