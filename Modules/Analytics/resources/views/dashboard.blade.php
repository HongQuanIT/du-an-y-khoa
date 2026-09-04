@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 11 => 'Chào buổi sáng',
        $hour < 14 => 'Chào buổi trưa',
        $hour < 18 => 'Chào buổi chiều',
        default => 'Chào buổi tối',
    };

    $firstName = Str::afterLast(auth()->user()->name, ' ');

    $formatDuration = function (int $minutes): string {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $hours === 0
            ? $minutes.' phút'
            : ($remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h");
    };
    $statCards = [
        ['icon' => 'fact_check', 'iconClass' => 'text-primary bg-primary-container/20', 'delta' => $stats['questions_delta'], 'suffix' => '', 'value' => number_format($stats['questions_answered'], 0, ',', '.'), 'label' => 'Câu đã làm'],
        ['icon' => 'analytics', 'iconClass' => 'text-secondary bg-secondary-fixed/20', 'delta' => $stats['correct_rate_delta'], 'suffix' => '%', 'value' => $stats['correct_rate'].'%', 'label' => 'Tỷ lệ đúng'],
        ['icon' => 'schedule', 'iconClass' => 'text-tertiary bg-tertiary-fixed/20', 'delta' => null, 'suffix' => '', 'value' => $formatDuration($stats['study_minutes_this_week']), 'label' => 'Học tuần này'],
        ['icon' => 'local_fire_department', 'iconClass' => 'text-orange-500 bg-orange-100', 'delta' => null, 'suffix' => '', 'value' => (string) $stats['streak_days'], 'label' => 'Ngày streak'],
    ];

@endphp

<x-layouts.app title="Trang chủ học tập">
    <div class="mx-auto max-w-[1200px] space-y-gutter p-margin-mobile md:p-margin-desktop">
        <x-cms.announcement-banners placement="dashboard" />

        <!-- Greeting & Streak -->
        <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">
                    {{ $greeting }}, {{ $firstName }} 👋
                </h1>
                <p class="text-body-md font-body-md text-on-surface-variant">
                    @if ($planTasks->isEmpty())
                        Hôm nay bạn chưa có nhiệm vụ nào trong kế hoạch.
                    @else
                        Hôm nay bạn có {{ $planTasks->count() }} mục tiêu cần hoàn thành.
                    @endif
                </p>
            </div>
            <div
                class="flex items-center gap-2 rounded-full border border-tertiary-fixed-dim bg-tertiary-fixed px-4 py-2 shadow-sm">
                <span class="material-symbols-outlined text-tertiary"
                    style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                <span class="font-label-md text-label-md text-on-tertiary-fixed-variant">{{ $stats['streak_days'] }} ngày học liên tục</span>
            </div>
        </div>

        <!-- Dashboard Grid Layout -->
        <div class="grid grid-cols-12 gap-gutter">
            <!-- Continue Learning -->
            <div
                class="group relative col-span-12 flex flex-col justify-between overflow-hidden rounded-xl border border-outline-variant bg-surface p-6 transition-colors hover:border-primary lg:col-span-8">
                <div class="relative z-10 flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
                    <div class="flex-1 space-y-2">
                        <span class="text-label-sm font-bold tracking-wider text-primary uppercase">
                            {{ $continueCard['label'] ?? 'Bắt đầu học' }}
                        </span>
                        <h2 class="font-headline-sm text-headline-sm text-on-surface">
                            {{ $continueCard['title'] ?? 'Chưa có phiên học nào đang dở' }}
                        </h2>
                        <p class="text-body-sm font-body-sm text-on-surface-variant">
                            {{ $continueCard['hint'] ?? 'Tạo kế hoạch học tập hoặc mở một phiên luyện để bắt đầu.' }}
                        </p>
                        @if ($continueCard)
                            <div class="max-w-sm pt-4">
                                <div class="mb-1 flex items-end justify-between">
                                    <span class="text-label-sm font-semibold text-primary">Tiến độ:
                                        {{ $continueCard['progress'] }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high">
                                    <div class="h-full bg-primary transition-all duration-500"
                                        style="width: {{ $continueCard['progress'] }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <a href="{{ $continueCard['url'] ?? route('study-plan.index') }}"
                        class="flex items-center gap-2 rounded-lg bg-primary px-8 py-3 font-semibold text-white shadow-md transition-all hover:bg-primary-container active:scale-95">
                        {{ $continueCard ? 'Tiếp tục học' : 'Tới kế hoạch học tập' }}
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                </div>
                <div
                    class="pointer-events-none absolute -right-12 -bottom-12 opacity-[0.03] transition-opacity group-hover:opacity-[0.07]">
                    <span class="material-symbols-outlined text-[200px]"
                        style="font-variation-settings: 'FILL' 1;">cardiology</span>
                </div>
            </div>

            <!-- Stats Side Column -->
            <div class="col-span-12 grid grid-cols-2 gap-4 lg:col-span-4">
                @foreach ($statCards as $stat)
                    <div class="flex flex-col justify-between rounded-xl border border-outline-variant bg-surface p-4">
                        <div class="flex items-start justify-between">
                            <span
                                class="material-symbols-outlined rounded-lg p-2 {{ $stat['iconClass'] }}">{{ $stat['icon'] }}</span>
                            @if ($stat['delta'] !== null)
                                <span @class([
                                    'text-label-sm font-bold',
                                    'text-primary' => $stat['delta'] >= 0,
                                    'text-error' => $stat['delta'] < 0,
                                ])>{{ $stat['delta'] > 0 ? '+' : '' }}{{ $stat['delta'] }}{{ $stat['suffix'] }}</span>
                            @endif
                        </div>
                        <div>
                            <p class="mt-2 text-2xl font-bold">{{ $stat['value'] }}</p>
                            <p class="text-label-sm font-label-sm text-on-surface-variant">{{ $stat['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Chart Section -->
            <section class="col-span-12 rounded-xl border border-outline-variant bg-surface p-4 sm:p-6"
                aria-labelledby="learning-progress-heading">
                <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h3 id="learning-progress-heading" class="font-headline-sm text-headline-sm text-on-surface">
                            Tiến trình học tập
                        </h3>
                        <p class="text-body-sm font-body-sm text-on-surface-variant">
                            Tỷ lệ trả lời đúng trong
                            {{ $progressRange === '7d' ? '7 ngày gần nhất' : ($progressRange === 'all' ? 'toàn bộ thời gian' : '30 ngày gần nhất') }}.
                        </p>
                    </div>
                    <nav class="grid w-full grid-cols-3 rounded-lg bg-surface-container-low p-1 sm:w-auto"
                        aria-label="Chọn khoảng thời gian">
                        @foreach (['7d' => '7 ngày', '30d' => '30 ngày', 'all' => 'Tất cả'] as $range => $rangeLabel)
                            <a href="{{ route('dashboard', ['range' => $range]) }}"
                                @if ($progressRange === $range) aria-current="page" @endif
                                @class([
                                    'rounded-md px-4 py-2 text-center text-label-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                                    'bg-surface text-primary shadow-sm' => $progressRange === $range,
                                    'text-on-surface-variant hover:bg-surface-container' => $progressRange !== $range,
                                ])>
                                {{ $rangeLabel }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                @if ($progressSummary['questions'] === 0)
                    <div class="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 text-center"
                        role="status">
                        <span class="material-symbols-outlined mb-2 text-4xl text-on-surface-variant">bar_chart</span>
                        <p class="font-semibold text-on-surface">Chưa có dữ liệu học tập</p>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Hoàn thành một phiên luyện để xem tỷ lệ đúng tại đây.</p>
                        <a href="{{ route('qbank.create') }}"
                            class="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            Bắt đầu luyện tập
                        </a>
                    </div>
                @else
                    @php
                        $progressChart = [[
                            'id' => 'student-dashboard-learning-progress',
                            'title' => 'Tỷ lệ đúng theo ngày',
                            'subtitle' => $progressRange === '7d' ? '7 ngày gần nhất' : ($progressRange === 'all' ? 'Toàn bộ thời gian' : '30 ngày gần nhất'),
                            'type' => 'bar',
                            'format' => 'percent',
                            'labels' => array_column($chartBars, 'label'),
                            'datasets' => [[
                                'label' => 'Tỷ lệ đúng',
                                'data' => array_column($chartBars, 'rate'),
                                'color' => '#0f766e',
                            ]],
                        ]];
                    @endphp

                    <dl class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Tổng quan tiến trình học tập">
                        <div class="rounded-lg bg-surface-container-low p-4">
                            <dt class="text-label-sm text-on-surface-variant">Tỷ lệ đúng trung bình</dt>
                            <dd class="mt-1 text-xl font-bold text-on-surface">{{ $progressSummary['rate'] }}%</dd>
                        </div>
                        <div class="rounded-lg bg-surface-container-low p-4">
                            <dt class="text-label-sm text-on-surface-variant">Tổng số câu đã làm</dt>
                            <dd class="mt-1 text-xl font-bold text-on-surface">{{ number_format($progressSummary['questions']) }}</dd>
                        </div>
                        <div class="rounded-lg bg-surface-container-low p-4">
                            <dt class="text-label-sm text-on-surface-variant">Số ngày có hoạt động</dt>
                            <dd class="mt-1 text-xl font-bold text-on-surface">{{ $progressSummary['active_days'] }}</dd>
                        </div>
                    </dl>

                    <div data-admin-dashboard-charts data-charts='@json($progressChart)'>
                        <x-admin.trend-chart
                            id="student-dashboard-learning-progress"
                            title="Tỷ lệ đúng theo ngày"
                            :subtitle="$progressChart[0]['subtitle']" />
                    </div>

                    <details class="mt-4 rounded-lg border border-outline-variant">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-on-surface focus-visible:outline-2 focus-visible:outline-primary">
                            Xem dữ liệu chi tiết
                        </summary>
                        <div class="overflow-x-auto border-t border-outline-variant">
                            <table class="w-full border-collapse text-left text-sm">
                                <caption class="sr-only">Dữ liệu chi tiết tiến trình học tập theo ngày</caption>
                                <thead class="bg-surface-container-low text-on-surface-variant">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 font-semibold">Ngày</th>
                                        <th scope="col" class="px-4 py-3 text-right font-semibold">Đã làm</th>
                                        <th scope="col" class="px-4 py-3 text-right font-semibold">Đúng</th>
                                        <th scope="col" class="px-4 py-3 text-right font-semibold">Tỷ lệ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @foreach ($chartBars as $bar)
                                        @if ($bar['questions'] > 0)
                                            <tr>
                                                <th scope="row" class="px-4 py-3 font-medium text-on-surface">
                                                    <time datetime="{{ $bar['date'] }}">{{ $bar['display_date'] }}</time>
                                                </th>
                                                <td class="px-4 py-3 text-right">{{ $bar['questions'] }}</td>
                                                <td class="px-4 py-3 text-right">{{ $bar['correct'] }}</td>
                                                <td class="px-4 py-3 text-right font-semibold">{{ $bar['rate'] }}%</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </section>

            <!-- Weak Subjects -->
            <div class="col-span-12 rounded-xl border border-outline-variant bg-surface p-6 md:col-span-6">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Chủ đề cần cải thiện</h3>
                    <span class="material-symbols-outlined text-on-surface-variant">warning</span>
                </div>
                <div class="space-y-5">
                    @forelse ($weakTopics as $topic)
                        @php
                            $tone = match (true) {
                                $topic['accuracy'] < 50 => ['text-error', 'bg-error'],
                                $topic['accuracy'] < 60 => ['text-tertiary', 'bg-tertiary'],
                                default => ['text-orange-500', 'bg-orange-500'],
                            };
                        @endphp
                        <div class="space-y-3 rounded-lg border border-outline-variant p-3">
                            <div class="flex justify-between text-body-sm font-body-sm">
                                <span class="font-medium">{{ $topic['name'] }}</span>
                                <span class="font-bold {{ $tone[0] }}">{{ $topic['accuracy'] }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-surface-container-low">
                                <div class="h-full rounded-full {{ $tone[1] }}" style="width: {{ $topic['accuracy'] }}%">
                                </div>
                            </div>
                            <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
                                <p class="text-label-sm text-on-surface-variant">
                                    {{ $topic['incorrect'] }} lượt trả lời sai được ghi nhận.
                                </p>
                                <a href="{{ $topic['practice_url'] }}"
                                    class="rounded-lg border border-primary px-3 py-1.5 text-label-sm font-semibold text-primary transition-colors hover:bg-primary/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    Luyện tập ngay
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-body-sm text-on-surface-variant">
                            Làm thêm vài phiên luyện để hệ thống chỉ ra chủ đề bạn còn yếu.
                        </p>
                    @endforelse
                </div>
                @error('weak_topic')
                    <p class="mt-4 rounded-lg bg-error-container px-3 py-2 text-body-sm text-on-error-container" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <!-- Today's Tasks -->
            <div class="col-span-12 rounded-xl border border-outline-variant bg-surface p-6 md:col-span-6">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Nhiệm vụ hôm nay</h3>
                    <span class="text-label-sm font-bold text-primary">
                        {{ $planTasks->filter(fn($task) => $task->isDone())->count() }}/{{ $planTasks->count() }} Hoàn thành
                    </span>
                </div>
                <div class="space-y-4">
                    @forelse ($planTasks as $task)
                        <div
                            class="group flex items-center gap-4 rounded-lg border border-outline-variant p-3 transition-colors hover:bg-surface-container-low">
                            <span
                                class="material-symbols-outlined {{ $task->isDone() ? 'text-primary' : 'text-on-surface-variant' }}">{{ $task->type->icon() }}</span>
                            <div class="flex-1">
                                <p @class([
                                    'text-body-sm font-semibold text-on-surface',
                                    'line-through opacity-50' => $task->isDone(),
                                ])>{{ $task->title() }}</p>
                                <p class="text-label-sm font-label-sm text-on-surface-variant">
                                    {{ $task->type->label() }} · {{ $task->done }}/{{ $task->target }}
                                </p>
                            </div>
                            @if ($task->isDone())
                                <span class="material-symbols-outlined text-primary">check_circle</span>
                            @elseif ($task->type->isSupported())
                                <form method="POST" action="{{ route('study-plan.tasks.start', [$task->study_plan_id, $task]) }}">
                                    @csrf
                                    <button type="submit"
                                        class="rounded-lg border border-primary px-4 py-1.5 text-label-sm font-semibold text-primary transition-colors hover:bg-primary/5">
                                        {{ $task->isStarted() ? 'Tiếp tục' : 'Bắt đầu' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-body-sm text-on-surface-variant">
                            Chưa có nhiệm vụ nào cho hôm nay.
                            <a href="{{ route('study-plan.index') }}" class="text-primary hover:underline">Tạo kế hoạch
                                học tập</a>
                            để nhận mục tiêu mỗi ngày.
                        </p>
                    @endforelse
                </div>
            </div>

            <!-- Recommendation Carousel -->
            <div class="col-span-12">
                <div class="mb-4 flex items-center justify-between px-2">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Gợi ý cho bạn</h3>
                    <a href="{{ route('qbank.create') }}" class="flex items-center gap-1 text-body-sm font-semibold text-primary hover:underline">
                        Xem tất cả
                        <span class="material-symbols-outlined text-body-sm">arrow_forward</span>
                    </a>
                </div>
                <div class="no-scrollbar -mx-2 flex gap-gutter overflow-x-auto px-2 pb-4">
                    @forelse ($recommendations as $item)
                        <a href="{{ $item['url'] }}"
                            class="group min-w-[280px] overflow-hidden rounded-xl border border-outline-variant bg-surface transition-all hover:shadow-lg">
                            <div class="relative flex h-32 items-center justify-center bg-surface-container">
                                <span
                                    class="material-symbols-outlined text-6xl text-primary/30 transition-transform duration-500 group-hover:scale-110"
                                    style="font-variation-settings: 'FILL' 1;">{{ $item['icon'] }}</span>
                            </div>
                            <div class="space-y-2 p-4">
                                <span class="text-label-sm font-bold text-primary uppercase">{{ $item['eyebrow'] }}</span>
                                <h4 class="font-label-md text-label-md leading-tight">{{ $item['title'] }}</h4>
                                <p class="text-label-sm text-on-surface-variant">{{ $item['description'] }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="w-full rounded-lg bg-surface-container-low px-4 py-8 text-center text-on-surface-variant">
                            Chưa có gợi ý phù hợp. Hãy hoàn thành thêm vài phiên luyện tập.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Recent Activity Timeline -->
            <div class="col-span-12 rounded-xl border border-outline-variant bg-surface p-6">
                <h3 class="mb-6 font-headline-sm text-headline-sm text-on-surface">Hoạt động gần đây</h3>
                <div
                    class="relative space-y-6 before:absolute before:top-2 before:bottom-0 before:left-[11px] before:w-[2px] before:bg-surface-container-high">
                    @forelse ($recentActivities as $activity)
                        <div class="relative flex items-start justify-between pl-8">
                            <div
                                class="absolute top-1.5 left-0 z-10 flex size-6 items-center justify-center rounded-full border-2 border-surface {{ $activity['tone'] === 'primary' ? 'bg-primary-container' : 'bg-secondary-fixed' }}">
                                <span class="material-symbols-outlined text-label-sm {{ $activity['tone'] === 'primary' ? 'text-primary' : 'text-secondary' }}"
                                    style="font-variation-settings: 'FILL' 1;">{{ $activity['icon'] }}</span>
                            </div>
                            <div class="flex-1">
                                <a href="{{ $activity['url'] }}"
                                    class="text-body-sm font-semibold text-on-surface hover:text-primary hover:underline">
                                    {{ $activity['title'] }}
                                </a>
                                <p class="text-label-sm font-label-sm text-on-surface-variant">
                                    {{ $activity['detail'] }}</p>
                            </div>
                            <span class="ml-4 text-label-sm whitespace-nowrap text-on-surface-variant">{{ $activity['time'] }}</span>
                        </div>
                    @empty
                        <div class="pl-8 text-body-sm text-on-surface-variant">
                            Chưa có hoạt động học tập. Hoàn thành một phiên luyện để bắt đầu ghi nhận tiến trình.
                        </div>
                    @endforelse
                </div>
            </div>

            @if (($dashboardSubscription['show_upgrade'] ?? false) || (\Modules\Billing\Support\CurrentSubscription::for(auth()->user())['is_free'] ?? true))
                @php
                    $isFreePlan = (\Modules\Billing\Support\CurrentSubscription::for(auth()->user())['is_free'] ?? true);
                    $planName = $dashboardSubscription['plan_name'] ?? 'Premium';
                    $features = $dashboardSubscription['features'] ?? ['Video giải phẫu 3D', 'Mentorship 1:1', 'Tài liệu offline'];
                    $priceLabel = $dashboardSubscription['price_label'] ?? 'Chỉ từ gói tháng linh hoạt';
                    $upgradeUrl = $dashboardSubscription['show_upgrade'] ?? false ? route('landing.pricing') : route('subscription.upgrade');
                @endphp
                <div
                    class="premium-gradient col-span-12 flex flex-col items-center justify-between gap-6 rounded-xl border border-white/20 p-8 text-white shadow-xl md:flex-row">
                    <div class="space-y-2 text-center md:text-left">
                        <h2 class="font-headline-lg text-headline-lg-mobile font-bold md:text-headline-lg">
                            Nâng tầm kiến thức với {{ $isFreePlan ? 'Premium' : $planName }}
                        </h2>
                        <p class="text-body-md font-body-md opacity-90">
                            {{ $isFreePlan ? 'Truy cập ngân hàng 10,000+ câu hỏi bản quyền và phân tích chuyên sâu AI.' : 'Mở khóa toàn bộ nội dung và các công cụ học tập nâng cao.' }}
                        </p>
                        <div class="flex flex-wrap justify-center gap-4 pt-2 md:justify-start">
                            @foreach ($features as $perk)
                                <span class="flex items-center gap-1 text-label-sm font-semibold">
                                    <span class="material-symbols-outlined text-body-sm">done_all</span>
                                    {{ $perk }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex min-w-[200px] flex-col gap-3">
                        <a href="{{ $upgradeUrl }}"
                            class="rounded-lg bg-white px-8 py-3 text-center font-bold text-[#FF5E62] shadow-lg transition-all hover:opacity-90 active:scale-95">
                            Nâng cấp Premium ngay
                        </a>
                        @if ($priceLabel)
                            <p class="text-center text-label-sm font-bold tracking-widest uppercase opacity-80">
                                {{ $priceLabel }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($progressSummary['questions'] > 0)
        @vite('resources/js/admin/dashboard-charts.js')
    @endif
</x-layouts.app>
