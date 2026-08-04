@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Phân trang lộ trình" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-body-sm text-on-surface-variant">
            Hiển thị
            <span class="font-semibold text-on-surface">{{ $paginator->firstItem() }}</span>
            –
            <span class="font-semibold text-on-surface">{{ $paginator->lastItem() }}</span>
            trong
            <span class="font-semibold text-on-surface">{{ $paginator->total() }}</span>
            lộ trình
            · Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-outline-variant px-3 text-body-sm text-on-surface-variant/50"
                    aria-disabled="true">
                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    Trước
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-outline-variant px-3 text-body-sm text-on-surface transition-colors hover:bg-surface-container-low hover:text-primary">
                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    Trước
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex h-9 min-w-9 items-center justify-center text-body-sm text-on-surface-variant"
                        aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-primary-container px-2 font-label-md text-white">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                                class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-outline-variant px-2 text-body-sm text-on-surface transition-colors hover:bg-surface-container-low hover:text-primary"
                                aria-label="Trang {{ $page }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-outline-variant px-3 text-body-sm text-on-surface transition-colors hover:bg-surface-container-low hover:text-primary">
                    Sau
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </a>
            @else
                <span
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-outline-variant px-3 text-body-sm text-on-surface-variant/50"
                    aria-disabled="true">
                    Sau
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </span>
            @endif
        </div>
    </nav>
@endif
