@php
    use Modules\Billing\Support\MoneyFormatter;
@endphp

<x-layouts.partner title="Chi trả">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Kỳ</th>
                    <th class="px-4 py-3">Số tiền</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Chi lúc</th>
                    <th class="px-4 py-3">Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payouts as $payout)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            {{ $payout->period_from->format('d/m/Y') }} — {{ $payout->period_to->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 font-label-md">{{ MoneyFormatter::vnd((int) $payout->amount_cents) }}</td>
                        <td class="px-4 py-3">{{ $payout->status->label() }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $payout->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $payout->note ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Chưa có kỳ chi trả.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payouts->links() }}</div>
</x-layouts.partner>
