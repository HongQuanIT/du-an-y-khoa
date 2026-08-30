<x-layouts.partner title="Hoa hồng">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Người dùng</th>
                    <th class="px-4 py-3">Doanh thu</th>
                    <th class="px-4 py-3">%</th>
                    <th class="px-4 py-3">Hoa hồng</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Thời gian</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($commissions as $commission)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md">{{ $commission->referredUser?->name }}</div>
                            <div class="text-on-surface-variant">{{ $commission->referredUser?->email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ number_format($commission->gross_cents / 100) }} ₫</td>
                        <td class="px-4 py-3">{{ number_format($commission->rate_bps / 100, 1) }}%</td>
                        <td class="px-4 py-3 font-label-md">{{ number_format($commission->commission_cents / 100) }} ₫</td>
                        <td class="px-4 py-3">{{ $commission->status->label() }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $commission->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">Chưa có hoa hồng.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $commissions->links() }}</div>
</x-layouts.partner>
