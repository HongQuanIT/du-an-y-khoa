@php
    /** @var array $comparison */
    $empty = 'Chưa nhập.';
    $heading = $heading ?? 'So sánh với bản đang dùng';
    $proposedTitle = $proposedTitle ?? 'Bản cần duyệt';
    $proposedBadge = $proposedBadge ?? 'Hiện tại';
    $newCopy = $newCopy ?? 'Câu này chưa từng xuất bản — bên trái trống, bên phải là toàn bộ nội dung mới.';
    $sameCopy = $sameCopy ?? 'Nội dung gửi duyệt trùng với bản đang dùng.';
    $preserveRichText = (bool) ($preserveRichText ?? false);
@endphp

@if (! $comparison['can_compare'])
    <section class="rounded-2xl border border-outline-variant bg-surface p-5" aria-labelledby="question-content-title">
        <h3 id="question-content-title" class="mb-4 font-label-lg font-bold text-on-surface">Nội dung câu hỏi</h3>
        @include('questionbank::partials.question-review-comparison-pane', [
            'side' => 'proposed',
            'comparison' => $comparison,
            'empty' => $empty,
            'preserveRichText' => $preserveRichText,
            'highlightChanges' => false,
        ])
    </section>
@else
<section class="mb-6 rounded-2xl border border-outline-variant bg-surface p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="font-label-lg font-bold text-on-surface">{{ $heading }}</h3>
            <p class="mt-1 text-sm text-on-surface-variant">
                @if ($comparison['has_changes'])
                    Có thay đổi ở
                    <span class="font-semibold text-on-surface">{{ implode(', ', $comparison['changed_labels']) }}</span>.
                    Chữ gạch đỏ = xóa, chữ nền xanh = thêm, khối vàng = sửa.
                @else
                    {{ $sameCopy }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-rose-800">
                <span class="size-2 rounded-full bg-rose-500" aria-hidden="true"></span>Xóa
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-amber-800">
                <span class="size-2 rounded-full bg-amber-500" aria-hidden="true"></span>Sửa
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-900">
                <span class="size-2 rounded-full bg-emerald-500" aria-hidden="true"></span>Thêm
            </span>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
    <section class="rounded-2xl border border-outline-variant bg-surface p-5 lg:sticky lg:top-4"
        aria-labelledby="published-review-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 id="published-review-title" class="font-label-lg font-bold text-on-surface">Bản đang dùng</h3>
            @if ($comparison['published_version'])
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">v{{ $comparison['published_version'] }}</span>
            @endif
        </div>
        @include('questionbank::partials.question-review-comparison-pane', [
            'side' => 'published',
            'comparison' => $comparison,
            'empty' => $empty,
            'preserveRichText' => $preserveRichText,
        ])
    </section>

    <section class="rounded-2xl border border-primary/40 bg-primary/5 p-5" aria-labelledby="proposed-review-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 id="proposed-review-title" class="font-label-lg font-bold text-on-surface">{{ $proposedTitle }}</h3>
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">{{ $proposedBadge }}</span>
        </div>
        @include('questionbank::partials.question-review-comparison-pane', [
            'side' => 'proposed',
            'comparison' => $comparison,
            'empty' => $empty,
            'preserveRichText' => $preserveRichText,
        ])
    </section>
</div>
@endif
