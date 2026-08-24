@php
    use Modules\Billing\Support\MoneyFormatter;
@endphp

<x-layouts.admin title="Thanh toán">
    <x-admin.page-header title="Thanh toán"
        description="Giao dịch từ checkout (VNPay / Fake).">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    <form method="get" action="{{ route('admin.billing.payments.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-3">
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach (['succeeded', 'pending', 'failed', 'refunded'] as $st)
                    <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="provider">Cổng</label>
            <select id="provider" name="provider"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach (['fake', 'vnpay', 'momo', 'zalopay'] as $p)
                    <option value="{{ $p }}" @selected($filters['provider'] === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">Lọc</button>
            <a href="{{ route('admin.billing.payments.index') }}"
                class="rounded-lg px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Học viên</th>
                    <th class="px-4 py-3">Số tiền</th>
                    <th class="px-4 py-3">Cổng</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Provider ID</th>
                    <th class="px-4 py-3">Thời gian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/60">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3">{{ $payment->id }}</td>
                        <td class="px-4 py-3">
                            {{ $payment->checkoutSession?->user?->email ?? '—' }}
                        </td>
                        <td class="px-4 py-3">{{ MoneyFormatter::vnd((int) $payment->amount_cents) }}</td>
                        <td class="px-4 py-3">{{ $payment->provider }}</td>
                        <td class="px-4 py-3">{{ $payment->status }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $payment->provider_payment_id ?? '—' }}</td>
                        <td class="px-4 py-3">
                            {{ ($payment->paid_at ?? $payment->created_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-on-surface-variant">Chưa có giao dịch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.admin>
