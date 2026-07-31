@php
    // Static port of html/pc-question-bank.html. Figures are placeholders until
    // QuestionSession queries feed real history.
    $stats = [
        [
            'label' => 'Tổng phiên',
            'value' => '124',
            'hint' => '+12% tháng này',
            'hintIcon' => 'trending_up',
            'hintClass' => 'text-primary font-bold',
            'icon' => 'history',
            'iconWrap' => 'bg-primary/10 text-primary',
        ],
        [
            'label' => 'Trung bình đúng',
            'value' => '78.5%',
            'hint' => 'Hiệu suất tốt',
            'hintIcon' => 'bolt',
            'hintClass' => 'text-secondary font-bold',
            'icon' => 'check_circle',
            'iconWrap' => 'bg-secondary/10 text-secondary',
        ],
        [
            'label' => 'Thời gian luyện',
            'value' => '42h 15m',
            'hint' => 'Tổng cộng 30 ngày qua',
            'hintIcon' => null,
            'hintClass' => 'text-on-surface-variant',
            'icon' => 'timer',
            'iconWrap' => 'bg-tertiary/10 text-tertiary',
        ],
        [
            'label' => 'Câu hỏi đã làm',
            'value' => '4,520',
            'hint' => '45% bộ đề Q-Bank',
            'hintIcon' => null,
            'hintClass' => 'text-on-surface-variant',
            'icon' => 'task_alt',
            'iconWrap' => 'bg-primary/10 text-primary',
        ],
    ];

    $sessions = [
        [
            'date' => '10/07/2026',
            'title' => 'Tùy chọn - Tim mạch',
            'subtitle' => 'Bệnh lý suy tim & Van tim',
            'mode' => 'Exam',
            'modeClass' => 'bg-secondary/10 text-secondary',
            'count' => 40,
            'accuracy' => 75,
            'barClass' => 'bg-primary',
            'accuracyClass' => 'text-primary',
            'status' => 'completed',
            'statusLabel' => 'Hoàn thành',
            'statusClass' => 'text-green-600',
            'dotClass' => 'bg-green-600',
            'primaryAction' => 'review',
            'primaryLabel' => 'Xem lại',
        ],
        [
            'date' => '09/07/2026',
            'title' => 'Hô hấp nâng cao',
            'subtitle' => 'Cận lâm sàng & X-Quang',
            'mode' => 'Study',
            'modeClass' => 'bg-primary/10 text-primary',
            'count' => 50,
            'accuracy' => 42,
            'barClass' => 'bg-secondary',
            'accuracyClass' => 'text-on-surface',
            'status' => 'paused',
            'statusLabel' => 'Tạm dừng',
            'statusClass' => 'text-amber-500',
            'dotClass' => 'bg-amber-500 animate-pulse',
            'primaryAction' => 'continue',
            'primaryLabel' => 'Tiếp tục',
        ],
        [
            'date' => '08/07/2026',
            'title' => 'Nội tiết cơ bản',
            'subtitle' => 'Đái tháo đường Type 2',
            'mode' => 'Study',
            'modeClass' => 'bg-primary/10 text-primary',
            'count' => 25,
            'accuracy' => 92,
            'barClass' => 'bg-primary',
            'accuracyClass' => 'text-primary',
            'status' => 'completed',
            'statusLabel' => 'Hoàn thành',
            'statusClass' => 'text-green-600',
            'dotClass' => 'bg-green-600',
            'primaryAction' => 'review',
            'primaryLabel' => 'Xem lại',
        ],
    ];
@endphp

