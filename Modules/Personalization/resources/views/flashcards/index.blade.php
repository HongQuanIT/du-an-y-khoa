@php
    // Static port of html/pc-flashcards-dashboard.html. Placeholders until decks/SRS land.
    $circumference = 176; // 2πr with r=28

    $decks = [
        [
            'title' => 'Dược lý tim mạch',
            'count' => '450 thẻ kiến thức',
            'percent' => 80,
            'ringClass' => 'text-primary',
            'due' => 12,
            'new' => 5,
            'href' => 'flashcards.deck',
        ],
        [
            'title' => 'Chẩn đoán phân biệt',
            'count' => '820 thẻ kiến thức',
            'percent' => 33,
            'ringClass' => 'text-primary',
            'due' => 45,
            'new' => 15,
        ],
        [
            'title' => 'Câu sai của tôi',
            'count' => '128 thẻ cần xem lại',
            'percent' => 92,
            'ringClass' => 'text-error',
            'due' => 8,
            'new' => null,
        ],
    ];

    $recommended = [
        [
            'badge' => 'Đề xuất',
            'icon' => 'auto_stories',
            'title' => 'Giải phẫu cơ bản',
            'meta' => '1,200 thẻ kiến thức • 4.8 ★',
        ],
        [
            'badge' => 'Mới',
            'icon' => 'biotech',
            'title' => 'Dược lý lâm sàng 2024',
            'meta' => '650 thẻ kiến thức • 4.9 ★',
        ],
    ];

    $forecast = [
        ['day' => 'T2', 'height' => 40, 'barClass' => 'bg-primary/20', 'count' => 12],
        ['day' => 'T3', 'height' => 60, 'barClass' => 'bg-primary/40', 'count' => 18],
        ['day' => 'T4', 'height' => 50, 'barClass' => 'bg-primary/30', 'count' => 15],
        ['day' => 'T5', 'height' => 90, 'barClass' => 'bg-primary/80', 'count' => 27],
        ['day' => 'T6', 'height' => 70, 'barClass' => 'bg-primary/50', 'count' => 21],
        ['day' => 'T7', 'height' => 80, 'barClass' => 'bg-primary/60', 'count' => 24],
        ['day' => 'CN', 'height' => 65, 'barClass' => 'bg-primary/40', 'count' => 19],
    ];
@endphp

