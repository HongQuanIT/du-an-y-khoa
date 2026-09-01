@props([
    'title',
    'description',
    'icon',
    'reportCount',
    'href',
])

<a href="{{ $href }}"
    {{ $attributes->class([
        'group flex flex-col rounded-xl border border-outline-variant bg-surface p-5 transition hover:border-primary/40 hover:shadow-sm',
    ]) }}>
    <div class="mb-4 flex items-start justify-between gap-3">
        <span class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <span class="material-symbols-outlined text-[24px]">{{ $icon }}</span>
        </span>
        <span class="rounded-full bg-surface-container-high px-2.5 py-1 font-label-sm text-label-sm text-on-surface-variant">
            {{ $reportCount }} báo cáo
        </span>
    </div>
    <h3 class="font-headline-sm text-headline-sm text-on-surface group-hover:text-primary">{{ $title }}</h3>
    <p class="mt-2 flex-1 font-body-sm text-body-sm text-on-surface-variant">{{ $description }}</p>
    <p class="mt-4 font-label-sm text-label-sm text-primary opacity-0 transition group-hover:opacity-100">
        Xem danh mục →
    </p>
</a>
