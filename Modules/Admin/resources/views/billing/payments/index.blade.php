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
    <x-admin.page-header title="Thanh toán"
        description="Theo dõi mọi phiên checkout — chờ thanh toán, thành công, thất bại và hết hạn.">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    <form method="get" action="{{ route('admin.billing.payments.index') }}"
        role="search" aria-label="Lọc phiên thanh toán"
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2 xl:grid-cols-12">
        <div class="xl:col-span-4">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tất cả</option>
                @foreach ($statusLabels as $st => $label)
                    <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="xl:col-span-4">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="provider">Cổng thanh toán</label>
            <select id="provider" name="provider"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tất cả</option>
                @foreach (['fake', 'vnpay', 'momo', 'zalopay'] as $p)
                    <option value="{{ $p }}" @selected($filters['provider'] === $p)>{{ strtoupper($p) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 xl:col-span-4">
            <button type="submit" class="inline-flex h-11 flex-1 items-center justify-center rounded-lg bg-primary px-4 font-label-md font-medium text-on-primary hover:opacity-90">Lọc</button>
            <a href="{{ route('admin.billing.payments.index') }}"
                class="inline-flex h-11 flex-1 items-center justify-center rounded-lg border border-outline-variant px-4 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead>
                    <tr class="border-b border-outline-variant bg-surface-container-low/80">
                        <th class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Phiên</th>
                        <th class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Học viên</th>
                        <th class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Gói</th>
                        <th class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Số tiền</th>
                        <th class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Cổng</th>
                        <th class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Trạng thái</th>
                        <th class="px-4 py-3 font-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">Thời gian</th>
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
        <div class="mt-4">{{ $sessions->links() }}</div>
    @endif
</x-layouts.admin>
