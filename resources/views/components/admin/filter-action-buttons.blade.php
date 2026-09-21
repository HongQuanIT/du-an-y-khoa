@props([
    'loadingExpression' => 'loading',
    'resetMethod' => 'resetFilters',
    'resetUrl' => null,
    'fill' => false,
    'searchAriaLabel' => 'Tìm kiếm',
    'resetAriaLabel' => 'Xoá bộ lọc',
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <button type="submit" :disabled="{{ $loadingExpression }}" title="{{ $searchAriaLabel }}" aria-label="{{ $searchAriaLabel }}"
        @class(['inline-flex h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-primary px-3 text-sm font-semibold text-on-primary shadow-xs transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-50', 'flex-1' => $fill, 'w-36 shrink-0' => ! $fill])>
        <template x-if="{{ $loadingExpression }}">
            <span class="size-4 animate-spin rounded-full border-2 border-on-primary border-t-transparent" aria-hidden="true"></span>
        </template>
        <template x-if="!{{ $loadingExpression }}">
            <span class="material-symbols-outlined shrink-0 text-[18px]" aria-hidden="true">search</span>
        </template>
        <span x-text="{{ $loadingExpression }} ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
    </button>
    <button type="button" @click="{{ $resetUrl ? $resetMethod.'('.json_encode($resetUrl).')' : $resetMethod.'()' }}" :disabled="{{ $loadingExpression }}" title="Xoá" aria-label="{{ $resetAriaLabel }}"
        @class(['inline-flex h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-outline-variant bg-surface px-3 text-sm font-semibold text-on-surface-variant transition hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50', 'flex-1' => $fill, 'w-28 shrink-0' => ! $fill])>
        <span class="material-symbols-outlined shrink-0 text-[18px]" aria-hidden="true">delete</span>
        <span>Xoá</span>
    </button>
</div>
