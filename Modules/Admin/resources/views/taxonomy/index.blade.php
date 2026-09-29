<x-layouts.admin title="Phân loại câu hỏi">
    <x-admin.page-header title="Phân loại câu hỏi"
        description="Kiểm tra học viên có tìm được câu không: chức danh, kỳ thi, bài học và thẻ.">
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'overview'])

    <x-admin.flash />

    @if (count($kpis) > 0)
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <x-admin.kpi-card
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :hint="$kpi['hint']"
                    :icon="$kpi['icon']"
                    :href="$kpi['href']"
                    :severity="$kpi['severity']" />
            @endforeach
        </div>
    @endif

    @if (count($areas) > 0)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($areas as $area)
                <a href="{{ $area['href'] }}"
                    class="group flex flex-col rounded-xl border border-outline-variant bg-surface p-5 transition-colors hover:border-primary/40 hover:bg-primary/5">
                    <div class="flex items-start gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $area['icon_class'] }}">
                            <span class="material-symbols-outlined text-[28px]">{{ $area['icon'] }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-label-lg font-semibold text-on-surface group-hover:text-primary">{{ $area['title'] }}</h3>
                            <p class="mt-1 text-sm text-on-surface-variant">{{ $area['summary'] }}</p>
                        </div>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-2">
                        @foreach ($area['facts'] as $fact)
                            <div class="rounded-lg bg-surface-container-low px-3 py-2">
                                <dt class="text-[11px] font-medium text-on-surface-variant">{{ $fact['label'] }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-on-surface">{{ $fact['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-4 text-xs font-semibold text-primary">{{ $area['action'] }} →</p>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
