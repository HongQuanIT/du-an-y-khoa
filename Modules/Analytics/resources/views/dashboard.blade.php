@php
    // Continue learning, today's tasks and weak topics come from the controller.
    // The streak, chart, recommendations and activity feed are still placeholders
    // from html/pc-dashboard.html until Analytics ships its rollups.
    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 11 => 'Chào buổi sáng',
        $hour < 14 => 'Chào buổi trưa',
        $hour < 18 => 'Chào buổi chiều',
        default => 'Chào buổi tối',
    };

    $firstName = Str::afterLast(auth()->user()->name, ' ');

    $stats = [
        ['icon' => 'fact_check', 'iconClass' => 'text-primary bg-primary-container/20', 'delta' => '+45', 'deltaClass' => 'text-primary', 'value' => '1.240', 'label' => 'Câu đã làm'],
        ['icon' => 'analytics', 'iconClass' => 'text-secondary bg-secondary-fixed/20', 'delta' => '+3%', 'deltaClass' => 'text-secondary', 'value' => '72%', 'label' => 'Tỷ lệ đúng'],
        ['icon' => 'schedule', 'iconClass' => 'text-tertiary bg-tertiary-fixed/20', 'delta' => null, 'deltaClass' => '', 'value' => '6h 20m', 'label' => 'Học tuần này'],
        ['icon' => 'local_fire_department', 'iconClass' => 'text-orange-500 bg-orange-100', 'delta' => null, 'deltaClass' => '', 'value' => '15', 'label' => 'Ngày Streak'],
    ];

    $chartHeights = [40, 55, 35, 60, 80, 45, 90, 50, 30, 65, 40, 85, 55, 100];

    $recommendations = [
        ['topic' => 'Nội khoa', 'title' => 'Phân tích ECG nâng cao trong suy tim', 'duration' => '45m', 'rating' => '4.9', 'icon' => 'cardiology', 'pro' => true],
        ['topic' => 'Ngoại khoa', 'title' => 'Kỹ thuật can thiệp mạch não tối thiểu', 'duration' => '1h 20m', 'rating' => '4.7', 'icon' => 'neurology', 'pro' => false],
        ['topic' => 'Dược lý', 'title' => 'Cơ chế tác động của kháng sinh thế hệ mới', 'duration' => '30m', 'rating' => '4.8', 'icon' => 'medication', 'pro' => false],
        ['topic' => 'Di truyền học', 'title' => 'Ứng dụng CRISPR trong điều trị ung thư', 'duration' => '55m', 'rating' => '5.0', 'icon' => 'genetics', 'pro' => true],
    ];

    $activities = [
        ['icon' => 'quiz', 'circle' => 'bg-primary-container', 'iconClass' => 'text-primary', 'title' => 'Hoàn thành bài kiểm tra Tim mạch', 'detail' => 'Bạn đã đạt 85% tỉ lệ đúng trong 20 phút.', 'time' => '15 phút trước'],
        ['icon' => 'style', 'circle' => 'bg-secondary-fixed', 'iconClass' => 'text-secondary', 'title' => 'Đã ôn 10 thẻ Flashcard Thần kinh', 'detail' => 'Ghi nhớ thành công 8 thẻ mới.', 'time' => '2 giờ trước'],
        ['icon' => 'stars', 'circle' => 'bg-tertiary-fixed', 'iconClass' => 'text-tertiary', 'title' => 'Đã đạt danh hiệu "Chiến binh bền bỉ"', 'detail' => 'Vượt qua mốc 15 ngày học tập liên tiếp.', 'time' => 'Hôm qua'],
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
                <span class="font-label-md text-label-md text-on-tertiary-fixed-variant">15 ngày học liên tục</span>
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
                @foreach ($stats as $stat)
                    <div class="flex flex-col justify-between rounded-xl border border-outline-variant bg-surface p-4">
                        <div class="flex items-start justify-between">
                            <span
                                class="material-symbols-outlined rounded-lg p-2 {{ $stat['iconClass'] }}">{{ $stat['icon'] }}</span>
                            @if ($stat['delta'])
                                <span
                                    class="text-label-sm font-bold {{ $stat['deltaClass'] }}">{{ $stat['delta'] }}</span>
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
            <div class="col-span-12 rounded-xl border border-outline-variant bg-surface p-6">
                <div class="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Tiến trình 30 ngày</h3>
                        <p class="text-body-sm font-body-sm text-on-surface-variant">
                            Theo dõi số lượng câu hỏi hoàn thành hàng ngày
                        </p>
                    </div>
                    <div class="flex rounded-lg bg-surface-container-low p-1">
                        <button type="button"
                            class="rounded-md px-4 py-1.5 text-label-sm font-semibold transition-colors hover:bg-surface-container">7
                            ngày</button>
                        <button type="button"
                            class="rounded-md bg-surface px-4 py-1.5 text-label-sm font-semibold text-primary shadow-sm">30
                            ngày</button>
                        <button type="button"
                            class="rounded-md px-4 py-1.5 text-label-sm font-semibold transition-colors hover:bg-surface-container">Tất
                            cả</button>
                    </div>
                </div>

                <div class="relative flex h-48 w-full items-end gap-2 border-b border-outline-variant px-2">
                    <div class="pointer-events-none absolute inset-0 flex flex-col justify-between py-2 opacity-20">
                        <div class="border-t border-outline"></div>
                        <div class="border-t border-outline"></div>
                        <div class="border-t border-outline"></div>
                        <div class="border-t border-outline"></div>
                    </div>
                    @foreach ($chartHeights as $height)
                        @php $isToday = $loop->last; @endphp
                        <div @class([
                            'flex-1 rounded-t transition-all hover:bg-primary-container',
                            'border-x-2 border-primary bg-primary-container/60' => $isToday,
                            'bg-primary-container/20' => !$isToday,
                        ]) style="height: {{ $height }}%"></div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-label-sm font-medium text-on-surface-variant">
                    <span>01 May</span>
                    <span>10 May</span>
                    <span>20 May</span>
                    <span>Hôm nay</span>
                </div>
            </div>

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
                        <div class="space-y-2">
                            <div class="flex justify-between text-body-sm font-body-sm">
                                <span class="font-medium">{{ $topic['name'] }}</span>
                                <span class="font-bold {{ $tone[0] }}">{{ $topic['accuracy'] }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-surface-container-low">
                                <div class="h-full rounded-full {{ $tone[1] }}" style="width: {{ $topic['accuracy'] }}%">
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-body-sm text-on-surface-variant">
                            Làm thêm vài phiên luyện để hệ thống chỉ ra chủ đề bạn còn yếu.
                        </p>
                    @endforelse
                </div>
                <a href="{{ route('qbank.create') }}"
                    class="mt-8 block w-full rounded-lg bg-surface-container py-2.5 text-center font-semibold text-on-surface transition-all hover:bg-outline-variant/20">
                    Luyện tập ngay
                </a>
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
                    <a href="#" class="flex items-center gap-1 text-body-sm font-semibold text-primary hover:underline">
                        Xem tất cả
                        <span class="material-symbols-outlined text-body-sm">arrow_forward</span>
                    </a>
                </div>
                <div class="no-scrollbar -mx-2 flex gap-gutter overflow-x-auto px-2 pb-4">
                    @foreach ($recommendations as $item)
                        <div
                            class="group min-w-[280px] overflow-hidden rounded-xl border border-outline-variant bg-surface transition-all hover:shadow-lg">
                            <div class="relative flex h-32 items-center justify-center bg-surface-container">
                                <span
                                    class="material-symbols-outlined text-6xl text-primary/30 transition-transform duration-500 group-hover:scale-110"
                                    style="font-variation-settings: 'FILL' 1;">{{ $item['icon'] }}</span>
                                @if ($item['pro'])
                                    <span
                                        class="absolute top-2 right-2 rounded bg-primary px-2 py-1 text-label-sm font-bold text-white">PRO</span>
                                @endif
                            </div>
                            <div class="space-y-2 p-4">
                                <span class="text-label-sm font-bold text-primary uppercase">{{ $item['topic'] }}</span>
                                <h4 class="font-label-md text-label-md leading-tight">{{ $item['title'] }}</h4>
                                <div class="flex items-center gap-3 text-label-sm text-on-surface-variant">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-body-sm">schedule</span>
                                        {{ $item['duration'] }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-body-sm">star</span>
                                        {{ $item['rating'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Recent Activity Timeline -->
            <div class="col-span-12 rounded-xl border border-outline-variant bg-surface p-6">
                <h3 class="mb-6 font-headline-sm text-headline-sm text-on-surface">Hoạt động gần đây</h3>
                <div
                    class="relative space-y-6 before:absolute before:top-2 before:bottom-0 before:left-[11px] before:w-[2px] before:bg-surface-container-high">
                    @foreach ($activities as $activity)
                        <div class="relative flex items-start justify-between pl-8">
                            <div
                                class="absolute top-1.5 left-0 z-10 flex size-6 items-center justify-center rounded-full border-2 border-surface {{ $activity['circle'] }}">
                                <span class="material-symbols-outlined text-label-sm {{ $activity['iconClass'] }}"
                                    style="font-variation-settings: 'FILL' 1;">{{ $activity['icon'] }}</span>
                            </div>
                            <div class="flex-1">
                                <p class="text-body-sm font-semibold text-on-surface">{{ $activity['title'] }}</p>
                                <p class="text-label-sm font-label-sm text-on-surface-variant">
                                    {{ $activity['detail'] }}</p>
                            </div>
                            <span
                                class="ml-4 text-label-sm whitespace-nowrap text-on-surface-variant">{{ $activity['time'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Premium Banner -->
            <div
                class="premium-gradient col-span-12 flex flex-col items-center justify-between gap-6 rounded-xl border border-white/20 p-8 text-white shadow-xl md:flex-row">
                <div class="space-y-2 text-center md:text-left">
                    <h2 class="font-headline-lg text-headline-lg-mobile font-bold md:text-headline-lg">
                        Nâng tầm kiến thức với Premium
                    </h2>
                    <p class="text-body-md font-body-md opacity-90">
                        Truy cập ngân hàng 10,000+ câu hỏi bản quyền và phân tích chuyên sâu AI.
                    </p>
                    <div class="flex flex-wrap justify-center gap-4 pt-2 md:justify-start">
                        @foreach (['Video giải phẫu 3D', 'Mentorship 1:1', 'Tài liệu offline'] as $perk)
                            <span class="flex items-center gap-1 text-label-sm font-semibold">
                                <span class="material-symbols-outlined text-body-sm">done_all</span>
                                {{ $perk }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="flex min-w-[200px] flex-col gap-3">
                    <a href="{{ route('landing.pricing') }}"
                        class="rounded-lg bg-white px-8 py-3 text-center font-bold text-[#FF5E62] shadow-lg transition-all hover:opacity-90 active:scale-95">
                        Nâng cấp Premium ngay
                    </a>
                    <p class="text-center text-label-sm font-bold tracking-widest uppercase opacity-80">
                        Chỉ 99k / tháng
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
