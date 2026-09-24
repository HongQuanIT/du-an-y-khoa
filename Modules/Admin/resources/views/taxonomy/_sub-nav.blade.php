@php
    use App\Support\Auth\PortalRoute;
    use Modules\Admin\Support\AdminRouteAccess;

    $active = $active ?? null;
    $tabs = [
        ['key' => 'overview', 'label' => 'Tổng quan', 'suffix' => 'taxonomy.index', 'access' => 'admin.taxonomy.index', 'icon' => 'dashboard'],
        ['key' => 'blueprints', 'label' => 'Ma trận đề thi', 'suffix' => 'blueprints.index', 'access' => 'admin.blueprints.index', 'icon' => 'assignment'],
        ['key' => 'curriculum', 'label' => 'Danh mục kiến thức', 'suffix' => 'curriculum.index', 'access' => 'admin.curriculum.index', 'icon' => 'account_tree'],
        ['key' => 'tags', 'label' => 'Tags', 'suffix' => 'tags.index', 'access' => 'admin.tags.index', 'icon' => 'sell'],
    ];
@endphp

<nav aria-label="Phân loại câu hỏi" class="mb-6 overflow-x-auto rounded-xl border border-outline-variant bg-surface p-1">
    <ul class="flex min-w-max gap-1">
        @foreach ($tabs as $tab)
            @continue (! AdminRouteAccess::allows(auth()->user(), $tab['access']))
            @php
                $routeName = PortalRoute::content($tab['suffix']);
                $isActive = $active === $tab['key']
                    || ($active === null && request()->routeIs($routeName));
            @endphp
            <li>
                <a href="{{ route($routeName) }}"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2.5 font-label-sm transition-colors {{ $isActive ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
