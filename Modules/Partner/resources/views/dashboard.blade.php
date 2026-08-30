<x-layouts.partner title="Tổng quan">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 font-body-sm text-body-sm text-on-surface">
            {{ session('status') }}
        </div>
    @endif

    <p class="mb-6 font-body-sm text-body-sm text-on-surface-variant">
        Xin chào, <span class="font-semibold text-on-surface">{{ $partner->display_name }}</span>.
        Hoa hồng mặc định: {{ number_format($partner->commissionRatePercent(), 1) }}%.
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-label-sm text-on-surface-variant">Người được mời</p>
            <p class="mt-2 font-headline-sm text-headline-sm text-on-surface">{{ number_format($stats['referral_count']) }}</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-label-sm text-on-surface-variant">Hoa hồng chờ duyệt</p>
            <p class="mt-2 font-headline-sm text-headline-sm text-on-surface">{{ number_format($stats['pending_cents'] / 100) }} ₫</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-label-sm text-on-surface-variant">Đã duyệt</p>
            <p class="mt-2 font-headline-sm text-headline-sm text-on-surface">{{ number_format($stats['approved_cents'] / 100) }} ₫</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface p-4">
            <p class="font-label-sm text-label-sm text-on-surface-variant">Đã chi trả</p>
            <p class="mt-2 font-headline-sm text-headline-sm text-on-surface">{{ number_format($stats['paid_cents'] / 100) }} ₫</p>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('partner.codes.index') }}"
            class="rounded-lg bg-primary px-4 py-2.5 font-label-md text-label-md text-on-primary hover:opacity-90">Xem mã mời</a>
        <a href="{{ route('partner.referrals.index') }}"
            class="rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">Xem người được mời</a>
    </div>
</x-layouts.partner>
