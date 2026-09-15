@php
    /** @var array $comparison */
    $empty = 'Chưa nhập.';
@endphp

<section class="mb-6 rounded-2xl border border-outline-variant bg-surface p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="font-label-lg font-bold text-on-surface">So sánh với bản đang xuất bản</h3>
            <p class="mt-1 text-sm text-on-surface-variant">
                @if ($comparison['has_changes'])
                    Có thay đổi ở
                    <span class="font-semibold text-on-surface">{{ implode(', ', $comparison['changed_labels']) }}</span>.
                @else
                    Nội dung gửi duyệt trùng với bản đang xuất bản.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-rose-800">
                <span class="size-2 rounded-full bg-rose-500" aria-hidden="true"></span>Xóa
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-900">
                <span class="size-2 rounded-full bg-emerald-500" aria-hidden="true"></span>Thêm
            </span>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-outline-variant bg-surface p-5" aria-labelledby="published-review-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 id="published-review-title" class="font-label-lg font-bold text-on-surface">Bản đang xuất bản</h3>
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">v{{ $comparison['published_version'] }}</span>
        </div>
        @include('questionbank::partials.question-review-comparison-pane', [
            'side' => 'published',
            'comparison' => $comparison,
            'empty' => $empty,
        ])
    </section>

    <section class="rounded-2xl border border-primary/40 bg-primary/5 p-5" aria-labelledby="proposed-review-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 id="proposed-review-title" class="font-label-lg font-bold text-on-surface">Bản cần duyệt</h3>
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Working copy</span>
        </div>
        @include('questionbank::partials.question-review-comparison-pane', [
            'side' => 'proposed',
            'comparison' => $comparison,
            'empty' => $empty,
        ])
    </section>
</div>
