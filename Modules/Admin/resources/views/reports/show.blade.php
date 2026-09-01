<x-layouts.admin :title="$report['title']">
    @php
        $range = $data['range'];
        $frequencyValue = old('frequency', 'weekly');
    @endphp

    <div
        x-data="reportRefreshBanner({
            initialStatus: @js($refreshStatus['status']),
            statusUrl: @js(route('admin.reports.refresh-status', ['category' => $category['slug'], 'report' => $report['slug'], 'range' => $range])),
            showUrl: @js(route('admin.reports.show', ['category' => $category['slug'], 'report' => $report['slug'], 'range' => $range])),
            refreshUrl: @js(route('admin.reports.refresh', [$category['slug'], $report['slug']])),
            range: @js($range),
            csrf: @js(csrf_token()),
        })"
        x-init="init()"
    >
    <x-admin.page-header :title="$report['title']" :description="$report['description']">
        <x-slot:actions>
            <a href="{{ route('admin.reports.show-category', $category['slug']) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-label-md text-on-surface-variant transition hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                {{ $category['title'] }}
            </a>
            <button type="button"
                @click="queueRefresh()"
                :disabled="inFlight"
                class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-label-md text-on-surface transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60">
                <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': inFlight }" x-text="inFlight ? 'progress_activity' : 'cached'"></span>
                <span x-text="inFlight ? 'Đang xử lý…' : 'Làm mới báo cáo'"></span>
            </button>
            @if (count($data['columns']) > 0)
                <a href="{{ route('admin.reports.export', ['category' => $category['slug'], 'report' => $report['slug'], 'range' => $range]) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 font-label-md text-label-md text-on-primary transition hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    Xuất CSV
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="mb-4" x-show="visible" x-cloak>
        <div
            class="rounded-xl border px-4 py-3 font-body-sm text-body-sm"
            :class="bannerClass"
            role="status"
        >
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined mt-0.5 text-[20px]"
                    :class="{ 'animate-spin': inFlight }"
                    x-text="icon"></span>
                <div class="flex-1">
                    <p class="font-label-md text-on-surface" x-text="title"></p>
                    <p class="mt-0.5 text-on-surface-variant" x-text="subtitle"></p>
                </div>
                <button type="button" x-show="justReady" @click="reload()"
                    class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm hover:bg-surface">
                    Tải lại trang
                </button>
            </div>
        </div>
    </div>

    <form method="get" action="{{ route('admin.reports.show', [$category['slug'], $report['slug']]) }}"
        class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-outline-variant bg-surface p-4">
        <div>
            <label for="range" class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant">Khoảng thời gian</label>
            <select id="range" name="range"
                class="h-11 min-w-[160px] rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                onchange="this.form.requestSubmit()">
                @foreach ($ranges as $value => $label)
                    <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <p class="pb-3 font-label-sm text-label-sm text-on-surface-variant">
            {{ $data['from']->format('d/m/Y') }} → {{ $data['to']->format('d/m/Y') }}
            @if (! empty($data['cached_at']))
                <span class="ms-2 text-on-surface-variant/80">· Cache {{ $data['cached_at']->format('d/m H:i') }}</span>
            @endif
        </p>
    </form>

    @if ($data['empty_message'] && count($data['kpis']) === 0 && count($data['charts']) === 0)
        <div class="mb-6 rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-12 text-center">
            <span class="material-symbols-outlined mb-3 text-[48px] text-primary/70">monitoring</span>
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Chưa có dữ liệu báo cáo</h3>
            <p class="mx-auto mt-3 max-w-xl font-body-sm text-body-sm text-on-surface-variant">{{ $data['empty_message'] }}</p>
        </div>
    @else
        @if (count($data['kpis']) > 0)
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($data['kpis'] as $kpi)
                    <x-admin.kpi-card
                        :label="$kpi['label']"
                        :value="$kpi['value']"
                        :hint="$kpi['hint']"
                        :icon="$kpi['icon']"
                        :delta="$kpi['delta']" />
                @endforeach
            </div>
        @endif

        @if (count($data['charts']) > 0)
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2"
                data-admin-dashboard-charts
                data-charts='@json($data['charts'])'>
                @foreach ($data['charts'] as $chart)
                    <x-admin.trend-chart
                        :id="$chart['id']"
                        :title="$chart['title']"
                        :subtitle="$chart['subtitle']"
                        :full-width="(bool) ($chart['full_width'] ?? false)" />
                @endforeach
            </div>
        @endif

        @if (count($data['columns']) > 0)
            <section class="mb-6 overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="flex items-center justify-between border-b border-outline-variant px-5 py-4">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Chi tiết</h3>
                        @if ($data['empty_message'])
                            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $data['empty_message'] }}</p>
                        @else
                            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ count($data['rows']) }} dòng</p>
                        @endif
                    </div>
                </div>

                @if (count($data['rows']) === 0)
                    <p class="px-5 py-10 text-center font-body-sm text-body-sm text-on-surface-variant">Không có dòng dữ liệu trong kỳ này.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left font-body-sm text-body-sm">
                            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                                <tr>
                                    @foreach ($data['columns'] as $column)
                                        <th @class([
                                            'px-4 py-3 whitespace-nowrap',
                                            'text-right' => ($column['align'] ?? 'left') === 'right',
                                        ])>{{ $column['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['rows'] as $row)
                                    <tr class="border-b border-outline-variant/60 last:border-0">
                                        @foreach ($data['columns'] as $column)
                                            <td @class([
                                                'px-4 py-3 whitespace-nowrap text-on-surface',
                                                'text-right tabular-nums' => ($column['align'] ?? 'left') === 'right',
                                            ])>{{ $row[$column['key']] ?? '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @endif

    {{-- Schedule panel --}}
    <section class="rounded-xl border border-outline-variant bg-surface p-5"
        x-data="{
            frequency: @js($frequencyValue),
            sendEmail: @js((bool) old('send_email', true)),
        }">
        <div class="mb-4 flex items-start gap-3">
            <span class="material-symbols-outlined text-[24px] text-primary">schedule_send</span>
            <div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Lên lịch báo cáo</h3>
                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                    Cron warm cache theo chu kỳ cài đặt (mặc định 1 ngày) · Lịch email đến hạn kiểm tra mỗi 5 phút.
                    Bạn có thể <strong>tắt gửi email</strong> mà vẫn giữ lịch (chỉ cập nhật dữ liệu).
                </p>
            </div>
        </div>

        <form method="post" action="{{ route('admin.reports.schedules.store', [$category['slug'], $report['slug']]) }}"
            class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @csrf
            <div>
                <label for="range_key" class="mb-1.5 block font-label-sm text-on-surface-variant">Kỳ dữ liệu</label>
                <select id="range_key" name="range_key"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($ranges as $value => $label)
                        <option value="{{ $value }}" @selected(old('range_key', $range) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="frequency" class="mb-1.5 block font-label-sm text-on-surface-variant">Tần suất</label>
                <select id="frequency" name="frequency" x-model="frequency"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($frequencies as $freq)
                        <option value="{{ $freq->value }}" @selected(old('frequency', 'weekly') === $freq->value)>{{ $freq->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="send_time" class="mb-1.5 block font-label-sm text-on-surface-variant">Giờ chạy</label>
                <input id="send_time" name="send_time" type="time" value="{{ old('send_time', '08:00') }}" required
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>

            <div x-show="frequency === 'weekly'" x-cloak>
                <label for="weekday" class="mb-1.5 block font-label-sm text-on-surface-variant">Ngày trong tuần</label>
                <select id="weekday" name="weekday"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    @foreach ($weekdays as $value => $label)
                        <option value="{{ $value }}" @selected((int) old('weekday', 1) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="frequency === 'monthly'" x-cloak>
                <label for="day_of_month" class="mb-1.5 block font-label-sm text-on-surface-variant">Ngày trong tháng</label>
                <input id="day_of_month" name="day_of_month" type="number" min="1" max="28"
                    value="{{ old('day_of_month', 1) }}"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>

            <div class="md:col-span-2 xl:col-span-3 rounded-lg border border-outline-variant bg-surface-container-low/40 px-4 py-3">
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="hidden" name="send_email" value="0">
                    <input type="checkbox" name="send_email" value="1" class="mt-1 size-4 rounded border-outline-variant text-primary focus:ring-primary"
                        x-model="sendEmail"
                        @checked(old('send_email', true))>
                    <span>
                        <span class="block font-label-md text-on-surface">Gửi email kèm CSV khi đến lịch</span>
                        <span class="block font-label-sm text-on-surface-variant">Bỏ chọn nếu chỉ muốn lịch chạy / không nhận mail.</span>
                    </span>
                </label>
            </div>

            <div class="md:col-span-2 xl:col-span-3" x-show="sendEmail" x-cloak>
                <label for="recipients" class="mb-1.5 block font-label-sm text-on-surface-variant">Email nhận (cách nhau bằng dấu phẩy)</label>
                <input id="recipients" name="recipients" type="text"
                    value="{{ old('recipients', auth()->user()?->email) }}"
                    placeholder="ops@example.com, finance@example.com"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    :required="sendEmail">
                @error('recipients')
                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2 xl:col-span-3">
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary transition hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]">add_alert</span>
                    Tạo lịch
                </button>
            </div>
        </form>

        @if ($schedules->isNotEmpty())
            <div class="border-t border-outline-variant pt-4">
                <h4 class="mb-3 font-title-md text-on-surface">Lịch hiện có cho báo cáo này</h4>
                <ul class="space-y-3">
                    @foreach ($schedules as $schedule)
                        <li class="rounded-lg border border-outline-variant px-4 py-3">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="font-label-md text-on-surface">{{ $schedule->frequencySummary() }} · {{ $schedule->range_key }}</p>
                                    <p class="font-label-sm text-on-surface-variant">
                                        @if ($schedule->send_email)
                                            Mail: {{ $schedule->recipients !== [] ? implode(', ', $schedule->recipients) : '—' }}
                                        @else
                                            Không gửi email
                                        @endif
                                        · Lần tới: {{ $schedule->next_run_at?->format('d/m/Y H:i') ?? '—' }}
                                        @if ($schedule->last_run_at)
                                            · Đã chạy: {{ $schedule->last_run_at->format('d/m/Y H:i') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($schedule->is_active)
                                        <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 font-label-sm text-emerald-700">Lịch: Bật</span>
                                    @else
                                        <span class="rounded-full bg-surface-container-high px-2.5 py-1 font-label-sm text-on-surface-variant">Lịch: Tắt</span>
                                    @endif
                                    @if ($schedule->send_email)
                                        <span class="rounded-full bg-sky-500/10 px-2.5 py-1 font-label-sm text-sky-800">Email: Bật</span>
                                    @else
                                        <span class="rounded-full bg-amber-500/10 px-2.5 py-1 font-label-sm text-amber-800">Email: Tắt</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 border-t border-outline-variant/60 pt-3">
                                <form method="post" action="{{ route('admin.reports.schedules.toggle', $schedule) }}">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-sm text-on-surface transition hover:bg-surface-container-low">
                                        <span class="material-symbols-outlined text-[16px]">{{ $schedule->is_active ? 'pause_circle' : 'play_circle' }}</span>
                                        {{ $schedule->is_active ? 'Tắt lịch' : 'Bật lịch' }}
                                    </button>
                                </form>
                                <form method="post" action="{{ route('admin.reports.schedules.toggle-email', $schedule) }}">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-sm text-on-surface transition hover:bg-surface-container-low">
                                        <span class="material-symbols-outlined text-[16px]">{{ $schedule->send_email ? 'mail_off' : 'mail' }}</span>
                                        {{ $schedule->send_email ? 'Tắt gửi email' : 'Bật gửi email' }}
                                    </button>
                                </form>
                                <form method="post" action="{{ route('admin.reports.schedules.destroy', $schedule) }}"
                                    onsubmit="return confirm('Xóa lịch này?')">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-error/30 px-3 py-2 font-label-sm text-error transition hover:bg-error-container/20">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                        Xóa
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    </div>{{-- reportRefreshBanner --}}

    @push('scripts')
        @if (count($data['charts']) > 0)
            @vite('resources/js/admin/dashboard-charts.js')
        @endif
        <script>
            window.reportRefreshBanner = function (config) {
                return {
                    status: config.initialStatus || 'idle',
                    justReady: false,
                    pollTimer: null,
                    statusUrl: config.statusUrl,
                    showUrl: config.showUrl,
                    refreshUrl: config.refreshUrl,
                    range: config.range,
                    csrf: config.csrf,
                    get inFlight() {
                        return this.status === 'queued' || this.status === 'processing';
                    },
                    get visible() {
                        return this.inFlight || this.status === 'failed' || this.justReady;
                    },
                    get icon() {
                        if (this.inFlight) return 'progress_activity';
                        if (this.status === 'failed') return 'error';
                        return 'check_circle';
                    },
                    get bannerClass() {
                        if (this.inFlight) return 'border-sky-500/30 bg-sky-500/10 text-on-surface';
                        if (this.status === 'failed') return 'border-error/30 bg-error-container/30 text-on-surface';
                        return 'border-emerald-500/30 bg-emerald-500/10 text-on-surface';
                    },
                    get title() {
                        if (this.status === 'queued') return 'Đã đưa vào hàng đợi';
                        if (this.status === 'processing') return 'Đang tạo báo cáo mới nhất…';
                        if (this.status === 'failed') return 'Làm mới báo cáo thất bại';
                        if (this.justReady) return 'Báo cáo mới đã sẵn sàng';
                        return '';
                    },
                    get subtitle() {
                        if (this.inFlight) return 'Bạn có thể tiếp tục xem dữ liệu cache hiện tại. Trang sẽ cập nhật khi xử lý xong.';
                        if (this.status === 'failed') return 'Thử lại sau hoặc kiểm tra hàng đợi worker.';
                        if (this.justReady) return 'Nhấn «Tải lại trang» để xem số liệu mới nhất.';
                        return '';
                    },
                    init() {
                        if (this.inFlight) this.startPolling();
                    },
                    async queueRefresh() {
                        if (this.inFlight) return;
                        this.status = 'queued';
                        this.justReady = false;
                        try {
                            const res = await fetch(this.refreshUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ range: this.range }),
                            });
                            const data = await res.json();
                            this.status = data.status || 'queued';
                            if (this.inFlight) this.startPolling();
                            if (this.status === 'ready') {
                                this.justReady = true;
                            }
                        } catch (e) {
                            this.status = 'failed';
                        }
                    },
                    startPolling() {
                        if (this.pollTimer) return;
                        this.pollTimer = window.setInterval(() => this.poll(), 2000);
                        this.poll();
                    },
                    stopPolling() {
                        if (this.pollTimer) {
                            window.clearInterval(this.pollTimer);
                            this.pollTimer = null;
                        }
                    },
                    async poll() {
                        try {
                            const res = await fetch(this.statusUrl, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            const data = await res.json();
                            const prev = this.status;
                            this.status = data.status || 'idle';
                            if (this.status === 'ready' && (prev === 'queued' || prev === 'processing')) {
                                this.justReady = true;
                                this.stopPolling();
                            }
                            if (this.status === 'failed') this.stopPolling();
                            if (this.status === 'idle') this.stopPolling();
                        } catch (e) {
                            // keep polling
                        }
                    },
                    reload() {
                        window.location.href = this.showUrl;
                    },
                };
            };
        </script>
    @endpush
</x-layouts.admin>
