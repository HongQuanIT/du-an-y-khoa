@php
    // Static port of html/pc-statistics.html. Placeholders until session analytics land.
    $topics = [
        [
            'name' => 'Tim mạch',
            'rate' => 85,
            'count' => '17/20',
            'barClass' => 'bg-success',
            'rateClass' => 'text-success',
            'nameClass' => '',
            'rowClass' => '',
            'action' => 'review',
            'actionLabel' => 'Ôn chủ đề này',
        ],
        [
            'name' => 'Dược lý',
            'rate' => 45,
            'count' => '4/9',
            'barClass' => 'bg-error',
            'rateClass' => 'text-error',
            'nameClass' => 'text-error',
            'rowClass' => 'bg-error-container/5',
            'action' => 'urgent',
            'actionLabel' => 'Ôn tập ngay',
        ],
        [
            'name' => 'Hô hấp',
            'rate' => 70,
            'count' => '7/10',
            'barClass' => 'bg-primary',
            'rateClass' => 'text-primary',
            'nameClass' => '',
            'rowClass' => '',
            'action' => 'review',
            'actionLabel' => 'Ôn chủ đề này',
        ],
    ];

    $chartBars = [
        ['label' => 'Tim mạch', 'height' => 85],
        ['label' => 'Dược lý', 'height' => 45],
        ['label' => 'Hô hấp', 'height' => 70],
        ['label' => 'Nhi khoa', 'height' => 60],
        ['label' => 'Ngoại khoa', 'height' => 92],
    ];
@endphp

