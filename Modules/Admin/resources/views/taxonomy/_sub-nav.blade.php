@php
    $active = $active ?? null;
    $tabs = [
        ['key' => 'overview', 'label' => 'Tổng quan', 'route' => 'admin.taxonomy.index', 'icon' => 'dashboard'],
        ['key' => 'blueprints', 'label' => 'Ma trận đề thi', 'route' => 'admin.blueprints.index', 'icon' => 'assignment'],
        ['key' => 'medical', 'label' => 'Danh mục y khoa', 'route' => 'admin.medical-taxonomy.index', 'icon' => 'account_tree'],
        ['key' => 'tags', 'label' => 'Tags', 'route' => 'admin.tags.index', 'icon' => 'sell'],
    ];
@endphp

<nav aria-label="Phân loại câu hỏi" class="mb-6 overflow-x-auto rounded-xl border border-outline-variant bg-surface p-1">
    <ul class="flex min-w-max gap-1">
        @foreach ($tabs as $tab)
            @php
                $isActive = $active === $tab['key']
                    || ($active === null && request()->routeIs($tab['route']));
            @endphp
            <li>
                <a href="{{ route($tab['route']) }}"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2.5 font-label-sm transition-colors {{ $isActive ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
