@php
    // Teach classroom detail stub (Phase B).
@endphp

<x-layouts.teach :title="$classroom->title">
    <div class="mb-6">
        <a href="{{ route('teach.classes.index') }}"
            class="mb-4 inline-flex items-center gap-1 font-label-sm text-label-sm text-primary hover:underline">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Lớp của tôi
        </a>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 font-body-sm text-body-sm text-primary">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 font-label-sm text-primary">
                        {{ $classroom->purpose->label() }}
                    </span>
                    <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 font-label-sm text-on-surface-variant">
                        {{ $classroom->visibility->label() }}
                    </span>
                    @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
                        <span class="rounded-full bg-tertiary/15 px-2.5 py-0.5 font-label-sm font-semibold text-tertiary">Chờ duyệt</span>
                    @endif
                    @if ($classroom->liveSession)
                        <span class="rounded-full bg-error/10 px-2.5 py-0.5 font-label-sm font-semibold text-error">LIVE</span>
                    @endif
                </div>
                <h2 class="font-headline-sm text-headline-sm text-on-surface">{{ $classroom->title }}</h2>
                <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                    Host: <span class="text-on-surface">{{ $classroom->host?->name }}</span>
                    · {{ $classroom->activeMembers->count() }} thành viên
                    · {{ $classroom->status->label() }}
                </p>
                @if ($classroom->description)
                    <p class="mt-3 max-w-2xl font-body-md text-body-md text-on-surface">{{ $classroom->description }}</p>
                @endif
                @if ($classroom->join_code)
                    <p class="mt-3 inline-flex items-center gap-2 rounded-lg bg-surface-container-low px-3 py-1.5 font-body-sm text-body-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-[18px]">vpn_key</span>
                        Mã tham gia: <strong class="text-on-surface">{{ $classroom->join_code }}</strong>
                    </p>
                @endif
            </div>
        </div>
    </div>

    @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
        <div class="mb-6 rounded-xl border border-tertiary/30 bg-tertiary/10 px-4 py-3 font-body-sm text-body-sm text-on-surface">
            Lớp đang <strong>chờ admin duyệt</strong>. Học viên chưa thấy trên catalog; sau khi duyệt bạn mới có thể bắt đầu live.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="font-title-md text-title-md text-on-surface">Gắn đề / bộ câu hỏi</h3>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                    @if ($classroom->purpose->value === 'feedback_review')
                        Phase B+: chọn câu từ hàng chờ feedback QBank.
                    @else
                        Phase C: chọn exam / kỳ thi để gắn vào buổi chữa.
                    @endif
                </p>
                <div class="mt-4 rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-8 text-center">
                    <span class="material-symbols-outlined text-[32px] text-on-surface-variant">quiz</span>
                    <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Chưa gắn đề — sắp có ở phase tiếp theo.</p>
                </div>
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-title-md text-title-md text-on-surface">Buổi live</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Lên lịch và host studio — stub Phase B.</p>
                    </div>
                </div>

                @if ($classroom->liveSession)
                    <div class="mb-4 rounded-lg border border-error/20 bg-error/5 px-4 py-3 font-body-sm text-body-sm text-error">
                        Đang có buổi live: {{ $classroom->liveSession->title }}
                    </div>
                @endif

                @if ($upcomingSessions->isNotEmpty())
                    <ul class="mb-4 space-y-2">
                        @foreach ($upcomingSessions as $session)
                            <li class="flex items-center justify-between rounded-lg bg-surface-container-lowest px-3 py-2.5">
                                <div>
                                    <p class="font-label-md text-on-surface">{{ $session->title }}</p>
                                    <p class="font-body-sm text-on-surface-variant">
                                        {{ $session->scheduled_at?->format('d/m/Y H:i') ?? 'Chưa đặt giờ' }}
                                        · {{ $session->status->label() }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-8 text-center">
                        <span class="material-symbols-outlined text-[32px] text-on-surface-variant">event</span>
                        <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Chưa có lịch live.</p>
                    </div>
                @endif

                @if ($pastSessions->isNotEmpty())
                    <div class="mt-6 border-t border-outline-variant pt-4">
                        <p class="mb-2 font-label-sm text-on-surface-variant uppercase tracking-wide">Buổi trước</p>
                        <ul class="space-y-2">
                            @foreach ($pastSessions as $session)
                                <li class="font-body-sm text-on-surface-variant">
                                    {{ $session->title }}
                                    · {{ $session->status->label() }}
                                    @if ($session->ended_at)
                                        · {{ $session->ended_at->format('d/m/Y') }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="font-title-md text-title-md text-on-surface">Thành viên</h3>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                    {{ $classroom->activeMembers->count() }} đang tham gia
                    @if ($classroom->max_members)
                        / tối đa {{ $classroom->max_members }}
                    @endif
                </p>
                <ul class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                    @forelse ($classroom->activeMembers as $member)
                        <li class="flex items-center justify-between gap-2 font-body-sm">
                            <span class="truncate text-on-surface">{{ $member->user?->name ?? '—' }}</span>
                            <span class="shrink-0 font-label-sm text-on-surface-variant">{{ $member->role_in_class->label() }}</span>
                        </li>
                    @empty
                        <li class="font-body-sm text-on-surface-variant">Chưa có thành viên.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="font-title-md text-title-md text-on-surface">Bước tiếp theo</h3>
                <ul class="mt-3 space-y-2 font-body-sm text-body-sm text-on-surface-variant">
                    <li>• Gắn đề từ hàng chờ / exam (Phase B+/C)</li>
                    <li>• Lên lịch buổi live</li>
                    <li>• Mở host studio khi đến giờ</li>
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.teach>
