@php
    $pagination = $searchResult->pagination();
    $difficultyCounts = collect($searchResult->facets['difficulty'] ?? [])->keyBy('value');
    $topicCounts = collect($searchResult->facets['medical_taxonomy_node_id'] ?? [])->keyBy('value');
    $accessCounts = collect($searchResult->facets['is_free'] ?? [])->keyBy(
        fn (array $facet): string => $facet['value'] ? 'free' : 'premium',
    );
@endphp

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="font-headline-md text-headline-md font-bold text-on-surface">Tìm trong ngân hàng câu hỏi</h1>
        <p class="mt-2 text-sm text-on-surface-variant">
            {{ number_format($pagination['total'], 0, ',', '.') }} kết quả cho
            <span class="font-bold text-on-surface">“{{ $searchQuery }}”</span>
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('qbank.index') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-outline-variant bg-white px-4 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low">
            <span class="material-symbols-outlined text-[18px]">history</span>
            Lịch sử phiên luyện
        </a>
        <a href="{{ route('qbank.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary/90">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tạo phiên luyện
        </a>
    </div>
</div>

<form method="GET" action="{{ route('qbank.index') }}"
    class="mb-6 rounded-2xl border border-outline-variant bg-white p-4 shadow-sm sm:p-5">
    <div class="grid gap-3 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] lg:items-end">
        <label class="block">
            <span class="mb-1.5 block text-xs font-bold text-on-surface-variant">Từ khóa</span>
            <span class="relative block">
                <span class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[19px] text-on-surface-variant">search</span>
                <input name="q" value="{{ $searchQuery }}" type="search" minlength="2" maxlength="255" required
                    class="w-full rounded-xl border border-outline-variant bg-surface py-2.5 pr-4 pl-10 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
            </span>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-xs font-bold text-on-surface-variant">Độ khó</span>
            <select name="filter[difficulty]"
                class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($difficultyLabels as $value => $label)
                    @php
                        $count = (int) data_get($difficultyCounts->get($value), 'count', 0);
                    @endphp
                    <option value="{{ $value }}" @selected(($searchFilters['difficulty'] ?? null) === $value)>
                        {{ $label }}{{ $count > 0 ? ' · '.$count : '' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-xs font-bold text-on-surface-variant">Chủ đề</span>
            <select name="filter[medical_taxonomy_node_id]"
                class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($searchTopics as $topic)
                    @php
                        $count = (int) data_get($topicCounts->get($topic->id), 'count', 0);
                    @endphp
                    <option value="{{ $topic->id }}" @selected(($searchFilters['medical_taxonomy_node_id'] ?? null) === $topic->id)>
                        {{ $topic->name }}{{ $count > 0 ? ' · '.$count : '' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-xs font-bold text-on-surface-variant">Quyền truy cập</span>
            <select name="filter[is_free]"
                class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Tất cả</option>
                @if ($accessCounts->has('free'))
                    <option value="1" @selected(($searchFilters['is_free'] ?? null) === true)>
                        Miễn phí · {{ data_get($accessCounts->get('free'), 'count', 0) }}
                    </option>
                @endif
                @if ($accessCounts->has('premium'))
                    <option value="0" @selected(($searchFilters['is_free'] ?? null) === false)>
                        Premium · {{ data_get($accessCounts->get('premium'), 'count', 0) }}
                    </option>
                @endif
            </select>
        </label>

        <div class="flex gap-2">
            <a href="{{ route('qbank.index', ['q' => $searchQuery]) }}"
                class="inline-flex flex-1 items-center justify-center rounded-xl border border-outline-variant px-4 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low lg:flex-none">
                Xóa lọc
            </a>
            <button type="submit"
                class="inline-flex flex-1 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary/90 lg:flex-none">
                Áp dụng
            </button>
        </div>
    </div>
</form>

@if ($searchResult->degraded)
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="status">
        <span class="material-symbols-outlined text-[20px]">warning</span>
        <p>Công cụ tìm kiếm đang tạm gián đoạn. Kết quả bên dưới được lấy từ cơ sở dữ liệu và có thể ít linh hoạt hơn.</p>
    </div>
@endif

@if ($searchError)
    <div class="rounded-2xl border border-error/30 bg-error-container/30 p-6 text-center text-on-error-container" role="alert">
        <p class="font-bold">{{ $searchError }}</p>
    </div>
@elseif ($searchItems === [])
    <div class="rounded-2xl border border-outline-variant bg-white px-6 py-16 text-center shadow-sm">
        <span class="material-symbols-outlined mb-3 text-5xl text-outline">search_off</span>
        <h2 class="text-lg font-bold text-on-surface">Không tìm thấy câu hỏi phù hợp</h2>
        <p class="mt-2 text-sm text-on-surface-variant">Thử sửa chính tả, dùng từ đồng nghĩa hoặc xóa bớt bộ lọc.</p>
        <a href="{{ route('qbank.index', ['q' => $searchQuery]) }}"
            class="mt-5 inline-flex rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white">Nới bộ lọc</a>
    </div>
@else
    <div class="space-y-3">
        @foreach ($searchItems as $item)
            @php
                $attributes = $item['attributes'];
                $topicIds = $attributes['medical_taxonomy_node_ids']
                    ?? $attributes['topic_ids']
                    ?? array_values(array_filter([(int) ($attributes['medical_taxonomy_node_id'] ?? 0)]));
                $topics = $searchTopics->only($topicIds);
                $sessionParams = array_filter([
                    'medical_taxonomy_node_ids' => $topicIds ?: null,
                    'difficulties' => [$attributes['difficulty']],
                ]);
            @endphp
            <article class="rounded-2xl border border-outline-variant bg-white p-4 shadow-sm transition-shadow hover:shadow-md sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-primary/10 px-2.5 py-1 font-bold text-primary">
                                {{ $difficultyLabels[$attributes['difficulty']] ?? $attributes['difficulty'] }}
                            </span>
                            @if ($topics->isNotEmpty())
                                <span class="rounded-full bg-surface-container px-2.5 py-1 text-on-surface-variant">{{ $topics->pluck('name')->join(', ') }}</span>
                            @endif
                            <span @class([
                                'rounded-full px-2.5 py-1 font-bold',
                                'bg-green-100 text-green-700' => $attributes['is_free'],
                                'bg-amber-100 text-amber-800' => ! $attributes['is_free'],
                            ])>
                                {{ $attributes['is_free'] ? 'Miễn phí' : 'Premium' }}
                            </span>
                        </div>
                        <p class="[&_mark]:rounded [&_mark]:bg-yellow-200 [&_mark]:text-on-surface text-sm leading-7 text-on-surface sm:text-base">
                            {!! $item['highlight'] !!}
                        </p>
                    </div>
                    <a href="{{ route('qbank.create', $sessionParams) }}"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-primary/30 px-4 py-2.5 text-sm font-bold text-primary hover:bg-primary/5">
                        <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                        Luyện chủ đề này
                    </a>
                </div>
            </article>
        @endforeach
    </div>

    @if ($searchResult->paginator->hasPages())
        <div class="mt-6">
            {{ $searchResult->paginator->links() }}
        </div>
    @endif
@endif