<x-layouts.app title="Ngân hàng câu hỏi">
    <section class="mx-auto max-w-container-max p-6 md:p-10">
        <!-- Breadcrumbs -->
        <div class="mb-8">
            <h2 class="mb-2 font-headline-md text-headline-md font-bold text-on-surface">Lịch sử phiên luyện</h2>
            <nav class="flex items-center gap-2 text-label-sm text-on-surface-variant">
                <a class="transition-colors hover:text-primary" href="{{ route('qbank.index') }}">Ngân hàng câu hỏi</a>
                <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                <span class="font-bold text-primary">Lịch sử phiên luyện</span>
            </nav>
        </div>

        <!-- Stats Summary Cards -->
        <div class="mb-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <div
                    class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm transition-all hover:shadow-md">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-label-sm font-medium text-on-surface-variant">{{ $stat['label'] }}</span>
                        <div class="rounded-xl p-2.5 {{ $stat['iconWrap'] }}">
                            <span class="material-symbols-outlined text-[24px]">{{ $stat['icon'] }}</span>
                        </div>
                    </div>
                    <p class="mb-2 text-[32px] leading-none font-bold text-on-surface">{{ $stat['value'] }}</p>
                    <p class="flex items-center text-[12px] {{ $stat['hintClass'] }}">
                        @if ($stat['hintIcon'])
                            <span class="material-symbols-outlined mr-1 text-[16px]">{{ $stat['hintIcon'] }}</span>
                        @endif
                        {{ $stat['hint'] }}
                    </p>
                </div>
            @endforeach
        </div>

        <!-- Filters & Main Content Container -->
        <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm">
            <div
                class="flex flex-col items-center justify-between gap-4 border-b border-outline-variant bg-surface-container-lowest p-6 md:flex-row">
                <div class="flex w-full flex-wrap gap-4 md:w-auto">
                    <div class="relative w-full md:w-48">
                        <select
                            class="w-full appearance-none rounded-xl border border-outline-variant bg-white py-2.5 pr-4 pl-10 text-body-sm transition-all focus:border-primary focus:ring-1 focus:ring-primary">
                            <option>Tất cả chế độ</option>
                            <option>Luyện tập (Study)</option>
                            <option>Thi thử (Exam)</option>
                        </select>
                        <span
                            class="material-symbols-outlined absolute top-3 left-3 text-[20px] text-on-surface-variant">tune</span>
                    </div>
                    <div class="relative w-full md:w-48">
                        <select
                            class="w-full appearance-none rounded-xl border border-outline-variant bg-white py-2.5 pr-4 pl-10 text-body-sm transition-all focus:border-primary focus:ring-1 focus:ring-primary">
                            <option>Mọi chủ đề</option>
                            <option>Tim mạch</option>
                            <option>Hô hấp</option>
                            <option>Tiêu hóa</option>
                        </select>
                        <span
                            class="material-symbols-outlined absolute top-3 left-3 text-[20px] text-on-surface-variant">category</span>
                    </div>
                    <div class="relative w-full md:w-48">
                        <select
                            class="w-full appearance-none rounded-xl border border-outline-variant bg-white py-2.5 pr-4 pl-10 text-body-sm transition-all focus:border-primary focus:ring-1 focus:ring-primary">
                            <option>7 ngày gần đây</option>
                            <option>30 ngày gần đây</option>
                            <option>Tháng này</option>
                        </select>
                        <span
                            class="material-symbols-outlined absolute top-3 left-3 text-[20px] text-on-surface-variant">calendar_today</span>
                    </div>
                </div>
                <a href="{{ route('qbank.create') }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-label-md font-bold text-white shadow-md transition-all hover:bg-primary/90 active:scale-95 md:w-auto">
                    Tạo phiên luyện tập
                </a>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-outline-variant bg-surface-container-lowest text-on-surface-variant">
                            <th class="px-6 py-4 text-label-md font-bold">Ngày thực hiện</th>
                            <th class="px-6 py-4 text-label-md font-bold whitespace-nowrap">Tên / Nguồn đề</th>
                            <th class="px-6 py-4 text-center text-label-md font-bold">Chế độ</th>
                            <th class="px-6 py-4 text-label-md font-bold whitespace-nowrap">Số câu</th>
                            <th class="px-6 py-4 text-label-md font-bold">Tỉ lệ đúng</th>
                            <th class="px-6 py-4 text-label-md font-bold">Trạng thái</th>
                            <th class="px-6 py-4 text-right text-label-md font-bold">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @foreach ($sessions as $session)
                            <tr class="transition-colors hover:bg-surface-container-lowest">
                                <td class="px-6 py-5 text-body-sm text-on-surface-variant">{{ $session['date'] }}</td>
                                <td class="px-6 py-5">
                                    <div class="text-body-sm font-bold text-on-surface">{{ $session['title'] }}</div>
                                    <div class="text-[11px] text-on-surface-variant">{{ $session['subtitle'] }}</div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span
                                        class="rounded-lg px-2.5 py-1 text-[10px] font-bold tracking-wider uppercase {{ $session['modeClass'] }}">{{ $session['mode'] }}</span>
                                </td>
                                <td class="px-6 py-5 text-center text-body-sm font-medium">{{ $session['count'] }}</td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-2 min-w-[80px] flex-1 overflow-hidden rounded-full bg-surface-container-high">
                                            <div class="h-full rounded-full {{ $session['barClass'] }}"
                                                style="width: {{ $session['accuracy'] }}%"></div>
                                        </div>
                                        <span
                                            class="text-body-sm font-bold {{ $session['accuracyClass'] }}">{{ $session['accuracy'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span
                                        class="flex items-center gap-2 text-body-sm font-bold {{ $session['statusClass'] }}">
                                        <span class="size-2 rounded-full {{ $session['dotClass'] }}"></span>
                                        {{ $session['statusLabel'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        @if ($session['primaryAction'] === 'continue')
                                            <a href="{{ route('qbank.session') }}"
                                                class="w-full whitespace-nowrap rounded-xl bg-primary px-5 py-2 text-center text-label-sm font-bold text-white shadow-sm transition-all hover:shadow-md active:scale-95">
                                                {{ $session['primaryLabel'] }}
                                            </a>
                                        @else
                                            <a href="{{ route('qbank.review') }}"
                                                class="w-full whitespace-nowrap rounded-xl border-2 border-primary/20 px-5 py-2 text-center text-label-sm font-bold text-primary transition-all hover:border-primary hover:bg-primary/5">
                                                {{ $session['primaryLabel'] }}
                                            </a>
                                        @endif
                                        <a href="{{ route('qbank.summary') }}"
                                            class="w-full whitespace-nowrap rounded-xl border border-outline-variant px-4 py-2 text-center text-label-sm font-bold text-on-surface-variant transition-all hover:bg-surface-container-low">
                                            Tổng kết
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile List View -->
            <div class="space-y-6 p-6 md:hidden">
                @foreach ($sessions as $session)
                    <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <p class="mb-1 text-[11px] font-medium text-on-surface-variant">{{ $session['date'] }}
                                </p>
                                <h3 class="text-body-md font-bold text-on-surface">{{ $session['title'] }}</h3>
                                <p class="text-[11px] text-on-surface-variant">{{ $session['subtitle'] }}</p>
                            </div>
                            <span
                                class="rounded-lg px-2.5 py-1 text-[10px] font-bold uppercase {{ $session['modeClass'] }}">{{ $session['mode'] }}</span>
                        </div>
                        <div class="mb-4 grid grid-cols-2 gap-4 border-b border-outline-variant/50 pb-4">
                            <div>
                                <p class="mb-1 text-[11px] text-on-surface-variant">Số câu</p>
                                <p class="text-body-sm font-bold">{{ $session['count'] }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-[11px] text-on-surface-variant">Trạng thái</p>
                                <span
                                    class="flex items-center gap-1.5 text-[12px] font-bold {{ $session['statusClass'] }}">
                                    <span class="size-2 rounded-full {{ $session['dotClass'] }}"></span>
                                    {{ $session['statusLabel'] }}
                                </span>
                            </div>
                        </div>
                        <div class="mb-6">
                            <div class="mb-2 flex items-end justify-between">
                                <span class="text-[11px] text-on-surface-variant">Tỉ lệ đúng</span>
                                <span
                                    class="text-body-sm font-bold {{ $session['accuracyClass'] }}">{{ $session['accuracy'] }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-surface-container-high">
                                <div class="h-full rounded-full {{ $session['barClass'] }}"
                                    style="width: {{ $session['accuracy'] }}%"></div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            @if ($session['primaryAction'] === 'continue')
                                <a href="{{ route('qbank.session') }}"
                                    class="w-full rounded-xl bg-primary py-3 text-center text-[12px] font-bold text-white shadow-md transition-all active:scale-95">
                                    {{ $session['primaryLabel'] }}
                                </a>
                            @else
                                <a href="{{ route('qbank.review') }}"
                                    class="w-full rounded-xl border-2 border-primary/20 py-3 text-center text-[12px] font-bold text-primary transition-all active:bg-primary active:text-white">
                                    {{ $session['primaryLabel'] }}
                                </a>
                            @endif
                            <a href="{{ route('qbank.summary') }}"
                                class="w-full rounded-xl border border-outline-variant py-3 text-center text-[12px] font-bold text-on-surface-variant transition-all hover:bg-surface-container-low">
                                Tổng kết
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination (Desktop) -->
            <div
                class="hidden items-center justify-between border-t border-outline-variant bg-surface-container-lowest p-6 md:flex">
                <p class="text-body-sm font-medium text-on-surface-variant">Hiển thị 1 - 10 trên 124 phiên luyện</p>
                <div class="flex gap-2">
                    <button type="button" disabled
                        class="flex size-10 items-center justify-center rounded-xl border border-outline-variant transition-colors hover:bg-surface-container disabled:opacity-30">
                        <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                    </button>
                    <button type="button"
                        class="flex size-10 items-center justify-center rounded-xl bg-primary text-label-md font-bold text-white">1</button>
                    <button type="button"
                        class="flex size-10 items-center justify-center rounded-xl border border-outline-variant font-medium transition-colors hover:bg-surface-container">2</button>
                    <button type="button"
                        class="flex size-10 items-center justify-center rounded-xl border border-outline-variant font-medium transition-colors hover:bg-surface-container">3</button>
                    <span class="flex items-center px-2 font-medium text-on-surface-variant">...</span>
                    <button type="button"
                        class="flex size-10 items-center justify-center rounded-xl border border-outline-variant font-medium transition-colors hover:bg-surface-container">13</button>
                    <button type="button"
                        class="flex size-10 items-center justify-center rounded-xl border border-outline-variant transition-colors hover:bg-surface-container">
                        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Load More -->
        <div class="py-10 text-center md:hidden">
            <button type="button"
                class="rounded-xl bg-primary/10 px-6 py-2 text-label-md font-bold text-primary transition-all active:scale-95">
                Tải thêm phiên luyện
            </button>
        </div>
    </section>
</x-layouts.app>
