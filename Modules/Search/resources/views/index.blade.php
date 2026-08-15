@php
    use Illuminate\Support\Str;

    $typeLabels = [
        'question' => 'Câu hỏi',
        'article' => 'Bài viết',
        'disease' => 'Bệnh',
        'drug' => 'Thuốc',
        'procedure' => 'Thủ thuật',
        'keyword' => 'Từ khoá',
    ];
@endphp

<x-layouts.app title="Tìm kiếm">
    <div class="px-4 py-6 md:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <section class="rounded-2xl border border-outline-variant bg-white p-5 shadow-sm">
                <form method="get" action="{{ route('search.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto]">
                    <div class="relative">
                        <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant">search</span>
                        <input name="q" value="{{ $query }}" type="search" placeholder="Tìm bài viết, câu hỏi, thuốc, thủ thuật..."
                            autocomplete="off"
                            class="w-full rounded-xl border border-outline-variant bg-surface py-3 pr-4 pl-10 font-body-md text-body-md focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-3 font-label-md text-label-md font-semibold text-white">
                        Tìm kiếm
                    </button>
                </form>

                @if ($query === '')
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl bg-surface-container-low p-4">
                            <p class="font-label-md text-label-md font-semibold text-on-surface">Gợi ý gần đây</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($suggestions as $suggestion)
                                    <a href="{{ $suggestion['url'] }}"
                                        class="rounded-full bg-white px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant hover:bg-primary/10 hover:text-primary">
                                        {{ $suggestion['text'] }}
                                    </a>
                                @empty
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">Chưa có gợi ý nào.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="rounded-xl bg-surface-container-low p-4">
                            <p class="font-label-md text-label-md font-semibold text-on-surface">Cách tìm nhanh</p>
                            <ul class="mt-3 space-y-2 font-body-sm text-body-sm text-on-surface-variant">
                                <li>• Gõ tên bệnh, thuốc, hoặc chủ đề.</li>
                                <li>• Bấm Enter để tìm toàn hệ thống.</li>
                                <li>• Kết quả sẽ tự gom từ Qbank và Library.</li>
                            </ul>
                        </div>
                    </div>
                @endif
            </section>

            @if ($result !== null)
                <section class="rounded-2xl border border-outline-variant bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p class="font-title-md text-title-md font-bold text-on-surface">
                                Kết quả cho “{{ $query }}”
                            </p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">
                                {{ $result->pagination()['total'] }} kết quả · {{ $result->engine }}{{ $result->degraded ? ' · fallback' : '' }}
                            </p>
                        </div>
                        <form method="get" action="{{ route('search.index') }}" class="flex flex-wrap gap-2">
                            <input type="hidden" name="q" value="{{ $query }}">
                            @foreach (['' => 'Tất cả', 'question' => 'Câu hỏi', 'article' => 'Bài viết', 'disease' => 'Bệnh', 'drug' => 'Thuốc', 'procedure' => 'Thủ thuật'] as $value => $label)
                                <button type="submit" name="type" value="{{ $value }}"
                                    @class([
                                        'rounded-full px-3 py-1.5 font-label-sm text-label-sm transition-colors',
                                        'bg-primary text-white' => ($type ?? '') === $value,
                                        'bg-surface-container-low text-on-surface-variant hover:bg-surface-container' => ($type ?? '') !== $value,
                                    ])>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </form>
                    </div>

                    <div class="mt-5 grid gap-3">
                        @forelse ($result->items() as $item)
                            @php
                                $type = (string) ($item['type'] ?? 'article');
                                $label = $typeLabels[$type] ?? Str::headline($type);
                                $isFree = (bool) data_get($item, 'attributes.is_free', true);
                            @endphp
                            <a href="{{ $item['url'] ?? route('search.index', ['q' => $query, 'type' => $item['type'] ?? null]) }}"
                                class="block rounded-2xl border border-outline-variant bg-surface p-4 transition-colors hover:border-primary/40 hover:bg-primary/5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-primary/10 px-2.5 py-1 font-label-sm text-label-sm font-semibold text-primary">
                                        {{ $label }}
                                    </span>
                                    <span class="rounded-full bg-surface-container-low px-2.5 py-1 font-label-sm text-label-sm text-on-surface-variant">
                                        {{ (string) ($item['scope'] ?? 'global') }}
                                    </span>
                                    @if (! $isFree)
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 font-label-sm text-label-sm text-amber-800">
                                            Premium
                                        </span>
                                    @endif
                                </div>
                                <h3 class="mt-3 font-title-sm text-title-sm font-bold text-on-surface">{{ $item['title'] }}</h3>
                                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{!! $item['highlight'] ?? '' !!}</p>
                            </a>
                        @empty
                            <div class="rounded-2xl border border-dashed border-outline-variant bg-surface p-8 text-center">
                                <p class="font-title-sm text-title-sm font-bold text-on-surface">Không có kết quả phù hợp</p>
                                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Thử đổi từ khoá hoặc bỏ bớt bộ lọc.</p>
                            </div>
                        @endforelse
                    </div>

                    @if ($result->paginator->hasPages())
                        <div class="mt-6">
                            {{ $result->paginator->links() }}
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
