@php
    /**
     * @var \Modules\StudyPlan\Models\StudyPlan $plan
     * @var \Modules\StudyPlan\Models\StudyPlanTask $task
     * @var list<array<string, mixed>> $topics
     * @var list<array{label: string, height: int, rate: int}> $chartBars
     */
    $minutes = intdiv(max(0, $timeSpentSeconds), 60);
    $seconds = max(0, $timeSpentSeconds) % 60;
    $timeLabel = $minutes > 0
        ? sprintf('%d phút %02d giây', $minutes, $seconds)
        : sprintf('%d giây', $seconds);
    $summaryConfig = $summaryConfig ?? [
        'page_title' => 'Phân tích kết quả',
        'heading' => 'Phân tích kết quả',
        'subtitle' => $task->title() . ' · hoàn thành ' . $total . ' câu hỏi trong phiên này.',
        'breadcrumbs' => [
            ['label' => 'Kế hoạch học tập', 'url' => route('study-plan.index')],
            ['label' => $plan->name, 'url' => route('study-plan.detail', $plan)],
            ['label' => 'Phân tích', 'url' => null],
        ],
        'review_url' => route('study-plan.session.review', [$plan, $task]),
        'back_url' => route('study-plan.detail', $plan),
        'back_label' => 'Quay lại kế hoạch',
        'back_icon' => 'route',
        'progress_label' => $task->done . '/' . $task->target . ' mục tiêu nhiệm vụ',
        'context_message' => 'Tiến độ kế hoạch đã được cập nhật sau nhiệm vụ này.',
    ];
    $reviewUrl = $summaryConfig['review_url'];
    $detailUrl = $summaryConfig['back_url'];
    $chartMinWidth = max(280, count($chartBars) * 104);
@endphp

