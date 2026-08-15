@php
    /** @var \Modules\Classroom\Models\Classroom $classroom */
    $isMember = $isMember ?? in_array($classroom->id, $joinedClassroomIds ?? [], true);
    $highlightLive = $highlightLive ?? false;

    $isLive = $classroom->liveSession !== null;
    $upcoming = $classroom->upcomingSession;
    $replay = $classroom->replaySession;
    $coverUrl = $classroom->catalogCoverUrl();
    $purpose = $classroom->purpose;
    $memberCount = $classroom->active_members_count ?? $classroom->activeMembers()->count();

    $featuredSession = $isLive ? $classroom->liveSession : ($upcoming ?? $replay);
    $questionCount = $featuredSession?->hasQuestionSet() ? count($featuredSession->questionIds()) : null;

    $cardUrl = route('classroom.show', $classroom);
    $ctaUrl = $cardUrl;
    $ctaLabel = null;

    if ($isLive && $isMember && $classroom->liveSession) {
        $ctaUrl = route('classroom.live', [$classroom, $classroom->liveSession]);
        $ctaLabel = 'Vào ngay';
    } elseif ($isLive) {
        $ctaLabel = 'Xem live';
    } elseif ($replay && $isMember) {
        $ctaUrl = route('classroom.live', [$classroom, $replay]);
        $ctaLabel = 'Xem lại';
    } elseif ($replay) {
        $ctaLabel = 'Có recording';
    }

    $scheduleLabel = null;
    if ($upcoming?->scheduled_at) {
        $at = $upcoming->scheduled_at->timezone(config('app.timezone'));
        $scheduleLabel = $at->isToday()
            ? 'Hôm nay '.$at->format('H:i')
            : ($at->isTomorrow() ? 'Ngày mai '.$at->format('H:i') : $at->format('d/m/Y H:i'));
    }
@endphp

<article
    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm transition hover:border-primary/40 hover:shadow-md">
    <a href="{{ $cardUrl }}" class="relative block aspect-[16/9] shrink-0 overflow-hidden">
        @if ($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="size-full object-cover transition duration-300 group-hover:scale-[1.02]">
        @else
            <div @class([
                'flex size-full flex-col items-center justify-center bg-gradient-to-br text-on-primary',
                $purpose->coverGradientClass(),
            ])>
                <span class="material-symbols-outlined text-[48px] opacity-90">{{ $purpose->coverIcon() }}</span>
                <span class="mt-1 font-headline-sm text-headline-sm opacity-90">{{ $classroom->coverInitial() }}</span>
            </div>
        @endif

        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>

        @if ($isLive || $highlightLive)
            <span class="absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-full bg-error px-2.5 py-1 text-xs font-bold text-white shadow">
                <span class="size-1.5 rounded-full bg-white animate-pulse"></span>
                LIVE
            </span>
        @elseif ($replay)
            <span class="absolute top-3 right-3 rounded-full bg-surface/90 px-2.5 py-1 text-xs font-semibold text-on-surface shadow">
                Có VOD
            </span>
        @elseif ($upcoming)
            <span class="absolute top-3 right-3 rounded-full bg-primary/90 px-2.5 py-1 text-xs font-semibold text-on-primary shadow">
                Sắp live
            </span>
        @endif

        <div class="absolute bottom-3 left-3 flex items-center gap-2">
            <span class="flex size-9 items-center justify-center overflow-hidden rounded-full border-2 border-surface bg-primary-container font-bold text-on-primary-container shadow">
                @if ($classroom->host?->avatarUrl())
                    <img src="{{ $classroom->host->avatarUrl() }}" alt="{{ $classroom->host->name }}" class="size-full object-cover">
                @else
                    {{ $classroom->host?->avatarInitial() }}
                @endif
            </span>
            <span class="max-w-[140px] truncate rounded-full bg-black/45 px-2 py-0.5 text-xs font-medium text-white backdrop-blur-sm">
                {{ $classroom->host?->name }}
            </span>
        </div>
    </a>

    <div class="flex flex-1 flex-col p-4">
        <div class="mb-2 flex flex-wrap items-center gap-1.5">
            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                {{ $purpose->label() }}
            </span>
            @if ($isMember)
                <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-xs font-medium text-on-surface-variant">
                    Đã tham gia
                </span>
            @elseif ($classroom->visibility === \Modules\Classroom\Enums\ClassroomVisibility::Public)
                <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-xs font-medium text-on-surface-variant">
                    Công khai
                </span>
            @endif
        </div>

        <a href="{{ $cardUrl }}" class="mb-2 font-headline-sm text-headline-sm text-on-surface transition group-hover:text-primary">
            {{ $classroom->title }}
        </a>

        <p class="mb-3 line-clamp-2 flex-1 text-sm text-on-surface-variant">
            {{ $classroom->description ?: 'Chưa có mô tả.' }}
        </p>

        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-on-surface-variant">
            <span class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">group</span>
                {{ $memberCount }}@if ($classroom->max_members)/{{ $classroom->max_members }}@endif
            </span>
            @if ($questionCount)
                <span class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">quiz</span>
                    {{ $questionCount }} câu
                </span>
            @endif
            @if ($isLive)
                <span class="inline-flex items-center gap-1 font-medium text-error">
                    <span class="material-symbols-outlined text-[16px]">podcasts</span>
                    Đang phát
                </span>
            @elseif ($scheduleLabel)
                <span class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">schedule</span>
                    {{ $scheduleLabel }}
                    @if ($upcoming?->title)
                        · {{ \Illuminate\Support\Str::limit($upcoming->title, 24) }}
                    @endif
                </span>
            @endif
        </div>

        @if ($classroom->host?->specialty)
            <p class="mb-3 truncate text-xs text-on-surface-variant">
                {{ $classroom->host->specialty }}
                @if ($classroom->host->institution)
                    · {{ $classroom->host->institution }}
                @endif
            </p>
        @endif

        @if ($ctaLabel)
            <a href="{{ $ctaUrl }}"
                @class([
                    'mt-auto inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition',
                    'bg-error text-white hover:opacity-90' => $isLive,
                    'bg-primary text-on-primary hover:opacity-90' => ! $isLive,
                ])>
                @if ($isLive)
                    <span class="material-symbols-outlined text-[18px]">sensors</span>
                @elseif ($replay && $isMember)
                    <span class="material-symbols-outlined text-[18px]">play_circle</span>
                @endif
                {{ $ctaLabel }}
            </a>
        @else
            <a href="{{ $cardUrl }}"
                class="mt-auto inline-flex items-center justify-center rounded-xl border border-outline-variant px-4 py-2 text-sm font-medium text-on-surface transition hover:bg-surface-container-low">
                Xem chi tiết
            </a>
        @endif
    </div>
</article>
