<x-layouts.partner title="Tổng quan">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 font-body-sm text-body-sm text-on-surface">
            {{ session('status') }}
        </div>
    @endif

    <x-admin.page-header title="Bảng điều khiển cộng tác viên"
        description="Hiệu quả giới thiệu, hoa hồng và các khoản chi trả của bạn.">
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($primary_invite_url)
                    <button type="button" x-data="{ copied: false, link: @js($primary_invite_url) }"
                        @click="navigator.clipboard.writeText(link); copied = true; setTimeout(() => copied = false, 1500)"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary transition hover:opacity-90">
                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                        <span x-text="copied ? 'Đã sao chép' : 'Sao chép link mời'">Sao chép link mời</span>
                    </button>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-[16px]">update</span>
                    Cập nhật {{ $refreshed_at->format('H:i d/m/Y') }}
                </span>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <p class="mb-6 font-body-sm text-body-sm text-on-surface-variant">
        Xin chào, <span class="font-semibold text-on-surface">{{ $partner->display_name }}</span>.
        Hoa hồng mặc định: {{ number_format($partner->commissionRatePercent(), 1) }}%.
    </p>

    @if (count($kpis) > 0)
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <x-admin.kpi-card :label="$kpi['label']" :value="$kpi['value']" :hint="$kpi['hint']"
                    :icon="$kpi['icon']" :delta="$kpi['delta']" :delta-suffix="$kpi['delta_suffix']"
                    :delta-mode="$kpi['delta_mode']" :href="$kpi['href']" :severity="$kpi['severity']" />
            @endforeach
        </div>
    @endif

    @if (count($charts) > 0)
        <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2" data-admin-dashboard-charts data-charts='@json($charts)'>
            @foreach ($charts as $chart)
                <x-admin.trend-chart :id="$chart['id']" :title="$chart['title']" :subtitle="$chart['subtitle']" />
            @endforeach
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-admin.alerts-panel title="Cần chú ý" description="Mã mời, hoa hồng và chi trả" :alerts="$alerts" />

        <section class="rounded-xl border border-outline-variant bg-surface p-5">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Mã mời đang hoạt động</h3>
                    <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Các link sẵn sàng để chia sẻ</p>
                </div>
                @can('partner_code.view')
                    <a href="{{ route('partner.codes.index') }}" class="font-label-sm text-label-sm text-primary hover:underline">Xem tất cả</a>
                @endcan
            </div>

            @if (count($active_codes) === 0)
                <p class="font-body-sm text-body-sm text-on-surface-variant">Chưa có mã mời nào đang hiệu lực.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($active_codes as $code)
                        <li class="rounded-lg border border-outline-variant px-4 py-3" x-data="{ copied: false, link: @js($code['url']) }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-label-md text-label-md text-on-surface">
                                        {{ $code['code'] }}
                                        @if ($code['label'])
                                            <span class="font-normal text-on-surface-variant">· {{ $code['label'] }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-1 font-label-sm text-label-sm text-on-surface-variant">
                                        {{ number_format($code['rate_percent'], 1) }}% hoa hồng ·
                                        {{ number_format($code['use_count']) }}{{ $code['max_uses'] ? ' / '.number_format($code['max_uses']) : '' }} lượt
                                        @if ($code['expires_at'])
                                            · Hết hạn {{ \Illuminate\Support\Carbon::parse($code['expires_at'])->format('d/m/Y') }}
                                        @endif
                                    </p>
                                </div>
                                <button type="button"
                                    @click="navigator.clipboard.writeText(link); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="shrink-0 font-label-sm text-label-sm text-primary hover:underline"
                                    x-text="copied ? 'Đã copy' : 'Copy link'">Copy link</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="mb-6 rounded-xl border border-outline-variant bg-surface p-5" aria-labelledby="recent-activity-heading">
        <header class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h2 id="recent-activity-heading" class="font-headline-sm text-headline-sm text-on-surface">Hoạt động gần đây</h2>
                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">8 hoạt động mới nhất</p>
            </div>
            <div class="flex shrink-0 flex-wrap justify-end gap-x-3 gap-y-1">
                @can('partner_referral.view')
                    <a href="{{ route('partner.referrals.index') }}" class="font-label-sm text-label-sm text-primary hover:underline">Xem referral</a>
                @endcan
                @can('partner_commission.view')
                    <a href="{{ route('partner.commissions.index') }}" class="font-label-sm text-label-sm text-primary hover:underline">Xem hoa hồng</a>
                @endcan
            </div>
        </header>

        @if (count($recent_activity) === 0)
            <p class="font-body-sm text-body-sm text-on-surface-variant">Chưa có hoạt động nào để hiển thị.</p>
        @else
            <ol class="grid grid-cols-1 gap-3 lg:grid-cols-2" aria-label="Danh sách hoạt động gần đây">
                @foreach ($recent_activity as $item)
                    <li>
                        @php($occurredAt = \Illuminate\Support\Carbon::parse($item['occurred_at']))
                        <article class="h-full">
                            <a href="{{ $item['href'] }}" class="group flex h-full items-start gap-3 rounded-lg border border-outline-variant bg-surface-container-lowest p-4 transition hover:border-primary/40 hover:bg-primary/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-on-primary">
                                    <span class="material-symbols-outlined text-[21px]">{{ $item['icon'] }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-label-md text-label-md text-on-surface">{{ $item['title'] }}</span>
                                    <span class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">{{ $item['description'] }}</span>
                                    <time datetime="{{ $occurredAt->toIso8601String() }}" class="mt-2 block font-label-sm text-label-sm text-on-surface-variant">{{ $occurredAt->diffForHumans() }}</time>
                                </span>
                                <span class="material-symbols-outlined mt-1 text-[18px] text-on-surface-variant/60 transition group-hover:text-primary">chevron_right</span>
                            </a>
                        </article>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <x-admin.quick-actions :actions="$quick_actions" />

    @push('scripts')
        @vite('resources/js/admin/dashboard-charts.js')
    @endpush
</x-layouts.partner>
