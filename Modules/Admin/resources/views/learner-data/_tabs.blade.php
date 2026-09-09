@php
    $learnerDataTabs = [
        ['label' => 'Tổng hợp', 'route' => 'admin.learner-data.demographics', 'match' => 'admin.learner-data.demographics'],
        ['label' => 'Quốc gia', 'route' => 'admin.countries.index', 'match' => 'admin.countries.*'],
        ['label' => 'Tỉnh/Thành phố', 'route' => 'admin.administrative-units.index', 'match' => 'admin.administrative-units.*'],
        ['label' => 'Trường học', 'route' => 'admin.institutions.index', 'match' => 'admin.institutions.*'],
        ['label' => 'Chức danh', 'route' => 'admin.professions.index', 'match' => 'admin.professions.*'],
        ['label' => 'Năm học', 'route' => 'admin.education-stages.index', 'match' => 'admin.education-stages.*'],
    ];
@endphp

<nav class="mb-6 overflow-x-auto rounded-xl border border-outline-variant bg-surface p-1" aria-label="Quản lý dữ liệu học viên">
    <div class="flex min-w-max gap-1">
        @foreach ($learnerDataTabs as $tab)
            <a href="{{ route($tab['route']) }}"
                @class([
                    'rounded-lg px-3 py-2 font-label-sm font-semibold transition-colors',
                    'bg-primary text-on-primary' => request()->routeIs($tab['match']),
                    'text-on-surface-variant hover:bg-surface-container-low' => ! request()->routeIs($tab['match']),
                ])>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
