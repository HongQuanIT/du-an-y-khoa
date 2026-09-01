<x-layouts.admin title="Tổng quan">
    <x-admin.page-header title="Tổng quan vận hành"
        description="Sức khỏe hệ thống, hàng đợi vận hành và hoạt động quản trị gần đây.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px]">update</span>
                Cập nhật {{ $refreshed_at->format('H:i d/m/Y') }}
            </span>
        </x-slot:actions>
    </x-admin.page-header>

    @if (count($kpis) > 0)
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <x-admin.kpi-card
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :hint="$kpi['hint']"
                    :icon="$kpi['icon']"
                    :delta="$kpi['delta']"
                    :delta-suffix="$kpi['delta_suffix']"
                    :delta-mode="$kpi['delta_mode']"
                    :href="$kpi['href']"
                    :severity="$kpi['severity']" />
            @endforeach
        </div>
    @else
        <div class="mb-8 rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-10 text-center">
            <span class="material-symbols-outlined mb-3 text-[40px] text-on-surface-variant">dashboard</span>
            <h3 class="font-title-md text-on-surface">Chưa có KPI nào cho quyền của bạn</h3>
            <p class="mx-auto mt-2 max-w-lg font-body-sm text-on-surface-variant">
                Liên hệ Super Admin nếu bạn cần truy cập thêm module quản trị.
            </p>
        </div>
    @endif

    @if (count($charts) > 0)
        <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2"
            data-admin-dashboard-charts
            data-charts='@json($charts)'>
            @foreach ($charts as $chart)
                <x-admin.trend-chart
                    :id="$chart['id']"
                    :title="$chart['title']"
                    :subtitle="$chart['subtitle']"
                    :full-width="(bool) ($chart['full_width'] ?? false)" />
            @endforeach
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-admin.alerts-panel :alerts="$alerts" />

        @if (count($audit_feed) > 0 || auth()->user()?->can(\App\Support\Enums\Permission::AuditView->value))
            <x-admin.audit-feed
                :items="$audit_feed"
                :view-all-href="auth()->user()?->can(\App\Support\Enums\Permission::AuditView->value) ? route('admin.audit.index') : null" />
        @endif
    </div>

    <x-admin.quick-actions :actions="$quick_actions" />

    @push('scripts')
        @vite('resources/js/admin/dashboard-charts.js')
    @endpush
</x-layouts.admin>
