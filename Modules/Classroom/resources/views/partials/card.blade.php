@php
    /** @var \Modules\Classroom\Models\Classroom $classroom */
    $highlightLive = $highlightLive ?? false;
    $isLive = $classroom->liveSession !== null;
@endphp

<a href="{{ route('classroom.show', $classroom) }}"
    class="group block rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm transition hover:border-primary/40 hover:shadow-md">
    <div class="mb-3 flex items-start justify-between gap-2">
        <h3 class="font-headline-sm text-headline-sm text-on-surface group-hover:text-primary">
            {{ $classroom->title }}
        </h3>
        @if ($isLive || $highlightLive)
            <span class="shrink-0 rounded-full bg-error px-2 py-0.5 text-xs font-semibold text-white">LIVE</span>
        @endif
    </div>
    <p class="mb-4 line-clamp-2 text-sm text-on-surface-variant">
        {{ $classroom->description ?: 'Chưa có mô tả.' }}
    </p>
    <div class="flex items-center justify-between text-xs text-on-surface-variant">
        <span class="inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">person</span>
            {{ $classroom->host?->name }}
        </span>
        <span class="inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">group</span>
            {{ $classroom->active_members_count ?? $classroom->activeMembers()->count() }}
        </span>
    </div>
</a>
