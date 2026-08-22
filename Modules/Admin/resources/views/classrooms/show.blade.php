@php
    use Modules\Classroom\Enums\LiveSessionStatus;
@endphp

<x-layouts.admin :title="$classroom->title">
    <div class="mb-6">
        <a href="{{ route('admin.classrooms.index') }}"
            class="mb-4 inline-flex items-center gap-1 font-label-sm text-label-sm text-primary hover:underline">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Danh sách lớp
        </a>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $classroom->title }}</h1>
                <p class="mt-2 text-on-surface-variant">
                    Giảng viên: <span class="font-semibold text-on-surface">{{ $classroom->host?->name ?? '—' }}</span>
                    · {{ $classroom->purpose->label() }}
                    · {{ $classroom->status->label() }}
                </p>
                @if ($classroom->description)
                    <p class="mt-3 max-w-3xl text-on-surface-variant">{{ $classroom->description }}</p>
                @endif
            </div>

            @if ($classroom->liveSession)
                <a href="{{ route('admin.classrooms.live', [$classroom, $classroom->liveSession]) }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-error px-4 py-2.5 font-label-md text-label-md font-semibold text-white hover:opacity-90">
                    <span class="material-symbols-outlined text-[20px]">sensors</span>
                    Vào live đang dạy
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <section class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
            <h2 class="mb-4 font-headline-sm text-headline-sm text-on-surface">Các buổi học</h2>

            @if ($classroom->sessions->isEmpty())
                <p class="text-on-surface-variant">Lớp chưa có buổi học nào.</p>
            @else
                <div class="divide-y divide-outline-variant">
                    @foreach ($classroom->sessions as $session)
                        <div class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-label-md text-label-md font-semibold text-on-surface">
                                        {{ $session->title }}
                                    </span>
                                    <span @class([
                                        'rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold',
                                        'bg-error text-white' => $session->status === LiveSessionStatus::Live,
                                        'bg-primary/15 text-primary' => $session->status === LiveSessionStatus::Scheduled,
                                        'bg-surface-container text-on-surface-variant' => $session->status === LiveSessionStatus::Ended,
                                    ])>
                                        {{ $session->status->label() }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-on-surface-variant">
                                    {{ $session->scheduled_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Chưa đặt lịch' }}
                                </p>
                            </div>

                            @if ($session->status === LiveSessionStatus::Live)
                                <a href="{{ route('admin.classrooms.live', [$classroom, $session]) }}"
                                    class="inline-flex items-center justify-center rounded-lg bg-error px-3 py-1.5 font-label-sm text-label-sm font-semibold text-white hover:opacity-90">
                                    Vào phòng live
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Thành viên</h2>
                <span class="rounded-full bg-surface-container px-2.5 py-1 text-xs text-on-surface-variant">
                    {{ $classroom->activeMembers->count() }}
                </span>
            </div>

            @if ($classroom->activeMembers->isEmpty())
                <p class="text-sm text-on-surface-variant">Chưa có thành viên.</p>
            @else
                <ul class="max-h-96 space-y-3 overflow-y-auto">
                    @foreach ($classroom->activeMembers as $member)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-on-surface">{{ $member->user?->name ?? '—' }}</span>
                            <span class="shrink-0 text-xs text-on-surface-variant">
                                {{ $member->role_in_class->value }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>
</x-layouts.admin>
