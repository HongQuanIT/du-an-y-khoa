@php
    /**
     * @var \Modules\StudyPlan\Models\StudyPlan $plan
     * @var \Modules\StudyPlan\Models\StudyPlanTask $task
     */
    $iconWrap = match ($task->type->value) {
        'questions' => 'bg-primary/10 text-primary',
        'review' => 'bg-error-container/20 text-error',
        'flashcards' => 'bg-tertiary/10 text-tertiary',
        default => 'bg-secondary/10 text-secondary',
    };
@endphp

<div class="flex h-full flex-col rounded-xl border border-outline-variant bg-white p-6 transition-shadow hover:shadow-md">
    <div class="mb-4 flex items-start justify-between">
        <div class="rounded-lg p-2 {{ $iconWrap }}">
            <span class="material-symbols-outlined">{{ $task->type->icon() }}</span>
        </div>
        <span class="text-label-sm text-on-surface-variant">
            @if ($task->isMissed())
                Lỡ ngày {{ $task->date->format('d/m') }}
            @else
                {{ $task->done }}/{{ $task->target }} câu
            @endif
        </span>
    </div>

    <h3 class="mb-1 font-bold text-on-surface">{{ $task->title() }}</h3>
    <p class="mb-4 flex-1 text-body-sm text-on-surface-variant">
        {{ $task->type->label() }} · khoảng {{ $task->estimatedMinutes() }} phút
    </p>

    <div class="mb-4 h-1.5 w-full overflow-hidden rounded-full bg-surface-container">
        <div class="h-full rounded-full bg-primary" style="width: {{ $task->percent() }}%"></div>
    </div>

    @if ($task->isDone())
        <div class="flex gap-2">
            <span
                class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-[#e6f4ea] py-2 font-label-md text-[#137333]">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                Đã hoàn thành
            </span>
            @if ($task->sessionId())
                <a href="{{ route('study-plan.session.summary', [$plan, $task]) }}"
                    class="flex items-center justify-center gap-1 rounded-lg border border-primary px-4 py-2 font-label-md text-primary transition-colors hover:bg-primary/5">
                    <span class="material-symbols-outlined text-[18px]">insights</span>
                    Phân tích
                </a>
            @endif
        </div>
    @elseif ($task->status === \Modules\StudyPlan\Enums\TaskStatus::Skipped || $task->isMissed())
        <span class="flex w-full items-center justify-center gap-2 rounded-lg bg-error/10 py-2 font-label-md text-error">
            <span class="material-symbols-outlined text-[18px]">remove_circle</span>
            Bỏ qua
        </span>
    @elseif (! $task->type->isSupported())
        <span class="block w-full rounded-lg border border-outline-variant py-2 text-center font-label-md text-on-surface-variant">
            Sắp có
        </span>
    @else
        <form method="POST" action="{{ route('study-plan.tasks.start', [$plan, $task]) }}">
            @csrf
            <button type="submit"
                class="block w-full rounded-lg border border-primary py-2 text-center font-label-md text-primary transition-colors hover:bg-primary/5">
                {{ $task->isStarted() ? 'Tiếp tục' : 'Bắt đầu' }}
            </button>
        </form>
    @endif
</div>
