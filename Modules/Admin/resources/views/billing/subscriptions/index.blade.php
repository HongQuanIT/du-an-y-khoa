<x-layouts.admin title="Lịch sử Premium">
    <div x-data="adminSubscriptionFilter()" class="space-y-6">
    <x-admin.page-header title="Lịch sử Premium"
        description="Các lần kích hoạt gói trả phí của học viên — theo SKU và nguồn.">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.billing.subscriptions.index'))
<form method="get" action="{{ route('admin.billing.subscriptions.index') }}" role="search"
        aria-labelledby="subscription-filter-heading" aria-describedby="subscription-filter-description"
        @submit.prevent="applyFilters()"
        class="mb-6 space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
        <div>
            <h2 id="subscription-filter-heading" class="font-label-lg font-semibold text-on-surface">Tìm kiếm lịch sử Premium</h2>
            <p id="subscription-filter-description" class="mt-1 font-body-sm text-on-surface-variant">Tìm theo tên hoặc email học viên, sau đó lọc theo trạng thái, gói, SKU và nguồn kích hoạt.</p>
        </div>
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(280px,1.5fr)_repeat(4,minmax(145px,1fr))]">
            <div class="sm:col-span-2 xl:col-auto">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="q">Tìm kiếm học viên</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant" aria-hidden="true">search</span>
                    <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên hoặc email" autocomplete="off"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="status" label="Trạng thái" placeholder="Tất cả"
                    :options="[
                        ['id' => 'active', 'label' => 'Đang hiệu lực'],
                        ['id' => 'expired', 'label' => 'Đã hết hạn'],
                    ]"
                    :selected="$filters['status']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="plan" label="Gói" placeholder="Tất cả"
                    :options="$plans->map(fn ($plan) => ['id' => $plan->id, 'label' => $plan->name])->all()"
                    :selected="$filters['plan']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="sku" label="SKU" placeholder="Tất cả"
                    :options="collect([['id' => 'unassigned', 'label' => 'Chưa gắn SKU']])->merge($prices->map(fn ($price) => ['id' => $price->id, 'label' => $price->plan?->name.' — '.$price->label]))->all()"
                    :selected="$filters['sku']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="source" label="Nguồn" placeholder="Tất cả"
                    :options="collect($sourceLabels)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])->values()->all()"
                    :selected="$filters['source']" />
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                <button type="submit" :disabled="loading" aria-label="Tìm kiếm lịch sử Premium"
                    class="inline-flex h-11 w-36 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 font-label-md font-medium text-on-primary transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="loading ? 'progress_activity' : 'search'">search</span>
                    <span class="whitespace-nowrap" x-text="loading ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
                </button>
                <button type="button" @click="resetFilters(@js(route('admin.billing.subscriptions.index')))" :disabled="loading" aria-label="Xoá bộ lọc lịch sử Premium"
                    class="inline-flex h-11 w-28 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span><span>Xoá</span>
                </button>
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
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Hiệu lực</span>
                            @else
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Hết hạn</span>
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
