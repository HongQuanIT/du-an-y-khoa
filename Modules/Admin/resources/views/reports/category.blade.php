<x-layouts.admin :title="$category['title']">
    <x-admin.page-header :title="$category['title']" :description="$category['description']">
        <x-slot:actions>
            <a href="{{ route('admin.reports.index') }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-label-md text-on-surface-variant transition hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Trung tâm báo cáo
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach ($category['reports'] as $report)
            <a href="{{ route('admin.reports.show', [$category['slug'], $report['slug']]) }}"
                class="group rounded-xl border border-outline-variant bg-surface p-5 transition hover:border-primary/40 hover:shadow-sm">
                <h3 class="font-title-md text-on-surface group-hover:text-primary">{{ $report['title'] }}</h3>
                <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">{{ $report['description'] }}</p>
                <p class="mt-4 font-label-sm text-label-sm text-primary opacity-0 transition group-hover:opacity-100">
                    Mở báo cáo →
                </p>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
