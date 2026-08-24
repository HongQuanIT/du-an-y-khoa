<x-layouts.admin title="Lịch sử Premium">
    <x-admin.page-header title="Lịch sử Premium"
        description="Các lần kích hoạt gói trả phí của học viên — theo SKU và nguồn.">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    <form method="get" action="{{ route('admin.billing.subscriptions.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="q">Tìm kiếm học viên</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên hoặc email"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <option value="active" @selected($filters['status'] === 'active')>Đang hiệu lực</option>
                <option value="expired" @selected($filters['status'] === 'expired')>Đã hết hạn</option>
                <option value="all" @selected($filters['status'] === 'all')>Tất cả</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="plan">Gói</label>
            <select id="plan" name="plan"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((string) $filters['plan'] === (string) $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="sku">SKU</label>
            <select id="sku" name="sku"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                <option value="unassigned" @selected($filters['sku'] === 'unassigned')>Chưa gắn SKU</option>
                @foreach ($prices as $price)
                    <option value="{{ $price->id }}" @selected((string) $filters['sku'] === (string) $price->id)>
                        {{ $price->plan?->name }} — {{ $price->label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="source">Nguồn</label>
            <select id="source" name="source"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($sourceLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['source'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
            <button type="submit"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">Lọc</button>
            <a href="{{ route('admin.billing.subscriptions.index') }}"
                class="rounded-lg px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Học viên</th>
                    <th class="px-4 py-3">Gói</th>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3">Nguồn</th>
                    <th class="px-4 py-3">Bắt đầu</th>
                    <th class="px-4 py-3">Kết thúc</th>
                    <th class="px-4 py-3">Trạng thái</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/60">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="px-4 py-3">
                            @if ($canViewUsers && $subscription->user)
                                <a href="{{ route('admin.users.show', $subscription->user) }}"
                                    class="font-label-md text-primary hover:underline">{{ $subscription->user->name }}</a>
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
        <div class="mt-4">
            {{ $subscriptions->links() }}
        </div>
    @endif
</x-layouts.admin>
