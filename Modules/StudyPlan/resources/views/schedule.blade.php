@php
    // Static port of html/pc-study-schedule.html. Placeholders until calendar tasks land.
    $weekdays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

    $calendarDays = [
        // Previous month padding
        ['day' => 25, 'type' => 'muted'],
        ['day' => 26, 'type' => 'muted'],
        ['day' => 27, 'type' => 'muted'],
        ['day' => 28, 'type' => 'muted'],
        ['day' => 29, 'type' => 'muted'],
        ['day' => 30, 'type' => 'muted'],
        ['day' => 1, 'type' => 'plain'],
        // Days 2–19
        ...collect(range(2, 19))->map(fn (int $d) => ['day' => $d, 'type' => 'plain'])->all(),
        // Missed
        [
            'day' => 20,
            'type' => 'missed',
            'events' => ['Missed: Suy tim', '20 Flashcards'],
            'openReschedule' => true,
        ],
        ['day' => 21, 'type' => 'plain'],
        ['day' => 22, 'type' => 'plain'],
        // Completed
        ['day' => 23, 'type' => 'completed'],
        // Today
        [
            'day' => 24,
            'type' => 'today',
            'events' => ['30 câu Tim mạch', 'Đọc bài Suy tim', '20 Thẻ Dược lý'],
        ],
        [
            'day' => 25,
            'type' => 'plain',
            'events' => ['Đề thi thử #1'],
            'eventClass' => 'bg-surface-variant/50 text-on-surface-variant',
        ],
        ...collect(range(26, 31))->map(fn (int $d) => ['day' => $d, 'type' => 'plain'])->all(),
        // Next month padding
        ...collect(range(1, 5))->map(fn (int $d) => ['day' => $d, 'type' => 'muted'])->all(),
    ];

    $dayTasks = [
        [
            'tag' => 'Luyện đề',
            'tagClass' => 'bg-secondary-container/10 text-secondary-container',
            'duration' => '45p',
            'title' => 'Làm 30 câu Tim mạch',
            'action' => 'Bắt đầu',
            'actionClass' => 'bg-primary text-white hover:bg-primary/90',
        ],
        [
            'tag' => 'Lý thuyết',
            'tagClass' => 'bg-[#8b5cf6]/10 text-[#8b5cf6]',
            'duration' => '60p',
            'title' => 'Đọc bài Suy tim',
            'action' => 'Mở tài liệu',
            'actionClass' => 'border border-primary bg-white text-primary hover:bg-primary-container/10',
        ],
        [
            'tag' => 'Ghi nhớ',
            'tagClass' => 'bg-[#f59e0b]/10 text-[#d97706]',
            'duration' => '15p',
            'title' => 'Ôn 20 Flashcard Dược lý',
            'action' => 'Bắt đầu ôn',
            'actionClass' => 'bg-primary text-white hover:bg-primary/90',
        ],
    ];
@endphp

