@php
    /**
     * @var \Modules\StudyPlan\Models\StudyPlan $plan
     * @var \Illuminate\Support\Carbon $month
     * @var \Illuminate\Support\Carbon $selectedDate
     * @var array $cells
     * @var \Illuminate\Support\Collection $dayTasks
     */
    $weekdayLabels = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    $doneToday = $dayTasks->filter(fn ($task) => $task->isDone())->count();
@endphp

<x-layouts.app title="Lịch trình học tập">
    <div class="mx-auto w-full max-w-container-max flex-1 p-4 md:p-8"
        x-data="{ rescheduleTaskId: null, rescheduleDate: '{{ now()->addDay()->toDateString() }}' }">
        <!-- Header -->
        <div
            class="mb-8 flex flex-col justify-between gap-6 rounded-xl border border-outline-variant bg-white p-6 shadow-sm md:flex-row md:items-end">
            <div class="flex-1">
                <div class="mb-2 flex flex-wrap items-center gap-3">
                    <span class="rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm text-primary">
                        {{ $plan->strategy->label() }}
                    </span>
                    <span class="flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-[16px]">timer</span>
                        Còn {{ $plan->daysUntilExam() }} ngày
                    </span>
                </div>
                <h2 class="mb-4 font-headline-lg text-headline-lg text-on-surface">{{ $plan->name }}</h2>
                <div class="max-w-xl">
                    <div class="mb-2 flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                        <span>Tiến độ tổng thể</span>
                        <span class="font-bold text-primary">{{ $plan->progressPercent() }}%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high">
                        <div class="h-full rounded-full bg-primary transition-all duration-500"
                            style="width: {{ $plan->progressPercent() }}%"></div>
                    </div>
                </div>
            </div>
            <a href="{{ route('study-plan.detail', $plan) }}"
                class="flex h-fit items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-6 py-2.5 font-label-md text-label-md text-primary transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]">route</span>
                Xem lộ trình
            </a>
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

        <div class="grid grid-cols-1 gap-gutter lg:grid-cols-12">
            <!-- Calendar -->
            <div class="flex flex-col overflow-hidden rounded-xl border border-outline-variant bg-white lg:col-span-8">
                <div
                    class="flex items-center justify-between border-b border-outline-variant bg-surface-container-lowest p-4 md:p-6">
                    <div class="flex items-center gap-4">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">
                            {{ $month->translatedFormat('\T\h\á\n\g n, Y') }}
                        </h3>
                        <div class="flex gap-1">
                            <a href="{{ route('study-plan.schedule', [$plan, 'month' => $month->copy()->subMonth()->toDateString()]) }}"
                                class="rounded p-1 text-on-surface-variant hover:bg-surface-container-low">
                                <span class="material-symbols-outlined">chevron_left</span>
                            </a>
                            <a href="{{ route('study-plan.schedule', [$plan, 'month' => $month->copy()->addMonth()->toDateString()]) }}"
                                class="rounded p-1 text-on-surface-variant hover:bg-surface-container-low">
                                <span class="material-symbols-outlined">chevron_right</span>
                            </a>
                        </div>
                    </div>
                    <a href="{{ route('study-plan.schedule', $plan) }}"
                        class="rounded-lg border border-outline-variant px-4 py-2 font-label-sm text-label-sm hover:bg-surface-container-low">
                        Hôm nay
                    </a>
                </div>

                <div class="flex-1 bg-surface-container-lowest p-4 md:p-6">
                    <div class="grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-outline-variant bg-outline-variant">
                        @foreach ($weekdayLabels as $label)
                            <div
                                @class([
                                    'bg-surface-container-low py-3 text-center font-label-sm text-label-sm',
                                    'text-error' => $label === 'CN',
                                    'text-on-surface-variant' => $label !== 'CN',
                                ])>{{ $label }}</div>
                        @endforeach

                        @foreach ($cells as $cell)
                            @php
                                $base = 'min-h-[100px] p-2 font-body-sm text-body-sm flex flex-col gap-1 transition-colors';
                                $cellClass = match (true) {
                                    ! $cell['inMonth'] => "bg-white text-surface-dim {$base}",
                                    $cell['type'] === 'today' => "bg-primary-container/5 text-on-surface {$base} border-2 border-primary",
                                    $cell['type'] === 'missed' => "bg-error-container/20 text-on-surface {$base}",
                                    $cell['type'] === 'completed' => "bg-[#e6f4ea] text-on-surface {$base}",
                                    default => "bg-white text-on-surface {$base}",
                                };
                                $eventClass = match ($cell['type']) {
                                    'missed' => 'bg-error/10 text-error',
                                    'today' => 'bg-primary/10 text-primary',
                                    default => 'bg-surface-variant/50 text-on-surface-variant',
                                };
                            @endphp
                            <a href="{{ route('study-plan.schedule', [$plan, 'month' => $month->toDateString(), 'date' => $cell['date']->toDateString()]) }}"
                                @class([$cellClass, 'ring-2 ring-inset ring-primary/40' => $cell['isSelected']])>
                                @if ($cell['type'] === 'today')
                                    <span
                                        class="mb-1 flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white shadow-sm">{{ $cell['day'] }}</span>
                                @elseif ($cell['type'] === 'missed')
                                    <span class="font-bold text-error">{{ $cell['day'] }}</span>
                                @elseif ($cell['type'] === 'completed')
                                    <span class="font-bold text-[#137333]">{{ $cell['day'] }}</span>
                                @else
                                    <span>{{ $cell['day'] }}</span>
                                @endif

                                @foreach ($cell['events'] as $event)
                                    <span class="truncate rounded px-1.5 py-0.5 text-[10px] leading-tight font-medium {{ $eventClass }}">
                                        {{ $event }}
                                    </span>
                                @endforeach
                            </a>
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

            <!-- Day panel -->
            <div class="flex flex-col gap-6 lg:col-span-4">
                <div
                    class="sticky top-[88px] flex h-full flex-col overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                    <div class="border-b border-outline-variant bg-surface-container-lowest p-5">
                        <h3 class="mb-1 font-headline-sm text-headline-sm text-primary">
                            Nhiệm vụ: {{ $selectedDate->translatedFormat('l, d/m') }}
                        </h3>
                        <p class="flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[16px]">pie_chart</span>
                            Tiến độ ngày: {{ $doneToday }}/{{ $dayTasks->count() }} hoàn thành
                        </p>
                    </div>
                    <div class="flex-1 space-y-4 overflow-y-auto bg-surface-bright p-5">
                        @forelse ($dayTasks as $task)
                            <div class="rounded-lg border border-outline-variant bg-white p-4 transition-shadow hover:shadow-md">
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="rounded bg-secondary-container/10 px-2 py-0.5 text-xs font-semibold text-secondary-container">
                                        {{ $task->type->label() }}
                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[14px]">schedule</span>
                                        {{ $task->estimatedMinutes() }}p
                                    </span>
                                </div>
                                <h4 class="mb-2 font-label-md text-label-md text-on-surface">{{ $task->title() }}</h4>
                                <p class="mb-3 text-label-sm text-on-surface-variant">{{ $task->done }}/{{ $task->target }} câu ·
                                    {{ $task->status->label() }}</p>

                                <div class="flex flex-wrap gap-2">
                                    @if ($task->isDone())
                                        <span class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-[#e6f4ea] py-2 font-label-md text-[#137333]">
                                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                            Hoàn thành
                                        </span>
                                        @if ($task->sessionId())
                                            <a href="{{ route('study-plan.session.summary', [$plan, $task]) }}"
                                                class="flex items-center justify-center gap-1 rounded-lg border border-primary px-3 py-2 font-label-md text-primary transition-colors hover:bg-primary/5">
                                                <span class="material-symbols-outlined text-[18px]">insights</span>
                                                Phân tích
                                            </a>
                                        @endif
                                    @elseif ($task->status === \Modules\StudyPlan\Enums\TaskStatus::Skipped)
                                        <span class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-error/10 py-2 font-label-md text-error">
                                            <span class="material-symbols-outlined text-[18px]">remove_circle</span>
                                            Bỏ qua
                                        </span>
                                        <button type="button"
                                            @click="rescheduleTaskId = {{ $task->id }}; rescheduleDate = '{{ max($task->date, now())->toDateString() }}'"
                                            class="rounded-lg border border-outline-variant px-3 py-2 font-label-md text-label-md text-primary transition-colors hover:bg-surface-container-low">
                                            Dời lịch
                                        </button>
                                    @else
                                        @if ($task->type->isSupported())
                                            <form method="POST" action="{{ route('study-plan.tasks.start', [$plan, $task]) }}"
                                                class="flex-1">
                                                @csrf
                                                <button type="submit"
                                                    class="w-full rounded-lg bg-primary py-2 font-label-md text-label-md text-white transition-colors hover:bg-primary/90">
                                                    {{ $task->isStarted() ? 'Tiếp tục' : 'Bắt đầu' }}
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button"
                                            @click="rescheduleTaskId = {{ $task->id }}; rescheduleDate = '{{ max($task->date, now())->toDateString() }}'"
                                            class="rounded-lg border border-outline-variant px-3 py-2 font-label-md text-label-md text-primary transition-colors hover:bg-surface-container-low">
                                            Dời lịch
                                        </button>
                                    @endif
                                </div>

                                <!-- Reschedule -->
                                <form method="POST" action="{{ route('study-plan.tasks.reschedule', [$plan, $task]) }}"
                                    x-show="rescheduleTaskId === {{ $task->id }}" x-cloak
                                    class="mt-3 space-y-2 rounded-lg border border-outline-variant bg-surface-container-lowest p-3">
                                    @csrf
                                    <label class="block font-label-sm text-label-sm text-on-surface-variant">Chọn ngày mới</label>
                                    <input type="date" name="date" x-model="rescheduleDate"
                                        min="{{ now()->toDateString() }}" max="{{ $plan->exam_target_date->toDateString() }}"
                                        class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="rescheduleTaskId = null"
                                            class="rounded-lg px-3 py-1.5 font-label-sm text-on-surface-variant hover:bg-surface-container">Hủy</button>
                                        <button type="submit"
                                            class="rounded-lg bg-primary px-4 py-1.5 font-label-sm text-white hover:opacity-90">Dời</button>
                                    </div>
                                </form>
                            </div>
                        @empty
                            <p class="text-body-sm text-on-surface-variant">Không có nhiệm vụ nào trong ngày này.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
