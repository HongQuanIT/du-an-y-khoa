<x-layouts.teach title="Tổng quan">
    <x-admin.page-header title="Bảng điều khiển giảng viên"
        description="Lớp của bạn, buổi live sắp tới và hàng duyệt câu hỏi chuyên môn.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px]">update</span>
                Cập nhật {{ $refreshed_at->format('H:i d/m/Y') }}
            </span>
        </x-slot:actions>
    </x-admin.page-header>

    @if (count($kpis) > 0)
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
        <x-admin.alerts-panel
            title="Cần chú ý"
            description="Việc cần xử lý trên lớp và duyệt câu hỏi"
            :alerts="$alerts"
            :view-all-href="auth()->user()?->can('classroom.view') ? route('teach.classes.index') : null" />

        <div class="space-y-6">
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Sắp live</h3>
                        <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Buổi đã lên lịch gần nhất</p>
                    </div>
                    @if (auth()->user()?->can('classroom.view'))
                        <a href="{{ route('teach.classes.index') }}" class="font-label-sm text-label-sm text-primary hover:underline">Xem lớp</a>
                    @endif
                </div>

                @if (count($upcoming_sessions) === 0)
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Chưa có buổi live nào được lên lịch.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($upcoming_sessions as $session)
                            <li>
                                <a href="{{ $session['href'] }}"
                                    class="flex items-start gap-3 rounded-lg border border-outline-variant px-4 py-3 transition hover:border-primary/40 hover:bg-surface-container-low">
                                    <span class="material-symbols-outlined mt-0.5 text-[20px] text-primary">podcasts</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-label-md text-label-md text-on-surface">{{ $session['title'] }}</span>
                                        <span class="mt-0.5 block font-body-sm text-body-sm text-on-surface-variant">
                                            {{ $session['classroom_title'] }} ·
                                            {{ $session['scheduled_at']->timezone(config('app.timezone'))->format('H:i · d/m/Y') }}
                                        </span>
                                    </span>
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant/60">chevron_right</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Câu chờ duyệt</h3>
                        <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Gán chuyên môn gần nhất</p>
                    </div>
                    @can('question.view')
                        <a href="{{ route('teach.questions.reviews.index') }}" class="font-label-sm text-label-sm text-primary hover:underline">Hàng đợi</a>
                    @endcan
                </div>

                @if (count($pending_reviews) === 0)
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Không có câu hỏi đang chờ bạn duyệt.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($pending_reviews as $review)
                            <li>
                                <a href="{{ $review['href'] }}"
                                    class="flex items-start gap-3 rounded-lg border border-outline-variant px-4 py-3 transition hover:border-primary/40 hover:bg-surface-container-low">
                                    <span class="material-symbols-outlined mt-0.5 text-[20px] text-primary">rate_review</span>
                                    <span class="min-w-0 flex-1">
                                        @if (filled($review['code']))
                                            <span class="mb-0.5 block font-mono text-xs text-on-surface-variant">{{ $review['code'] }}</span>
                                        @endif
                                        <span class="block font-label-md text-label-md text-on-surface">{{ $review['stem'] }}</span>
                                    </span>
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant/60">chevron_right</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

    <x-admin.quick-actions :actions="$quick_actions" />

    @push('scripts')
        @vite('resources/js/admin/dashboard-charts.js')
    @endpush
</x-layouts.teach>
