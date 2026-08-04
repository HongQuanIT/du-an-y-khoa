@php
    /**
     * @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \Modules\StudyPlan\Models\StudyPlan> $plans
     * @var \Modules\StudyPlan\Models\StudyPlan|null $plan  active / most recent
     */
@endphp

<x-layouts.app title="Kế hoạch học tập">
    <div class="mx-auto max-w-[1200px] space-y-8 p-8">
        @if (session('status'))
            <div class="rounded-lg border border-primary-fixed-dim/30 bg-[#F0FDFA] p-4 text-body-md text-primary-container">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="space-y-1">
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Kế hoạch học tập</h1>
                <p class="text-body-sm text-on-surface-variant">
                    @if ($plans->isEmpty())
                        Chưa có kế hoạch nào — tạo lộ trình theo kỳ thi mục tiêu của bạn.
                    @else
                        Bạn đang có {{ $plans->total() }} kế hoạch.
                    @endif
                </p>
            </div>
            <a href="{{ route('study-plan.create') }}"
                class="flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2 font-label-md text-white shadow-sm transition-all hover:opacity-90">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Tạo kế hoạch mới
            </a>
        </div>

        @if ($plans->isEmpty())
            <div
                class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest p-12 text-center">
                <span class="material-symbols-outlined text-[48px] text-primary">route</span>
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Bạn chưa có kế hoạch học tập</h2>
                <p class="max-w-md text-body-md text-on-surface-variant">
                    Chọn kỳ thi mục tiêu, phạm vi ôn tập và cường độ — hệ thống sẽ chia khối lượng thành nhiệm vụ mỗi
                    ngày cho bạn.
                </p>
                <a href="{{ route('study-plan.create') }}"
                    class="mt-2 flex items-center gap-2 rounded-lg bg-primary-container px-6 py-3 font-label-md text-white shadow-sm transition-all hover:opacity-90">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tạo kế hoạch đầu tiên
                </a>
            </div>
        @else
            <!-- All plans -->
            <div class="space-y-4">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Các lộ trình của bạn</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ($plans as $listed)
                        @php
                            $percent = $listed->progressPercent();
                            $statusClass = match ($listed->status->value) {
                                'active' => 'bg-primary/10 text-primary',
                                'paused' => 'bg-amber-50 text-amber-700',
                                'completed' => 'bg-[#e6f4ea] text-[#137333]',
                                default => 'bg-surface-container text-on-surface-variant',
                            };
                        @endphp
                        <div
                            class="flex flex-col rounded-xl border bg-white p-5 transition-shadow hover:shadow-md {{ $listed->isActive() ? 'border-primary' : 'border-outline-variant' }}">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <h3 class="truncate font-bold text-on-surface">{{ $listed->name }}</h3>
                                    <p class="text-body-sm text-on-surface-variant">
                                        Thi {{ $listed->exam_target_date->format('d/m/Y') }} ·
                                        Còn {{ $listed->daysUntilExam() }} ngày ·
                                        {{ $listed->daily_goal_questions }} câu/ngày
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold tracking-wide uppercase {{ $statusClass }}">
                                    {{ $listed->status->label() }}
                                </span>
                            </div>

                            <div class="mb-4">
                                <div class="mb-1 flex justify-between text-label-sm text-on-surface-variant">
                                    <span>Tiến độ</span>
                                    <span class="font-semibold text-primary">{{ $percent }}%</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-container">
                                    <div class="h-full rounded-full bg-primary" style="width: {{ $percent }}%"></div>
                                </div>
                                <p class="mt-1 text-label-sm text-on-surface-variant">
                                    {{ number_format($listed->questionsDone()) }} /
                                    {{ number_format($listed->questionsTarget()) }} câu
                                </p>
                            </div>

                            <div class="mt-auto flex flex-wrap gap-2">
                                <a href="{{ route('study-plan.detail', $listed) }}"
                                    class="flex flex-1 items-center justify-center gap-1 rounded-lg border border-primary px-3 py-2 text-center font-label-md text-primary transition-colors hover:bg-primary/5">
                                    {{ $listed->isActive() ? 'Tiếp tục' : 'Xem chi tiết' }}
                                </a>
                                <a href="{{ route('study-plan.schedule', $listed) }}"
                                    class="flex size-10 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant transition-colors hover:bg-surface-container-low"
                                    title="Lịch trình">
                                    <span class="material-symbols-outlined text-[20px]">calendar_month</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($plans->hasPages())
                    <div class="border-t border-outline-variant pt-4">
                        {{ $plans->onEachSide(1)->links('studyplan::pagination') }}
                    </div>
                @endif

            </div>

            @if ($plan !== null && $plan->isActive())
                @if ($plan->wasRecentlyReplanned())
                    <div
                        class="flex flex-col items-start gap-4 rounded-lg border border-primary-fixed-dim/30 bg-[#F0FDFA] p-4 sm:flex-row sm:items-center">
                        <span class="material-symbols-outlined text-primary-container">info</span>
                        <p class="flex-1 text-body-md text-primary-container">
                            <span class="font-bold">Đã điều chỉnh:</span> hệ thống vừa phân bổ lại các ngày bạn lỡ và ưu
                            tiên chủ đề đang yếu trên kế hoạch đang học.
                        </p>
                        <a href="{{ route('study-plan.detail', $plan) }}"
                            class="font-label-md text-primary-container hover:underline">Chi tiết</a>
                    </div>
                @endif

                <div class="space-y-6">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="mb-1 font-headline-sm text-headline-sm text-on-surface">Công việc hôm nay</h2>
                            <p class="text-body-sm text-on-surface-variant">
                                {{ now()->translatedFormat('l, j \t\h\á\n\g n') }} · {{ $plan->name }}
                            </p>
                        </div>
                        <a href="{{ route('study-plan.detail', $plan) }}"
                            class="flex items-center gap-1 font-label-md text-primary hover:underline">
                            Xem toàn bộ lộ trình
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    </div>

                    @if ($todayTasks->isEmpty())
                        <p
                            class="rounded-xl border border-outline-variant bg-white p-6 text-body-md text-on-surface-variant">
                            Hôm nay là ngày nghỉ theo lịch của bạn. Có thể ôn thêm ở
                            <a href="{{ route('qbank.index') }}" class="text-primary hover:underline">Ngân hàng câu
                                hỏi</a>.
                        </p>
                    @else
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            @foreach ($todayTasks as $task)
                                @include('studyplan::partials.task-card', ['plan' => $plan, 'task' => $task])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
