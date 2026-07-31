@php
    // Static port of html/pc-study-path.html. Placeholders until StudyPlan tasks land.
    $progressPercent = 42;
    $progressDegrees = ($progressPercent / 100) * 360;

    $tasks = [
        [
            'icon' => 'route',
            'iconWrap' => 'bg-primary/10 text-primary',
            'meta' => 'Ngày 2/68',
            'title' => 'Lộ trình học USMLE Step 2 CK của Charles',
            'hint' => 'Tiếp tục tuần 1 — Tim mạch & Nội khoa',
            'href' => 'study-plan.detail',
            'actionLabel' => 'Tiếp tục lộ trình',
        ],
        [
            'icon' => 'menu_book',
            'iconWrap' => 'bg-secondary/10 text-secondary',
            'meta' => 'Ngày 2/68',
            'title' => 'Lộ trình học USMLE Step 2 CK của Charles',
            'hint' => "Đọc bài 'Suy tim' — Phác đồ ESC 2023",
            'href' => 'study-plan.detail',
            'actionLabel' => 'Tiếp tục lộ trình',
        ],
        [
            'icon' => 'style',
            'iconWrap' => 'bg-tertiary/10 text-tertiary',
            'meta' => '15 thẻ',
            'title' => 'Ôn 15 flashcard',
            'hint' => 'Dược lý tim mạch',
            'actionLabel' => 'Bắt đầu',
        ],
    ];
@endphp

<x-layouts.app title="Kế hoạch học tập">
    <div class="mx-auto max-w-[1200px] space-y-8 p-8">
        <!-- Plan header -->
        <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="space-y-1">
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Ôn thi Bác sĩ nội trú 2026</h1>
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="rounded border border-error-container/20 bg-error-container/10 px-2 py-0.5 text-label-sm font-semibold text-error">Còn
                        84 ngày</span>
                    <span class="text-body-sm text-on-surface-variant">Cập nhật lần cuối: 2 giờ trước</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('study-plan.schedule') }}"
                    class="flex items-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2 font-label-md text-primary transition-all hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[20px]">calendar_month</span>
                    Lịch trình
                </a>
                <a href="{{ route('study-plan.create') }}"
                    class="flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2 font-label-md text-white shadow-sm transition-all hover:opacity-90">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tạo kế hoạch mới
                </a>
            </div>
        </div>

        <!-- Progress summary -->
        <div class="grid grid-cols-1 gap-6 rounded-xl border border-outline-variant bg-surface-container-lowest p-6 sm:grid-cols-3">
            <div class="flex flex-col items-center justify-center border-outline-variant sm:border-r">
                <div class="relative mb-2 size-16">
                    <div class="absolute inset-0 flex items-center justify-center rounded-full"
                        style="background: conic-gradient(rgb(15, 118, 110) {{ $progressDegrees }}deg, rgb(235, 239, 237) 0deg);">
                        <div class="flex size-[80%] items-center justify-center rounded-full bg-white">
                            <span class="font-bold text-primary">{{ $progressPercent }}%</span>
                        </div>
                    </div>
                </div>
                <span class="text-label-sm tracking-tight text-on-surface-variant uppercase">Hoàn thành</span>
            </div>
            <div class="flex flex-col items-center justify-center border-outline-variant sm:border-r">
                <span class="font-headline-sm text-headline-sm font-bold text-on-surface">450 / 1200</span>
                <span class="text-label-sm tracking-tight text-on-surface-variant uppercase">Câu hỏi</span>
            </div>
            <div class="flex flex-col items-center justify-center">
                <span class="font-headline-sm text-headline-sm font-bold text-on-surface">85%</span>
                <span class="text-label-sm tracking-tight text-on-surface-variant uppercase">Độ bám lịch</span>
            </div>
        </div>

        <!-- Adaptive suggestion -->
        <div class="flex flex-col items-start gap-4 rounded-lg border border-primary-fixed-dim/30 bg-[#F0FDFA] p-4 sm:flex-row sm:items-center">
            <span class="material-symbols-outlined text-primary-container">info</span>
            <p class="flex-1 text-body-md text-primary-container">
                <span class="font-bold">Gợi ý:</span> Tăng cường ôn tập phần Dược lý để cải thiện tỷ lệ chính xác.
            </p>
            <button type="button" class="font-label-md text-primary-container hover:underline">Chi tiết</button>
        </div>

        <!-- Today's tasks -->
        <div class="space-y-6">
            <div>
                <h2 class="mb-1 font-headline-sm text-headline-sm text-on-surface">Công việc hôm nay</h2>
                <p class="text-body-sm text-on-surface-variant">Thứ Hai, 23 tháng 10</p>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                @foreach ($tasks as $task)
                    <div
                        class="flex h-full flex-col rounded-xl border border-outline-variant bg-white p-6 transition-shadow hover:shadow-md">
                        <div class="mb-4 flex items-start justify-between">
                            <div class="rounded-lg p-2 {{ $task['iconWrap'] }}">
                                <span class="material-symbols-outlined">{{ $task['icon'] }}</span>
                            </div>
                            <span class="text-label-sm text-on-surface-variant">{{ $task['meta'] }}</span>
                        </div>
                        <h3 class="mb-1 font-bold text-on-surface">{{ $task['title'] }}</h3>
                        <p class="mb-6 flex-1 text-body-sm text-on-surface-variant">{{ $task['hint'] }}</p>
                        @if (!empty($task['href']))
                            <a href="{{ route($task['href']) }}"
                                class="block w-full rounded-lg border border-primary py-2 text-center font-label-md text-primary transition-colors hover:bg-primary/5">
                                {{ $task['actionLabel'] }}
                            </a>
                        @else
                            <button type="button"
                                class="w-full rounded-lg border border-primary py-2 font-label-md text-primary transition-colors hover:bg-primary/5">
                                {{ $task['actionLabel'] }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
