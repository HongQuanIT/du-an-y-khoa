@php
    $editingLanding = isset($page) && ($page->key?->isLandingBlock() ?? false);
    $requestGroup = request()->query('group');
    $cmsGroup = $editingLanding ? 'landing' : ($requestGroup === 'landing' ? 'landing' : 'static');
    $editorCms = request()->routeIs('editor.*');
    $routePrefix = $editorCms ? 'editor.cms.' : 'admin.cms.';

    $tabs = [
        ['label' => 'FAQ', 'route' => $routePrefix.'faq.index', 'match' => $routePrefix.'faq.*', 'params' => []],
        ['label' => 'Trang tĩnh', 'route' => $routePrefix.'pages.index', 'match' => $routePrefix.'pages.*', 'params' => [], 'active_when' => 'static'],
        ['label' => 'Banner', 'route' => $routePrefix.'banners.index', 'match' => $routePrefix.'banners.*', 'params' => []],
        ['label' => 'Landing', 'route' => $routePrefix.'pages.index', 'match' => $routePrefix.'pages.*', 'params' => ['group' => 'landing'], 'active_when' => 'landing'],
        ['label' => 'Blog', 'route' => null, 'match' => $routePrefix.'blog.*', 'params' => []],
        ['label' => 'Menu', 'route' => $routePrefix.'menus.index', 'match' => $routePrefix.'menus.*', 'params' => []],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-1 border-b border-outline-variant" aria-label="CMS">
    @foreach ($tabs as $tab)
        @php
            $href = $tab['route'] && Route::has($tab['route']) ? route($tab['route'], $tab['params'] ?? []) : null;
            $routeMatch = $tab['match'] && request()->routeIs($tab['match']);
            $activeWhen = $tab['active_when'] ?? null;
            if ($activeWhen === 'landing') {
                $active = $routeMatch && $cmsGroup === 'landing';
            } elseif ($activeWhen === 'static') {
                $active = $routeMatch && $cmsGroup === 'static';
            } else {
                $active = $routeMatch;
            }
        @endphp
        @if ($href)
            <a href="{{ $href }}"
                @class([
                    'border-b-2 px-4 py-2.5 font-label-md text-label-md transition-colors -mb-px',
                    'border-primary text-primary' => $active,
                    'border-transparent text-on-surface-variant hover:text-on-surface' => ! $active,
                ])>{{ $tab['label'] }}</a>
        @else
            <span
                class="cursor-not-allowed border-b-2 border-transparent px-4 py-2.5 font-label-md text-label-md text-on-surface-variant/50"
                title="Sắp có">{{ $tab['label'] }}</span>
        @endif
    @endforeach
</nav>
