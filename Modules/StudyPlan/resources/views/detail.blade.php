@php
    // Static port of html/pc-study-path-detail.html + week accordion (Amboss-style day timeline).
    $weekDays = [
        [
            'date' => '28 tháng 7, 2026',
            'status' => 'skipped',
            'statusLabel' => 'Bỏ qua',
            'statusClass' => 'bg-red-50 text-red-600',
            'marker' => 'skipped',
            'done' => 0,
            'total' => 49,
            'action' => 'start',
            'actionLabel' => 'Bắt đầu',
            'actionClass' => 'border border-outline-variant bg-white text-on-surface hover:bg-surface-container-low',
            'stats' => null,
        ],
        [
            'date' => '29 tháng 7, 2026',
            'status' => 'incomplete',
            'statusLabel' => 'Chưa xong',
            'statusClass' => 'bg-amber-50 text-amber-700',
            'marker' => 'incomplete',
            'done' => 4,
            'total' => 49,
            'action' => 'resume',
            'actionLabel' => 'Tiếp tục',
            'actionClass' => 'border border-outline-variant bg-white text-on-surface hover:bg-surface-container-low',
            'stats' => [
                ['color' => 'bg-primary', 'label' => '25% đúng'],
                ['color' => 'bg-amber-500', 'label' => '0% đúng khi dùng gợi ý'],
                ['color' => 'bg-red-400', 'label' => '75% sai'],
            ],
        ],
        [
            'date' => '30 tháng 7, 2026',
            'status' => 'skipped',
            'statusLabel' => 'Bỏ qua',
            'statusClass' => 'bg-red-50 text-red-600',
            'marker' => 'skipped',
            'done' => 0,
            'total' => 49,
            'action' => 'start',
            'actionLabel' => 'Bắt đầu',
            'actionClass' => 'border border-outline-variant bg-white text-on-surface hover:bg-surface-container-low',
            'stats' => null,
        ],
        [
            'date' => '31 tháng 7, 2026',
            'status' => 'pending',
            'statusLabel' => null,
            'statusClass' => '',
            'marker' => 'pending',
            'done' => 0,
            'total' => 49,
            'action' => 'start_primary',
            'actionLabel' => 'Bắt đầu',
            'actionClass' => 'bg-primary text-white hover:bg-primary-container',
            'stats' => null,
        ],
    ];

    $weeks = collect(range(1, 8))->map(fn (int $week) => [
        'id' => $week,
        'title' => "Tuần {$week}",
        'progress' => '0/4 hoàn thành',
        'days' => $weekDays,
    ])->all();

    $systems = [
        ['name' => 'Hệ tim mạch', 'percent' => 5],
        ['name' => 'Hệ máu & Bạch huyết', 'percent' => 0],
        ['name' => 'Hệ thần kinh & Giác quan', 'percent' => 2],
        ['name' => 'Hệ hô hấp', 'percent' => 0],
        ['name' => 'Sản khoa & Thai kỳ', 'percent' => 0],
        ['name' => 'Hệ cơ xương khớp', 'percent' => 0],
        ['name' => 'Da & Mô dưới da', 'percent' => 0],
    ];

    $dayPercent = 0;
    $circumference = 364.4;
    $dashOffset = $circumference * (1 - $dayPercent / 100);
@endphp