<x-layouts.app :title="$summaryConfig['page_title']">
    <div class="mx-auto max-w-6xl space-y-8 p-4 md:p-8">
        <div>
            <nav class="mb-2 flex flex-wrap gap-2 text-xs text-on-surface-variant">
                @foreach ($summaryConfig['breadcrumbs'] as $breadcrumb)
                    @if (! $loop->first)
                        <span>/</span>
                    @endif
                    @if ($breadcrumb['url'])
                        <a href="{{ $breadcrumb['url'] }}" class="hover:text-primary">{{ $breadcrumb['label'] }}</a>
                    @else
                        <span class="font-medium text-primary">{{ $breadcrumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
            <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $summaryConfig['heading'] }}</h1>
            <p class="mt-1 font-body-md text-on-surface-variant">
                {{ $summaryConfig['subtitle'] }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div
                class="flex flex-col items-center gap-8 rounded-2xl border border-outline-variant bg-white p-6 shadow-sm md:flex-row md:p-8 lg:col-span-8">
                <div class="donut-chart size-48 shrink-0" style="background: {{ $donutStyle }}">
                    <div class="donut-inner">
                        <div class="text-center">
                            <span class="block text-4xl font-extrabold text-primary">{{ $accuracy }}%</span>
                            <span class="text-xs font-semibold text-on-surface-variant">ĐÚNG</span>
                        </div>
                    </div>
                </div>
                <div class="w-full flex-1">
                    <div class="mb-6 grid grid-cols-3 gap-4">
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Đúng</p>
                            <p class="text-2xl font-bold text-[#16A34A]">{{ $correctCount }}</p>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Sai</p>
                            <p class="text-2xl font-bold text-error">{{ $wrongCount }}</p>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Bỏ qua</p>
                            <p class="text-2xl font-bold text-outline">{{ $skippedCount }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 border-t border-outline-variant pt-6">
                        <div class="flex items-center gap-2 rounded-lg bg-surface-container px-3 py-1.5">
                            <span class="material-symbols-outlined text-sm text-on-surface-variant">timer</span>
                            <span class="text-sm font-medium">{{ $timeLabel }}</span>
                        </div>
                        @if ($flaggedCount > 0)
                            <div class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-1.5 text-amber-700">
                                <span class="material-symbols-outlined text-sm"
                                    style="font-variation-settings: 'FILL' 1;">flag</span>
                                <span class="text-sm font-medium">{{ $flaggedCount }} câu gắn cờ</span>
                            </div>
                        @endif
                        <div class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary-container/10 px-3 py-1.5">
                            <span class="material-symbols-outlined text-sm text-primary">task_alt</span>
                            <span class="text-sm font-bold text-primary">
                                {{ $summaryConfig['progress_label'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm lg:col-span-4">
                <h2 class="mb-4 font-headline-sm text-headline-sm">Tóm tắt nhanh</h2>
                <ul class="space-y-4 text-body-sm text-on-surface-variant">
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary">insights</span>
                        <span>
                            Bạn trả lời đúng <strong class="text-on-surface">{{ $accuracy }}%</strong> số câu trong phiên.
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined {{ $wrongCount > 0 ? 'text-error' : 'text-[#16A34A]' }}">
                            {{ $wrongCount > 0 ? 'priority_high' : 'verified' }}
                        </span>
                        <span>
                            @if ($wrongCount > 0)
                                Có <strong class="text-on-surface">{{ $wrongCount }}</strong> câu sai —
                                nên xem lại giải thích.
                            @else
                                Không có câu sai trong phiên này.
                            @endif
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-on-surface-variant">route</span>
                        <span>{{ $summaryConfig['context_message'] }}</span>
                    </li>
                </ul>
            </div>

            @if ($chartBars !== [])
                <div class="rounded-2xl border border-outline-variant bg-white p-4 shadow-sm sm:p-6 lg:col-span-12">
                    <div class="mb-6 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                        <h2 class="font-headline-sm text-headline-sm">Tỷ lệ đúng theo chủ đề</h2>
                        <div class="flex items-center gap-2">
                            <div class="size-3 rounded bg-primary"></div>
                            <span class="text-xs text-on-surface-variant">Tỷ lệ đúng (%)</span>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
                        <div class="overflow-x-auto overscroll-x-contain" data-testid="topic-accuracy-chart-scroll">
                            <div class="grid h-72 w-full gap-4 px-4 pt-4"
                                style="min-width: {{ $chartMinWidth }}px; grid-template-columns: repeat({{ count($chartBars) }}, minmax(80px, 1fr));">
                                @foreach ($chartBars as $bar)
                                    <div class="flex min-w-0 flex-col items-center">
                                        <span class="flex h-7 items-start text-xs font-bold text-primary">
                                            {{ $bar['rate'] }}%
                                        </span>
                                        <div class="flex min-h-0 w-full flex-1 items-end justify-center border-b border-outline-variant">
                                            <div class="w-full max-w-[64px] rounded-t-lg bg-primary transition-all duration-500 hover:brightness-110"
                                                style="height: {{ $bar['height'] }}%"></div>
                                        </div>
                                        <span class="flex h-12 w-full items-start justify-center overflow-hidden px-1 pt-2 text-center text-[10px] leading-tight font-medium break-words text-on-surface-variant md:text-xs"
                                            title="{{ $bar['label'] }}">
                                            {{ $bar['label'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm lg:col-span-12">
                <div class="flex items-center justify-between border-b border-outline-variant p-6">
                    <h2 class="font-headline-sm text-headline-sm">Phân tích chi tiết chủ đề</h2>
                    <a href="{{ $reviewUrl }}" class="flex items-center gap-1 text-sm font-bold text-primary hover:underline">
                        Xem từng câu
                        <span class="material-symbols-outlined text-base">chevron_right</span>
                    </a>
                </div>

                @if ($topics === [])
                    <p class="p-6 text-body-sm text-on-surface-variant">Chưa có dữ liệu chủ đề để phân tích.</p>
                @else
                    <div class="hidden md:block">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-surface-container-low text-on-surface-variant">
                                    <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Chủ đề</th>
                                    <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Tỷ lệ đúng</th>
                                    <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Số câu</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold tracking-wider uppercase">Đánh giá</th>
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
                                                <span class="text-sm font-bold {{ $topic['rateClass'] }}">
                                                    {{ $topic['rate'] }}%
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">{{ $topic['count'] }}</td>
                                        <td class="px-6 py-4 text-right">
                                            @if ($topic['reviewUrl'])
                                                <a href="{{ $topic['reviewUrl'] }}"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-error px-3 py-1.5 text-sm font-bold text-white transition-opacity hover:opacity-90">
                                                    {{ $topic['actionLabel'] }}
                                                    <span class="material-symbols-outlined text-base">chevron_right</span>
                                                </a>
                                            @else
                                                <span class="inline-flex rounded-lg px-3 py-1.5 text-sm font-bold text-primary">
                                                    {{ $topic['actionLabel'] }}
                                                </span>
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
                                    <span class="text-xs font-bold {{ $topic['rateClass'] }}">{{ $topic['rate'] }}%</span>
                                </div>
                                @if ($topic['reviewUrl'])
                                    <a href="{{ $topic['reviewUrl'] }}"
                                        class="inline-flex items-center gap-1 rounded-lg bg-error px-3 py-1.5 text-sm font-bold text-white transition-opacity hover:opacity-90">
                                        {{ $topic['actionLabel'] }}
                                        <span class="material-symbols-outlined text-base">chevron_right</span>
                                    </a>
                                @else
                                    <span
                                        class="inline-flex rounded-lg border border-outline-variant px-3 py-1.5 text-sm font-bold text-primary">
                                        {{ $topic['actionLabel'] }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center">
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ $reviewUrl }}"
                    class="flex items-center justify-center gap-2 rounded-xl bg-primary-container px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95">
                    <span class="material-symbols-outlined">rate_review</span>
                    Xem lại từng câu
                    @if ($wrongCount > 0)
                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $wrongCount }} sai</span>
                    @endif
                </a>
                <a href="{{ $detailUrl }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-outline-variant bg-white px-6 py-3 font-bold text-on-surface-variant transition-all hover:bg-surface-container-low active:scale-95">
                    <span class="material-symbols-outlined">{{ $summaryConfig['back_icon'] }}</span>
                    {{ $summaryConfig['back_label'] }}
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
