@php
    use Modules\Billing\Support\MoneyFormatter;
@endphp

<x-layouts.admin title="Chi trả CTV">
    <x-admin.page-header title="Chi trả cộng tác viên"
        description="Tạo kỳ đối soát từ hoa hồng chờ duyệt và đánh dấu đã chi." />

    <x-admin.flash />

    <form method="post" action="{{ route('admin.partners.payouts.store') }}"
        class="mb-8 grid max-w-3xl grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2">
        @csrf
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm" for="partner_id">CTV</label>
            <select id="partner_id" name="partner_id" required class="w-full rounded-lg bg-surface-container-low px-3 py-2">
                <option value="">— Chọn —</option>
                @foreach ($partners as $partner)
                    <option value="{{ $partner->id }}">{{ $partner->display_name }} ({{ $partner->user?->email }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm" for="period_from">Từ ngày</label>
            <input id="period_from" name="period_from" type="date" required value="{{ old('period_from', now()->startOfMonth()->toDateString()) }}"
                class="w-full rounded-lg bg-surface-container-low px-3 py-2">
        </div>
        <div>
            <label class="mb-1 block font-label-sm" for="period_to">Đến ngày</label>
            <input id="period_to" name="period_to" type="date" required value="{{ old('period_to', now()->toDateString()) }}"
                class="w-full rounded-lg bg-surface-container-low px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm" for="note">Ghi chú</label>
            <input id="note" name="note" value="{{ old('note') }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary">Tạo kỳ & duyệt</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">CTV</th>
                    <th class="px-4 py-3">Kỳ</th>
                    <th class="px-4 py-3">Số tiền</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payouts as $payout)
                    <tr class="border-b border-outline-variant/60">
                        <td class="px-4 py-3">{{ $payout->partner?->display_name }}</td>
                        <td class="px-4 py-3">{{ $payout->period_from->format('d/m/Y') }} — {{ $payout->period_to->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ MoneyFormatter::vnd((int) $payout->amount_cents) }}</td>
                        <td class="px-4 py-3">{{ $payout->status->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($payout->status->value !== 'paid' && $payout->status->value !== 'cancelled')
                                <form method="post" action="{{ route('admin.partners.payouts.mark-paid', $payout) }}">
                                    @csrf
                                    <button type="submit" class="text-primary hover:underline">Đánh dấu đã chi</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-on-surface-variant">Chưa có kỳ chi trả.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payouts->links() }}</div>
</x-layouts.admin>