<x-layouts.app title="Chi tiết lộ trình">
    <div class="mx-auto max-w-container-max px-margin-desktop py-8" x-data="{ openWeek: 1 }">
        <div class="mb-8">
            <a href="{{ route('study-plan.index') }}"
                class="mb-2 flex items-center gap-2 text-label-sm font-bold tracking-wider text-primary uppercase">
                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                Lộ trình học
            </a>
            <h2 class="font-headline-lg text-headline-lg text-on-surface">
                Lộ trình học USMLE Step 2 CK của Charles
            </h2>
        </div>

        <div class="grid grid-cols-1 gap-gutter lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-8">
                @foreach ($weeks as $week)
                    <div class="overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest shadow-sm">
                        <button type="button" @click="openWeek = openWeek === {{ $week['id'] }} ? null : {{ $week['id'] }}"
                            class="group flex w-full cursor-pointer items-center justify-between p-5 text-left transition-colors hover:bg-surface-container-low/50">
                            <div class="flex items-center gap-5">
                                <div
                                    class="flex size-10 items-center justify-center rounded-lg border border-outline-variant bg-surface-container">
                                    <span class="material-symbols-outlined text-primary">calendar_today</span>
                                </div>
                                <div>
                                    <h3 class="font-headline-sm text-headline-sm text-on-surface">{{ $week['title'] }}
                                    </h3>
                                    <p class="text-label-sm tracking-tighter text-on-surface-variant uppercase">
                                        {{ $week['progress'] }}
                                    </p>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200 group-hover:text-primary"
                                :class="openWeek === {{ $week['id'] }} ? 'rotate-180' : ''">expand_more</span>
                        </button>

                        <div x-show="openWeek === {{ $week['id'] }}" x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="border-t border-outline-variant px-4 pb-5 pt-2 sm:px-6">
                            <div class="relative ml-2 space-y-4 pt-2 pl-8 sm:ml-4 sm:pl-10">
                                <div
                                    class="absolute top-3 bottom-16 left-[15px] w-px bg-outline-variant sm:left-[19px]">
                                </div>

                                @foreach ($week['days'] as $day)
                                    <div class="relative flex gap-4">
                                        <div
                                            class="absolute top-5 -left-8 flex size-8 items-center justify-center sm:-left-10">
                                            @if ($day['marker'] === 'skipped')
                                                <span
                                                    class="flex size-6 items-center justify-center rounded bg-red-500 text-white shadow-sm">
                                                    <span class="material-symbols-outlined text-[16px]"
                                                        style="font-variation-settings: 'FILL' 1;">remove</span>
                                                </span>
                                            @elseif ($day['marker'] === 'incomplete')
                                                <span
                                                    class="flex size-6 items-center justify-center rounded-full bg-amber-400 text-white shadow-sm">
                                                    <span class="material-symbols-outlined text-[16px]"
                                                        style="font-variation-settings: 'FILL' 1;">priority_high</span>
                                                </span>
                                            @else
                                                <span
                                                    class="size-5 rounded-full border-2 border-outline-variant bg-white"></span>
                                            @endif
                                        </div>

                                        <div
                                            class="flex flex-1 flex-col gap-3 rounded-xl border border-outline-variant bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                            <div class="min-w-0 flex-1 space-y-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h4 class="font-bold text-on-surface">{{ $day['date'] }}</h4>
                                                    @if ($day['statusLabel'])
                                                        <span
                                                            class="rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wide uppercase {{ $day['statusClass'] }}">{{ $day['statusLabel'] }}</span>
                                                    @endif
                                                </div>
                                                <div
                                                    class="flex items-center gap-1.5 text-label-sm font-medium tracking-wide text-on-surface-variant uppercase">
                                                    <span class="material-symbols-outlined text-[18px]">timer</span>
                                                    {{ $day['done'] }}/{{ $day['total'] }} câu hỏi
                                                </div>
                                                @if ($day['stats'])
                                                    <div
                                                        class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-body-sm text-on-surface-variant">
                                                        @foreach ($day['stats'] as $stat)
                                                            <span class="flex items-center gap-1.5">
                                                                <span
                                                                    class="size-2.5 rounded-full {{ $stat['color'] }}"></span>
                                                                {{ $stat['label'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                            <a href="{{ route('study-plan.session') }}"
                                                class="shrink-0 rounded-lg px-5 py-2 text-center font-label-md text-label-md font-semibold transition-colors {{ $day['actionClass'] }}">
                                                {{ $day['actionLabel'] }}
                                            </a>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="relative flex gap-4 pt-1">
                                    <div
                                        class="absolute top-4 -left-8 flex size-8 items-center justify-center sm:-left-10">
                                        <span
                                            class="material-symbols-outlined text-[22px] text-outline-variant">star</span>
                                    </div>
                                    <div
                                        class="flex-1 rounded-xl bg-amber-500 px-5 py-4 text-white shadow-sm">
                                        <p class="font-bold">{{ $week['title'] }}</p>
                                        <p class="mt-0.5 text-body-sm text-white/90">
                                            Hoàn thành các ngày để mở khóa phân tích tuần này.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="space-y-gutter lg:col-span-4">
                <div
                    class="flex flex-col items-center rounded-xl border border-outline-variant bg-surface-container-lowest p-6 text-center">
                    <h4 class="mb-6 font-headline-sm text-headline-sm">Ngày 2/68</h4>
                    <div class="relative mb-6 size-32">
                        <svg class="h-full w-full -rotate-90 transform">
                            <circle class="text-surface-container" cx="64" cy="64" fill="transparent" r="58"
                                stroke="currentColor" stroke-width="8"></circle>
                            <circle class="text-primary transition-all duration-1000" cx="64" cy="64"
                                fill="transparent" r="58" stroke="currentColor"
                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $dashOffset }}"
                                stroke-width="8"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-[20px] font-bold text-primary">{{ $dayPercent }}%</span>
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase">Hoàn thành</span>
                        </div>
                    </div>
                    <div class="mt-2 w-full border-t border-outline-variant pt-6">
                        <div class="flex flex-col items-center gap-2">
                            <span class="font-headline-md text-headline-md font-bold">0m 0s</span>
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <span class="material-symbols-outlined text-[18px]">timer</span>
                                <span class="text-label-sm font-bold tracking-tight uppercase">Thời gian mỗi câu</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
                    <div class="border-b border-outline-variant bg-surface-container-low p-5">
                        <h4 class="text-label-md font-bold tracking-wider text-on-surface-variant uppercase">
                            Tiến độ lộ trình học
                        </h4>
                    </div>
                    <div class="space-y-6 p-5">
                        @foreach ($systems as $system)
                            <div>
                                <div class="mb-2 flex justify-between">
                                    <span class="text-label-md font-medium">{{ $system['name'] }}</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-container">
                                    <div class="h-full rounded-full bg-primary"
                                        style="width: {{ $system['percent'] }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
