@php
    /**
     * @var \Modules\StudyPlan\Models\StudyPlan $plan
     * @var array $weeks
     * @var array $topicProgress
     */
    $dayPercent = $plan->progressPercent();
    $circumference = 364.4;
    $dashOffset = $circumference * (1 - $dayPercent / 100);
@endphp

<x-layouts.app title="Chi tiết lộ trình">
    <div class="mx-auto max-w-container-max px-margin-desktop py-8" x-data="{ openWeek: {{ $openWeek }} }">
        <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <a href="{{ route('study-plan.index') }}"
                    class="mb-2 flex items-center gap-2 text-label-sm font-bold tracking-wider text-primary uppercase">
                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    Lộ trình học
                </a>
                <h2 class="font-headline-lg text-headline-lg text-on-surface">{{ $plan->name }}</h2>
                <p class="mt-1 text-body-sm text-on-surface-variant">
                    Ngày {{ $plan->currentDay() }}/{{ $plan->totalDays() }} ·
                    {{ $plan->strategy->label() }} · {{ $plan->daily_goal_questions }} câu/ngày
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('study-plan.schedule', $plan) }}"
                    class="flex items-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2 font-label-md text-primary transition-colors hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[20px]">calendar_month</span>
                    Lịch trình
                </a>
            </div>
        </div>

        @if (session('status'))
            <div
                class="mb-6 rounded-lg border border-primary-fixed-dim/30 bg-[#F0FDFA] p-4 text-body-md text-primary-container">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-error/30 bg-error-container/20 p-4 text-body-md text-error">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($plan->wasRecentlyReplanned())
            <div class="mb-6 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4">
                <span class="material-symbols-outlined text-amber-600">auto_mode</span>
                <p class="text-body-md text-amber-900">
                    <span class="font-bold">Kế hoạch vừa được điều chỉnh.</span>
                    Các ngày bạn lỡ đã được dồn vào những buổi sắp tới, ưu tiên chủ đề đang yếu.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-gutter lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-8">
                @forelse ($weeks as $week)
                    <div class="overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest shadow-sm">
                        <button type="button"
                            @click="openWeek = openWeek === {{ $week['index'] }} ? null : {{ $week['index'] }}"
                            class="group flex w-full cursor-pointer items-center justify-between p-5 text-left transition-colors hover:bg-surface-container-low/50">
                            <div class="flex items-center gap-5">
                                <div
                                    class="flex size-10 items-center justify-center rounded-lg border border-outline-variant bg-surface-container">
                                    <span class="material-symbols-outlined text-primary">calendar_today</span>
                                </div>
                                <div>
                                    <h3 class="font-headline-sm text-headline-sm text-on-surface">{{ $week['title'] }}</h3>
                                    <p class="text-label-sm tracking-tighter text-on-surface-variant uppercase">
                                        {{ $week['progress'] }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="material-symbols-outlined text-on-surface-variant transition-transform duration-200 group-hover:text-primary"
                                :class="openWeek === {{ $week['index'] }} ? 'rotate-180' : ''">expand_more</span>
                        </button>

                        <div x-show="openWeek === {{ $week['index'] }}" x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="border-t border-outline-variant px-4 pt-2 pb-5 sm:px-6">
                            <div class="relative ml-2 space-y-4 pt-2 pl-8 sm:ml-4 sm:pl-10">
                                <div class="absolute top-3 bottom-4 left-[15px] w-px bg-outline-variant sm:left-[19px]"></div>

                                @foreach ($week['days'] as $day)
                                    <div class="relative flex gap-4">
                                        <div class="absolute top-5 -left-8 flex size-8 items-center justify-center sm:-left-10">
                                            @if ($day['status'] === 'skipped')
                                                <span class="flex size-6 items-center justify-center rounded bg-red-500 text-white shadow-sm">
                                                    <span class="material-symbols-outlined text-[16px]"
                                                        style="font-variation-settings: 'FILL' 1;">remove</span>
                                                </span>
                                            @elseif ($day['status'] === 'incomplete')
                                                <span
                                                    class="flex size-6 items-center justify-center rounded-full bg-amber-400 text-white shadow-sm">
                                                    <span class="material-symbols-outlined text-[16px]"
                                                        style="font-variation-settings: 'FILL' 1;">priority_high</span>
                                                </span>
                                            @elseif ($day['status'] === 'done')
                                                <span
                                                    class="flex size-6 items-center justify-center rounded-full bg-[#137333] text-white shadow-sm">
                                                    <span class="material-symbols-outlined text-[16px]"
                                                        style="font-variation-settings: 'FILL' 1;">check</span>
                                                </span>
                                            @else
                                                <span class="size-5 rounded-full border-2 border-outline-variant bg-white"></span>
                                            @endif
                                        </div>

                                        <div
                                            class="flex-1 space-y-3 rounded-xl border bg-white p-4 shadow-sm {{ $day['isToday'] ? 'border-primary' : 'border-outline-variant' }}">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="font-bold text-on-surface">{{ $day['label'] }}</h4>
                                                @if ($day['isToday'])
                                                    <span
                                                        class="rounded-full bg-primary px-2.5 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">Hôm
                                                        nay</span>
                                                @endif
                                                @if ($day['statusLabel'])
                                                    <span
                                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wide uppercase {{ $day['statusClass'] }}">{{ $day['statusLabel'] }}</span>
                                                @endif
                                                <span
                                                    class="flex items-center gap-1.5 text-label-sm font-medium tracking-wide text-on-surface-variant uppercase">
                                                    <span class="material-symbols-outlined text-[18px]">timer</span>
                                                    {{ $day['done'] }}/{{ $day['target'] }} câu hỏi
                                                </span>
                                            </div>

                                            @foreach ($day['tasks'] as $task)
                                                <div
                                                    class="flex flex-col gap-3 rounded-lg bg-surface-container-lowest p-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <span
                                                            class="material-symbols-outlined text-[20px] text-on-surface-variant">{{ $task->type->icon() }}</span>
                                                        <div>
                                                            <p class="font-label-md text-on-surface">{{ $task->title() }}</p>
                                                            <p class="text-label-sm text-on-surface-variant">
                                                                {{ $task->type->label() }} · {{ $task->done }}/{{ $task->target }}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div class="flex shrink-0 items-center gap-2">
                                                        @if ($task->isDone())
                                                            <span class="flex items-center gap-1 text-label-md text-[#137333]">
                                                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                                                Hoàn thành
                                                            </span>
                                                            @if ($task->sessionId())
                                                                <a href="{{ route('study-plan.session.summary', [$plan, $task]) }}"
                                                                    class="flex items-center gap-1 rounded-lg border border-primary px-4 py-2 font-label-md font-semibold text-primary transition-colors hover:bg-primary/5">
                                                                    <span class="material-symbols-outlined text-[18px]">insights</span>
                                                                    Phân tích
                                                                </a>
                                                            @endif
                                                        @elseif (! $task->type->isSupported())
                                                            <span class="text-label-md text-on-surface-variant">Sắp có</span>
                                                        @else
                                                            <form method="POST"
                                                                action="{{ route('study-plan.tasks.start', [$plan, $task]) }}">
                                                                @csrf
                                                                <button type="submit"
                                                                    class="rounded-lg bg-primary px-5 py-2 text-center font-label-md font-semibold text-white transition-colors hover:bg-primary-container">
                                                                    {{ $task->isStarted() ? 'Tiếp tục' : 'Bắt đầu' }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-outline-variant bg-white p-6 text-body-md text-on-surface-variant">
                        Kế hoạch chưa có nhiệm vụ nào. Hãy tạo kế hoạch mới hoặc chờ hệ thống phân bổ lại lịch học.
                    </p>
                @endforelse
            </div>

            <div class="space-y-gutter lg:col-span-4">
                <div
                    class="flex flex-col items-center rounded-xl border border-outline-variant bg-surface-container-lowest p-6 text-center">
                    <h4 class="mb-6 font-headline-sm text-headline-sm">Ngày {{ $plan->currentDay() }}/{{ $plan->totalDays() }}
                    </h4>
                    <div class="relative mb-6 size-32">
                        <svg class="h-full w-full -rotate-90 transform">
                            <circle class="text-surface-container" cx="64" cy="64" fill="transparent" r="58"
                                stroke="currentColor" stroke-width="8"></circle>
                            <circle class="text-primary transition-all duration-1000" cx="64" cy="64" fill="transparent"
                                r="58" stroke="currentColor" stroke-dasharray="{{ $circumference }}"
                                stroke-dashoffset="{{ $dashOffset }}" stroke-width="8"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-[20px] font-bold text-primary">{{ $dayPercent }}%</span>
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase">Hoàn thành</span>
                        </div>
                    </div>
                </div>

                @if ($topicProgress !== [])
                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
                        <div class="border-b border-outline-variant bg-surface-container-low p-5">
                            <h4 class="text-label-md font-bold tracking-wider text-on-surface-variant uppercase">
                                Tiến độ lộ trình học
                            </h4>
                        </div>
                        <div class="space-y-6 p-5">
                            @foreach ($topicProgress as $topic)
                                <div>
                                    <div class="mb-2 flex justify-between">
                                        <span class="text-label-md font-medium">{{ $topic['name'] }}</span>
                                        <span class="text-label-sm text-on-surface-variant">{{ $topic['percent'] }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-container">
                                        <div class="h-full rounded-full bg-primary" style="width: {{ $topic['percent'] }}%">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
