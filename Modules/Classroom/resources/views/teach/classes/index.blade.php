<x-layouts.teach title="Lớp của tôi">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="font-headline-sm text-headline-sm text-on-surface">Lớp của tôi</h2>
            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                Lớp chữa đề bạn host hoặc cohost trên portal giảng viên.
            </p>
        </div>
        <a href="{{ route('teach.classes.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tạo lớp
        </a>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 font-body-sm text-body-sm text-primary">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng lớp" :value="(string) $stats['total']" hint="Feedback + chữa exam" icon="school" />
        <x-admin.kpi-card label="Đang live" :value="(string) $stats['live']" hint="Buổi đang phát" icon="podcasts" />
        <x-admin.kpi-card label="Sắp diễn ra" :value="(string) $stats['upcoming']" hint="Có lịch live sắp tới" icon="event" />
    </div>

    @if ($classrooms->isEmpty())
        <div class="rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-14 text-center">
            <span class="material-symbols-outlined mb-3 text-[40px] text-on-surface-variant">cast_for_education</span>
            <p class="font-title-md text-title-md text-on-surface">Chưa có lớp chữa đề</p>
            <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                Tạo lớp feedback QBank hoặc chữa theo exam để bắt đầu host live.
            </p>
            <a href="{{ route('teach.classes.create') }}"
                class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Tạo lớp đầu tiên
            </a>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
            <table class="min-w-full text-left font-body-sm">
                <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                    <tr>
                        <th class="px-4 py-3">Lớp</th>
                        <th class="px-4 py-3">Loại</th>
                        <th class="px-4 py-3">Tham gia</th>
                        <th class="px-4 py-3 text-right">TV</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @foreach ($classrooms as $classroom)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-label-md text-on-surface">{{ $classroom->title }}</p>
                                @if ($classroom->description)
                                    <p class="mt-0.5 line-clamp-1 font-body-sm text-on-surface-variant">{{ $classroom->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">
                                    {{ $classroom->purpose->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant">{{ $classroom->visibility->label() }}</td>
                            <td class="px-4 py-3 text-right text-on-surface">{{ $classroom->active_members_count }}</td>
                            <td class="px-4 py-3">
                                @if ($classroom->liveSession)
                                    <span class="rounded-full bg-error/10 px-2 py-0.5 font-label-sm font-semibold text-error">LIVE</span>
                                @elseif ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
                                    <span class="rounded-full bg-tertiary/15 px-2 py-0.5 font-label-sm font-semibold text-tertiary">Chờ duyệt</span>
                                @elseif ($classroom->upcomingSession)
                                    <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">
                                        Sắp live
                                    </span>
                                @else
                                    <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">
                                        {{ $classroom->status->label() }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('teach.classes.show', $classroom) }}"
                                    class="font-label-md text-primary hover:underline">Chi tiết</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($classrooms->hasPages())
            <div class="mt-4">{{ $classrooms->links() }}</div>
        @endif
    @endif
</x-layouts.teach>