<x-layouts.app title="Tổng kết phiên luyện">
    <div class="mx-auto max-w-6xl p-4 md:p-8">
        <div class="mb-8">
            <nav class="mb-2 flex gap-2 text-xs text-on-surface-variant">
                <a href="{{ route('qbank.index') }}" class="hover:text-primary">Ngân hàng câu hỏi</a>
                <span>/</span>
                <span>Phiên #1284</span>
                <span>/</span>
                <span class="font-medium text-primary">Summary</span>
            </nav>
            <h2 class="font-headline-lg text-headline-lg text-on-surface">Tổng kết phiên luyện tập</h2>
            <p class="mt-1 font-body-md text-on-surface-variant">
                Chúc mừng bạn đã hoàn thành 40 câu hỏi ôn tập Nội khoa tổng hợp.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div
                class="flex flex-col items-center gap-8 rounded-2xl border border-outline-variant bg-white p-6 shadow-sm md:flex-row md:p-8 lg:col-span-8">
                <div class="donut-chart size-48 shrink-0">
                    <div class="donut-inner">
                        <div class="text-center">
                            <span class="block text-4xl font-extrabold text-primary">75%</span>
                            <span class="text-xs font-semibold text-on-surface-variant">SUCCESS</span>
                        </div>
                    </div>
                </div>
                <div class="w-full flex-1">
                    <div class="mb-6 grid grid-cols-3 gap-4">
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Đúng</p>
                            <p class="text-2xl font-bold text-success">30</p>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Sai</p>
                            <p class="text-2xl font-bold text-error">8</p>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Trống</p>
                            <p class="text-2xl font-bold text-outline">2</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 border-t border-outline-variant pt-6">
                        <div class="flex items-center gap-2 rounded-lg bg-surface-container px-3 py-1.5">
                            <span class="material-symbols-outlined text-sm text-on-surface-variant">timer</span>
                            <span class="text-sm font-medium">42 phút</span>
                        </div>
                        <div
                            class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary-container/10 px-3 py-1.5">
                            <span class="material-symbols-outlined text-sm text-primary">stars</span>
                            <span class="text-sm font-bold text-primary">Percentile: Top 20%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="relative overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm lg:col-span-4">
                <div class="p-6">
                    <h3 class="mb-4 font-headline-sm text-headline-sm">So sánh cộng đồng</h3>
                    <div class="space-y-6">
                        <div>
                            <div class="mb-2 flex justify-between text-sm">
                                <span class="font-medium">Bạn</span>
                                <span class="font-bold text-primary">75%</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-surface-container">
                                <div class="h-full w-[75%] rounded-full bg-primary"></div>
                            </div>
                        </div>
                        <div>
                            <div class="mb-2 flex justify-between text-sm">
                                <span class="font-medium">Trung bình cộng đồng</span>
                                <span class="font-bold text-on-surface-variant">68%</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-surface-container">
                                <div class="h-full w-[68%] rounded-full bg-outline-variant"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="blur-overlay absolute inset-0 flex flex-col items-center justify-center p-6 text-center">
                    <span class="material-symbols-outlined mb-3 text-4xl text-primary"
                        style="font-variation-settings: 'FILL' 1;">lock</span>
                    <h4 class="mb-2 font-bold">Phân tích chi tiết cộng đồng</h4>
                    <p class="mb-4 text-xs text-on-surface-variant">
                        Mở khóa Premium để xem thứ hạng chi tiết của bạn so với hàng nghìn sinh viên khác.
                    </p>
                    <a href="{{ route('landing.pricing') }}"
                        class="premium-gradient rounded-lg px-6 py-2 text-sm font-bold text-white shadow-md transition-transform active:scale-95">
                        Nâng cấp Premium
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm lg:col-span-12">
                <div class="mb-8 flex items-center justify-between">
                    <h3 class="font-headline-sm text-headline-sm">Tỷ lệ đúng theo chủ đề</h3>
                    <div class="flex items-center gap-2">
                        <div class="size-3 rounded bg-primary"></div>
                        <span class="text-xs text-on-surface-variant">Tỷ lệ đúng (%)</span>
                    </div>
                </div>
                <div class="flex h-64 items-end justify-between gap-4 border-b border-outline-variant px-4">
                    @foreach ($chartBars as $bar)
                        <div class="group flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <div
                                class="w-full max-w-[64px] rounded-t-lg bg-primary transition-all duration-500 hover:brightness-125"
                                style="height: {{ $bar['height'] }}%"></div>
                            <span
                                class="w-full truncate text-center text-[10px] font-medium text-on-surface-variant md:text-xs">{{ $bar['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm lg:col-span-12">
                <div class="flex items-center justify-between border-b border-outline-variant p-6">
                    <h3 class="font-headline-sm text-headline-sm">Phân tích chi tiết chủ đề</h3>
                    <button type="button" class="flex items-center gap-1 text-sm font-bold text-primary">
                        Xem tất cả <span class="material-symbols-outlined text-base">chevron_right</span>
                    </button>
                </div>

                <div class="hidden md:block">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="bg-surface-container-low text-on-surface-variant">
                                <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Chủ đề</th>
                                <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Tỷ lệ đúng</th>
                                <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Số câu</th>
                                <th class="px-6 py-4 text-right text-xs font-bold tracking-wider uppercase">Hành động
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach ($topics as $topic)
                                <tr class="{{ $topic['rowClass'] }}">
                                    <td class="px-6 py-4">
                                        <div class="font-bold {{ $topic['nameClass'] }}">{{ $topic['name'] }}</div>
                                    </td>
                                    <td class="w-1/3 px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container">
                                                <div class="h-full {{ $topic['barClass'] }}"
                                                    style="width: {{ $topic['rate'] }}%"></div>
                                            </div>
                                            <span
                                                class="text-sm font-bold {{ $topic['rateClass'] }}">{{ $topic['rate'] }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">{{ $topic['count'] }}</td>
                                    <td class="px-6 py-4 text-right">
                                        @if ($topic['action'] === 'urgent')
                                            <button type="button"
                                                class="rounded-lg bg-error px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors active:scale-95">
                                                {{ $topic['actionLabel'] }}
                                            </button>
                                        @else
                                            <button type="button"
                                                class="rounded-lg px-4 py-2 text-sm font-bold text-primary transition-colors hover:bg-primary/5">
                                                {{ $topic['actionLabel'] }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-outline-variant md:hidden">
                    @foreach ($topics as $topic)
                        <div class="space-y-3 p-4 {{ $topic['rowClass'] }}">
                            <div class="flex items-center justify-between">
                                <span class="font-bold {{ $topic['nameClass'] }}">{{ $topic['name'] }}</span>
                                <span class="text-sm font-medium">{{ $topic['count'] }} câu</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container">
                                    <div class="h-full {{ $topic['barClass'] }}"
                                        style="width: {{ $topic['rate'] }}%"></div>
                                </div>
                                <span
                                    class="text-xs font-bold {{ $topic['rateClass'] }}">{{ $topic['rate'] }}%</span>
                            </div>
                            @if ($topic['action'] === 'urgent')
                                <button type="button"
                                    class="w-full rounded-lg bg-error py-2.5 text-sm font-bold text-white shadow-sm">
                                    {{ $topic['actionLabel'] }}
                                </button>
                            @else
                                <button type="button"
                                    class="w-full rounded-lg border border-outline-variant py-2.5 text-sm font-bold text-primary">
                                    {{ $topic['actionLabel'] }}
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div class="flex w-full items-center gap-4 sm:w-auto">
                <button type="button"
                    class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary-container px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95 sm:flex-initial">
                    <span class="material-symbols-outlined">restart_alt</span>
                    Ôn lại câu sai (8)
                </button>
                <a href="{{ route('flashcards.create') }}"
                    class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-outline-variant bg-white px-6 py-3 font-bold text-on-surface-variant transition-all hover:bg-surface-container-low active:scale-95 sm:flex-initial">
                    <span class="material-symbols-outlined">style</span>
                    <span class="hidden md:inline">Tạo flashcard</span>
                    <span class="md:hidden">Flashcard</span>
                </a>
            </div>
            <a href="{{ route('qbank.review') }}"
                class="flex w-full items-center justify-center gap-1 px-6 py-3 font-bold text-primary underline-offset-4 hover:underline decoration-2 sm:w-auto">
                Xem lại từng câu
                <span class="material-symbols-outlined">arrow_forward</span>
            </a>
        </div>
    </div>
</x-layouts.app>
