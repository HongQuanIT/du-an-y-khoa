<x-layouts.teach
    title="Lớp của tôi"
    description="Quản lý lớp chữa đề, lịch phát trực tiếp và học viên trên cổng giảng viên.">
    <header class="mb-6 rounded-xl border border-outline-variant bg-surface px-5 py-6 md:px-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-wide text-primary">Quản lý giảng dạy</p>
                <h2 class="mt-1 font-headline-sm text-headline-sm font-bold text-on-surface">Danh sách lớp học</h2>
                <p class="mt-2 text-sm leading-6 text-on-surface-variant">
                    Theo dõi lớp chữa feedback, lớp chữa đề thi và các buổi phát trực tiếp bạn đang phụ trách.
                </p>
            </div>

            <a href="{{ route('teach.classes.create') }}"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-on-primary transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">add</span>
                Tạo lớp mới
            </a>
        </div>
    </header>

    @if (session('status'))
        <div role="status" aria-live="polite"
            class="mb-6 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary">
            <span class="material-symbols-outlined mt-0.5 text-[20px]" aria-hidden="true">check_circle</span>
            <p>{{ session('status') }}</p>
        </div>
    @endif

    <section aria-labelledby="classroom-overview-title" class="mb-8">
        <h2 id="classroom-overview-title" class="sr-only">Tổng quan lớp học</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-outline-variant bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <dl>
                        <dt class="text-sm font-medium text-on-surface-variant">Tổng số lớp</dt>
                        <dd class="mt-2 text-3xl font-bold tabular-nums text-on-surface">{{ $stats['total'] }}</dd>
                    </dl>
                    <span class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                        <span class="material-symbols-outlined">school</span>
                    </span>
                </div>
                <p class="mt-2 text-xs text-on-surface-variant">Tất cả lớp bạn làm giảng viên chính hoặc đồng giảng viên</p>
            </div>

            <div class="rounded-xl border border-outline-variant bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <dl>
                        <dt class="text-sm font-medium text-on-surface-variant">Đang phát trực tiếp</dt>
                        <dd class="mt-2 text-3xl font-bold tabular-nums text-on-surface">{{ $stats['live'] }}</dd>
                    </dl>
                    <span class="flex size-10 items-center justify-center rounded-lg bg-error/10 text-error" aria-hidden="true">
                        <span class="material-symbols-outlined">podcasts</span>
                    </span>
                </div>
                <p class="mt-2 text-xs text-on-surface-variant">Các buổi trực tiếp đang diễn ra</p>
            </div>

            <div class="rounded-xl border border-outline-variant bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <dl>
                        <dt class="text-sm font-medium text-on-surface-variant">Sắp diễn ra</dt>
                        <dd class="mt-2 text-3xl font-bold tabular-nums text-on-surface">{{ $stats['upcoming'] }}</dd>
                    </dl>
                    <span class="flex size-10 items-center justify-center rounded-lg bg-tertiary/10 text-tertiary" aria-hidden="true">
                        <span class="material-symbols-outlined">event</span>
                    </span>
                </div>
                <p class="mt-2 text-xs text-on-surface-variant">Lớp đã có lịch trực tiếp tiếp theo</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="classroom-list-title">
        <div class="mb-4">
            <h2 id="classroom-list-title" class="font-title-lg text-title-lg font-bold text-on-surface">Các lớp đang quản lý</h2>
            @if (! $classrooms->isEmpty())
                <p class="mt-1 text-sm text-on-surface-variant">
                    Hiển thị {{ $classrooms->firstItem() }}–{{ $classrooms->lastItem() }} trong {{ $classrooms->total() }} lớp.
                </p>
            @endif
        </div>

        @if ($classrooms->isEmpty())
            <div class="rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-14 text-center">
                <span class="material-symbols-outlined text-[44px] text-on-surface-variant" aria-hidden="true">cast_for_education</span>
                <h3 class="mt-3 font-title-md text-title-md font-semibold text-on-surface">Bạn chưa có lớp học</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-on-surface-variant">
                    Tạo lớp chữa feedback hoặc chữa đề thi để lên lịch và bắt đầu buổi giảng trực tiếp.
                </p>
                <a href="{{ route('teach.classes.create') }}"
                    class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-on-primary hover:opacity-90">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">add</span>
                    Tạo lớp đầu tiên
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach ($classrooms as $classroom)
                    @php
                        $isLive = $classroom->liveSession !== null;
                        $upcoming = $classroom->upcomingSession;
                        $statusLabel = $isLive
                            ? 'Đang live'
                            : ($upcoming ? 'Sắp live' : $classroom->status->label());
                        $statusClasses = $isLive
                            ? 'bg-error/10 text-error'
                            : ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval
                                ? 'bg-tertiary/15 text-tertiary'
                                : 'bg-surface-container-high text-on-surface-variant');
                        $nextLiveAt = $upcoming?->scheduled_at?->timezone(config('app.timezone'));
                    @endphp

                    <article class="flex min-w-0 flex-col rounded-xl border border-outline-variant bg-surface p-5 transition-colors hover:border-primary/40">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                        {{ $classroom->purpose->label() }}
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                        @if ($isLive)
                                            <span class="mr-1 inline-block size-1.5 rounded-full bg-current align-middle" aria-hidden="true"></span>
                                        @endif
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                <h3 class="text-lg font-bold leading-7 text-on-surface">
                                    <a href="{{ route('teach.classes.show', $classroom) }}"
                                        class="rounded-sm hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                        {{ $classroom->title }}
                                    </a>
                                </h3>
                            </div>

                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-surface-container-low text-on-surface-variant" aria-hidden="true">
                                <span class="material-symbols-outlined">{{ $classroom->purpose->coverIcon() }}</span>
                            </span>
                        </div>

                        <p class="mt-3 line-clamp-2 min-h-10 text-sm leading-5 text-on-surface-variant">
                            {{ $classroom->description ?: 'Lớp học chưa có mô tả.' }}
                        </p>

                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-y border-outline-variant py-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-on-surface-variant">Thành viên</dt>
                                <dd class="mt-1 font-semibold text-on-surface">
                                    {{ $classroom->active_members_count }}@if ($classroom->max_members)/{{ $classroom->max_members }}@endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-on-surface-variant">Chế độ tham gia</dt>
                                <dd class="mt-1 font-semibold text-on-surface">{{ $classroom->visibility->label() }}</dd>
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <dt class="text-xs text-on-surface-variant">Lịch tiếp theo</dt>
                                <dd class="mt-1 font-semibold text-on-surface">
                                    @if ($nextLiveAt)
                                        <time datetime="{{ $nextLiveAt->toIso8601String() }}">{{ $nextLiveAt->format('d/m/Y H:i') }}</time>
                                    @else
                                        Chưa lên lịch
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-on-surface-variant">
                                Cập nhật <time datetime="{{ $classroom->updated_at->toIso8601String() }}">{{ $classroom->updated_at->diffForHumans() }}</time>
                            </p>
                            <a href="{{ route('teach.classes.show', $classroom) }}"
                                class="inline-flex min-h-10 items-center gap-1 rounded-lg border border-outline-variant px-3 py-2 text-sm font-semibold text-on-surface transition-colors hover:border-primary hover:bg-primary/5 hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                Quản lý lớp
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($classrooms->hasPages())
                <nav class="mt-6" aria-label="Phân trang danh sách lớp">
                    {{ $classrooms->onEachSide(1)->links() }}
                </nav>
            @endif
        @endif
    </section>
</x-layouts.teach>