<x-layouts.app title="Lịch trình học tập">
    <div class="mx-auto w-full max-w-container-max flex-1 p-4 md:p-8" x-data="{ rescheduleOpen: false }">
        <!-- Header Section -->
        <div
            class="mb-8 flex flex-col justify-between gap-6 rounded-xl border border-outline-variant bg-white p-6 shadow-sm md:flex-row md:items-end">
            <div class="flex-1">
                <div class="mb-2 flex flex-wrap items-center gap-3">
                    <span
                        class="rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm text-primary">Kế hoạch
                        cá nhân</span>
                    <span class="flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-[16px]">timer</span>
                        Còn 245 ngày
                    </span>
                </div>
                <h2 class="mb-4 font-headline-lg text-headline-lg text-on-surface">Ôn thi Bác sĩ nội trú 2026</h2>
                <div class="max-w-xl">
                    <div class="mb-2 flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                        <span>Tiến độ tổng thể</span>
                        <span class="font-bold text-primary">42%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high">
                        <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: 42%"></div>
                    </div>
                </div>
            </div>
            <a href="{{ route('study-plan.index') }}"
                class="flex h-fit items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-6 py-2.5 font-label-md text-label-md text-primary transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]">edit</span>
                Chỉnh sửa kế hoạch
            </a>
        </div>

        <!-- Main Layout: Grid -->
        <div class="grid grid-cols-1 gap-gutter lg:grid-cols-12">
            <!-- Left Column: Calendar -->
            <div
                class="flex flex-col overflow-hidden rounded-xl border border-outline-variant bg-white lg:col-span-8">
                <div
                    class="flex items-center justify-between border-b border-outline-variant bg-surface-container-lowest p-4 md:p-6">
                    <div class="flex items-center gap-4">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Tháng 10, 2023</h3>
                        <div class="flex gap-1">
                            <button type="button"
                                class="rounded p-1 text-on-surface-variant hover:bg-surface-container-low">
                                <span class="material-symbols-outlined">chevron_left</span>
                            </button>
                            <button type="button"
                                class="rounded p-1 text-on-surface-variant hover:bg-surface-container-low">
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <button type="button"
                        class="rounded-lg border border-outline-variant px-4 py-2 font-label-sm text-label-sm hover:bg-surface-container-low">
                        Hôm nay
                    </button>
                </div>

                <div class="flex-1 bg-surface-container-lowest p-4 md:p-6">
                    <div
                        class="grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-outline-variant bg-outline-variant">
                        @foreach ($weekdays as $weekday)
                            <div
                                @class([
                                    'bg-surface-container-low py-3 text-center font-label-sm text-label-sm',
                                    'text-error' => $weekday === 'CN',
                                    'text-on-surface-variant' => $weekday !== 'CN',
                                ])>{{ $weekday }}</div>
                        @endforeach

                        @foreach ($calendarDays as $cell)
                            @php
                                $base = 'min-h-[100px] p-2 font-body-sm text-body-sm flex flex-col gap-1';
                                $cellClass = match ($cell['type']) {
                                    'muted' => "bg-white text-surface-dim {$base}",
                                    'missed' => "bg-error-container/20 text-on-surface {$base} relative group cursor-pointer",
                                    'completed' => "bg-[#e6f4ea] text-on-surface {$base}",
                                    'today' => "bg-primary-container/5 text-on-surface {$base} border-2 border-primary relative",
                                    default => "bg-white text-on-surface {$base}",
                                };
                                $eventClass = $cell['eventClass'] ?? match ($cell['type'] ?? '') {
                                    'missed' => 'bg-error/10 text-error',
                                    'today' => 'bg-primary/10 text-primary',
                                    default => 'bg-surface-variant/50 text-on-surface-variant',
                                };
                            @endphp
                            <div @class([$cellClass])
                                @if (!empty($cell['openReschedule'])) @click="rescheduleOpen = true" @endif>
                                @if (($cell['type'] ?? '') === 'today')
                                    <span
                                        class="mb-1 flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white shadow-sm">{{ $cell['day'] }}</span>
                                @elseif (($cell['type'] ?? '') === 'missed')
                                    <span class="font-bold text-error">{{ $cell['day'] }}</span>
                                @elseif (($cell['type'] ?? '') === 'completed')
                                    <span class="font-bold text-[#137333]">{{ $cell['day'] }}</span>
                                    <div class="flex items-center gap-1 text-[11px] font-medium text-[#137333]">
                                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                        Hoàn thành
                                    </div>
                                @else
                                    <span>{{ $cell['day'] }}</span>
                                @endif

                                @foreach ($cell['events'] ?? [] as $event)
                                    <div
                                        class="truncate rounded px-1.5 py-0.5 text-[10px] leading-tight font-medium {{ $eventClass }}">
                                        {{ $event }}
                                    </div>
                                @endforeach

                                @if (($cell['type'] ?? '') === 'missed')
                                    <div
                                        class="absolute inset-0 rounded border border-error opacity-0 transition-opacity group-hover:opacity-100">
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div
                        class="mt-6 flex flex-wrap items-center justify-center gap-4 font-label-sm text-label-sm text-on-surface-variant">
                        <div class="flex items-center gap-2">
                            <span class="size-3 rounded border border-[#137333] bg-[#e6f4ea]"></span> Hoàn thành
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-3 rounded border border-error bg-error-container/20"></span> Bỏ lỡ
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-3 rounded border-2 border-primary bg-primary-container/5"></span> Hôm nay
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-3 rounded border border-outline-variant bg-white"></span> Tương lai
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Task Detail Panel -->
            <div class="flex flex-col gap-6 lg:col-span-4">
                <div
                    class="sticky top-[88px] flex h-full flex-col overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                    <div class="border-b border-outline-variant bg-surface-container-lowest p-5">
                        <h3 class="mb-1 font-headline-sm text-headline-sm text-primary">Nhiệm vụ: Thứ Ba, 24/10</h3>
                        <p class="flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[16px]">pie_chart</span>
                            Tiến độ ngày: 0/3 hoàn thành
                        </p>
                    </div>
                    <div class="flex-1 space-y-4 overflow-y-auto bg-surface-bright p-5">
                        @foreach ($dayTasks as $task)
                            <div
                                class="group rounded-lg border border-outline-variant bg-white p-4 transition-shadow hover:shadow-md">
                                <div class="flex items-start gap-3">
                                    <input type="checkbox"
                                        class="mt-1 size-5 cursor-pointer rounded border-outline text-primary focus:ring-primary">
                                    <div class="flex-1">
                                        <div class="mb-1 flex items-center justify-between">
                                            <span
                                                class="rounded px-2 py-0.5 text-xs font-semibold {{ $task['tagClass'] }}">{{ $task['tag'] }}</span>
                                            <span
                                                class="flex items-center gap-1 text-xs text-on-surface-variant">
                                                <span class="material-symbols-outlined text-[14px]">schedule</span>
                                                {{ $task['duration'] }}
                                            </span>
                                        </div>
                                        <h4 class="mb-2 font-label-md text-label-md text-on-surface">{{ $task['title'] }}
                                        </h4>
                                        <button type="button"
                                            class="w-full rounded-lg py-2 font-label-md text-label-md transition-colors {{ $task['actionClass'] }}">
                                            {{ $task['action'] }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Dời lịch -->
        <div x-show="rescheduleOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-on-surface/40 p-4 backdrop-blur-sm"
            @keydown.escape.window="rescheduleOpen = false">
            <div class="flex w-full max-w-md flex-col overflow-hidden rounded-xl border border-outline-variant bg-white shadow-2xl"
                @click.outside="rescheduleOpen = false">
                <div
                    class="flex items-center justify-between border-b border-outline-variant bg-surface-container-lowest p-5">
                    <h3 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary">calendar_month</span>
                        Dời lịch học
                    </h3>
                    <button type="button"
                        class="rounded-full p-1 text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-on-surface"
                        @click="rescheduleOpen = false">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6">
                    <div
                        class="mb-6 flex items-start gap-3 rounded-lg border border-error/30 bg-error-container/20 p-4">
                        <span class="material-symbols-outlined mt-0.5 text-error">error</span>
                        <p class="font-body-sm text-body-sm text-on-surface">
                            Bạn đã lỡ nhiệm vụ ngày <strong class="text-error">20/10</strong>. Chọn ngày mới để dời lịch
                            hoặc để hệ thống tự động phân bổ lại.
                        </p>
                    </div>
                    <label class="mb-2 block font-label-md text-label-md text-on-surface">Chọn ngày mới</label>
                    <div class="relative mb-6 w-full">
                        <span
                            class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant">calendar_today</span>
                        <input type="text" readonly value="25/10/2023"
                            class="h-11 w-full cursor-pointer rounded-lg border border-outline-variant bg-surface pr-4 pl-10 font-body-md text-body-md text-on-surface outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs text-on-surface-variant">
                        @foreach ($weekdays as $weekday)
                            <div>{{ $weekday }}</div>
                        @endforeach
                    </div>
                    <div class="mb-6 grid grid-cols-7 gap-1 text-center text-sm font-medium">
                        <div class="py-1.5 text-surface-dim">23</div>
                        <div class="rounded-full bg-primary/10 py-1.5 text-primary ring-1 ring-primary/30">24</div>
                        <div
                            class="cursor-pointer rounded-full bg-primary py-1.5 text-white shadow-sm hover:bg-primary/90">
                            25</div>
                        @foreach ([26, 27, 28, 29] as $d)
                            <div class="cursor-pointer rounded-full py-1.5 hover:bg-surface-container-low">{{ $d }}
                            </div>
                        @endforeach
                    </div>
                </div>
                <div
                    class="flex justify-end gap-3 border-t border-outline-variant bg-surface-container-lowest p-5">
                    <button type="button"
                        class="rounded-lg border border-outline-variant bg-white px-5 py-2.5 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low"
                        @click="rescheduleOpen = false">
                        Hủy
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