<x-layouts.app title="Thẻ ghi nhớ">
    <div class="p-8">
        <!-- Page Header -->
        <div class="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h2 class="font-headline-lg text-headline-lg text-on-surface">Thẻ ghi nhớ</h2>
                <p class="mt-2 font-body-md text-body-md text-on-surface-variant sm:mt-6">
                    Hệ thống lặp lại ngắt quãng (SRS) giúp bạn ghi nhớ kiến thức bền vững.
                </p>
            </div>
            <a href="{{ route('flashcards.create') }}"
                class="flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-bold text-white shadow-lg transition-all hover:opacity-90 active:scale-95">
                <span class="material-symbols-outlined">add</span>
                Tạo bộ thẻ mới
            </a>
        </div>

        <!-- Review Banner -->
        <div
            class="relative mb-10 flex flex-col items-start justify-between gap-6 overflow-hidden rounded-2xl bg-primary-container p-8 text-white shadow-xl sm:flex-row sm:items-center">
            <div class="relative z-10 flex items-center gap-6">
                <div
                    class="flex size-16 items-center justify-center rounded-2xl border border-white/20 bg-white/10 backdrop-blur-md">
                    <span class="material-symbols-outlined text-4xl"
                        style="font-variation-settings: 'FILL' 1;">notifications_active</span>
                </div>
                <div>
                    <h3 class="mb-1 font-headline-md text-headline-md">Đã đến lúc ôn tập!</h3>
                    <p class="font-body-sm text-body-sm text-primary-fixed opacity-90">
                        Bạn có <span class="font-bold text-white">20 thẻ đến hạn</span> và
                        <span class="font-bold text-white">5 thẻ mới</span> hôm nay.
                    </p>
                </div>
            </div>
            <button type="button"
                class="relative z-10 flex items-center gap-2 rounded-xl bg-white px-8 py-3.5 text-lg font-bold text-primary shadow-lg transition-all hover:bg-surface-bright active:scale-95">
                <span class="material-symbols-outlined">play_arrow</span>
                Bắt đầu ôn
            </button>
        </div>

        <div class="grid grid-cols-12 gap-8">
            <!-- Left: Decks -->
            <div class="col-span-12 xl:col-span-8">
                <div class="mb-6 flex items-center justify-between">
                    <h4 class="font-headline-sm text-headline-sm text-on-surface">Bộ thẻ của tôi</h4>
                    <div class="flex gap-2 rounded-lg bg-surface-container p-1">
                        <button type="button" class="rounded-md bg-white p-1.5 text-primary shadow-sm">
                            <span class="material-symbols-outlined">grid_view</span>
                        </button>
                        <button type="button" class="p-1.5 text-on-surface-variant hover:text-on-surface">
                            <span class="material-symbols-outlined">list</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    @foreach ($decks as $deck)
                        @php
                            $offset = $circumference * (1 - $deck['percent'] / 100);
                            $tag = !empty($deck['href']) ? 'a' : 'div';
                        @endphp
                        <{{ $tag }}
                            @if (!empty($deck['href'])) href="{{ route($deck['href']) }}" @endif
                            class="group relative block cursor-pointer overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 transition-all hover:shadow-xl">
                            <div class="mb-6 flex items-start justify-between">
                                <div class="relative size-16">
                                    <svg class="h-full w-full -rotate-90">
                                        <circle class="text-surface-container" cx="32" cy="32" fill="transparent"
                                            r="28" stroke="currentColor" stroke-width="4"></circle>
                                        <circle class="{{ $deck['ringClass'] }}" cx="32" cy="32" fill="transparent"
                                            r="28" stroke="currentColor" stroke-dasharray="{{ $circumference }}"
                                            stroke-dashoffset="{{ $offset }}" stroke-width="4"
                                            stroke-linecap="round"></circle>
                                    </svg>
                                    <span
                                        class="absolute inset-0 flex items-center justify-center text-sm font-bold">{{ $deck['percent'] }}%</span>
                                </div>
                                <button type="button" onclick="event.preventDefault(); event.stopPropagation();"
                                    class="rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-container">
                                    <span class="material-symbols-outlined">more_vert</span>
                                </button>
                            </div>
                            <h5
                                class="mb-1 font-headline-sm text-headline-sm transition-colors group-hover:text-primary">
                                {{ $deck['title'] }}</h5>
                            <p class="mb-6 text-sm text-on-surface-variant">{{ $deck['count'] }}</p>
                            <div class="flex flex-wrap gap-3">
                                <div
                                    class="flex items-center gap-1.5 rounded-lg bg-tertiary-fixed px-3 py-1.5 text-[11px] font-bold text-on-tertiary-fixed">
                                    <span class="material-symbols-outlined text-sm">event_repeat</span>
                                    {{ $deck['due'] }} đến hạn
                                </div>
                                @if ($deck['new'])
                                    <div
                                        class="flex items-center gap-1.5 rounded-lg bg-primary-fixed px-3 py-1.5 text-[11px] font-bold text-on-primary-fixed">
                                        <span class="material-symbols-outlined text-sm">new_releases</span>
                                        {{ $deck['new'] }} mới
                                    </div>
                                @endif
                            </div>
                        </{{ $tag }}>
                    @endforeach

                    <a href="{{ route('flashcards.create') }}"
                        class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-outline-variant p-6 text-on-surface-variant transition-all hover:border-primary hover:bg-surface-container-low">
                        <div
                            class="mb-4 flex size-14 items-center justify-center rounded-full bg-surface-container transition-colors group-hover:bg-primary-fixed group-hover:text-primary">
                            <span class="material-symbols-outlined text-3xl">add</span>
                        </div>
                        <p class="font-bold">Tạo bộ thẻ mới</p>
                        <p class="mt-1 text-xs opacity-70">Bắt đầu hành trình ghi nhớ</p>
                    </a>
                </div>

                <div class="mt-10">
                    <div class="mb-6 flex items-center justify-between">
                        <h4 class="font-headline-sm text-headline-sm text-on-surface">Bộ thẻ đề xuất</h4>
                        <a href="#" class="text-sm font-bold text-primary hover:underline">Xem tất cả</a>
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        @foreach ($recommended as $item)
                            <div
                                class="group relative overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 transition-all hover:shadow-xl">
                                <div class="absolute top-4 right-4">
                                    <span
                                        class="rounded bg-primary/10 px-2 py-1 text-[10px] font-bold tracking-wider text-primary uppercase">{{ $item['badge'] }}</span>
                                </div>
                                <div class="mb-4 flex size-12 items-center justify-center rounded-xl bg-primary/10">
                                    <span
                                        class="material-symbols-outlined text-primary">{{ $item['icon'] }}</span>
                                </div>
                                <h5
                                    class="mb-1 font-headline-sm text-headline-sm transition-colors group-hover:text-primary">
                                    {{ $item['title'] }}</h5>
                                <p class="mb-6 text-sm text-on-surface-variant">{{ $item['meta'] }}</p>
                                <button type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-primary py-2.5 text-sm font-bold text-primary transition-all hover:bg-primary hover:text-white">
                                    <span class="material-symbols-outlined text-sm">add_circle</span>
                                    Thêm vào thư viện
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right: SRS stats -->
            <div class="col-span-12 space-y-6 xl:col-span-4">
                <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm">
                    <div class="mb-8 flex items-center justify-between">
                        <h4 class="flex items-center gap-2 text-lg font-bold">
                            <span class="material-symbols-outlined text-primary">analytics</span>
                            Thống kê SRS
                        </h4>
                        <span
                            class="rounded bg-surface-container px-2 py-1 text-[10px] font-bold tracking-wider text-on-surface-variant uppercase">7
                            ngày qua</span>
                    </div>

                    <div class="mb-8 grid grid-cols-2 gap-4">
                        <div class="rounded-xl border border-outline-variant/50 bg-surface-container-low p-4">
                            <p class="mb-1 text-[11px] font-bold tracking-wider text-on-surface-variant uppercase">Ghi
                                nhớ</p>
                            <div class="flex items-end gap-2">
                                <p class="text-3xl font-bold text-primary">89%</p>
                                <div class="mb-1 flex items-center text-[10px] font-bold text-primary">
                                    <span class="material-symbols-outlined text-xs">trending_up</span> +2.4%
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-outline-variant/50 bg-surface-container-low p-4">
                            <p class="mb-1 text-[11px] font-bold tracking-wider text-on-surface-variant uppercase">Chuỗi
                                ngày</p>
                            <div class="flex items-end gap-2">
                                <p class="text-3xl font-bold text-tertiary">12</p>
                                <div class="mb-1 flex items-center text-[10px] font-bold text-tertiary">
                                    <span class="material-symbols-outlined text-xs">local_fire_department</span> Hot!
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="mb-6 text-sm font-bold">Dự báo thẻ đến hạn</p>
                    <div class="mb-4 flex h-32 items-end justify-between gap-2.5 px-1">
                        @foreach ($forecast as $bar)
                            <div class="group relative flex-1 rounded-t-md transition-all hover:bg-primary {{ $bar['barClass'] }}"
                                style="height: {{ $bar['height'] }}%">
                                <div
                                    class="absolute -top-8 left-1/2 z-10 -translate-x-1/2 rounded bg-on-surface px-2 py-1 text-[10px] whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    {{ $bar['count'] }} thẻ
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between px-1 text-[10px] font-bold text-on-surface-variant">
                        @foreach ($forecast as $bar)
                            <span>{{ $bar['day'] }}</span>
                        @endforeach
                    </div>
                </div>

                <div
                    class="relative overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-high p-6">
                    <div class="relative z-10">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">lightbulb</span>
                            <p class="text-sm font-bold text-primary">Mẹo học tập</p>
                        </div>
                        <p class="mb-4 text-sm leading-relaxed font-medium text-on-surface">
                            Sử dụng phương pháp <span class="font-bold text-primary">“Chủ động gợi nhớ”</span> trước khi
                            lật flashcard để tăng hiệu quả ghi nhớ lên 300%.
                        </p>
                        <a href="#" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                            Tìm hiểu thêm
                            <span class="material-symbols-outlined text-xs">arrow_forward</span>
                        </a>
                    </div>
                    <span
                        class="material-symbols-outlined absolute -right-6 -bottom-6 rotate-12 text-[140px] text-primary/5">psychology</span>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
