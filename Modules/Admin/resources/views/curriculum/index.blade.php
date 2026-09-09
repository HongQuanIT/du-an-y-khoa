@php
    $tabs = [
        ['key' => 'organ-systems', 'label' => 'Hệ cơ quan', 'icon' => 'account_tree', 'count' => $stats['organ_systems']],
        ['key' => 'subjects', 'label' => 'Môn học', 'icon' => 'folder', 'count' => $stats['subjects']],
        ['key' => 'lessons', 'label' => 'Bài học', 'icon' => 'menu_book', 'count' => $stats['lessons']],
    ];
    $activeTab = in_array(request('tab'), ['organ-systems', 'subjects', 'lessons'], true)
        ? request('tab')
        : 'organ-systems';
    $focusId = request()->filled('focus') ? (int) request('focus') : null;
@endphp

<x-layouts.admin title="Danh mục kiến thức">
    <x-admin.page-header title="Danh mục kiến thức"
        description="DAG nhiều-nhiều: hệ cơ quan ↔ môn học ↔ bài học. Một môn gắn nhiều hệ; một bài gắn nhiều môn. Câu hỏi chỉ gắn bài học.">
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'curriculum'])

    <x-admin.flash />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-error/40 bg-error/5 px-4 py-3 text-sm text-error">
            <p class="mb-1 font-semibold">Vui lòng kiểm tra lại:</p>
            <ul class="list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Hệ cơ quan" :value="number_format($stats['organ_systems'])" hint="Một hệ xuất hiện ở nhiều môn" icon="account_tree" />
        <x-admin.kpi-card label="Môn học" :value="number_format($stats['subjects'])" hint="Một môn gắn nhiều hệ cơ quan" icon="folder" />
        <x-admin.kpi-card label="Bài học" :value="number_format($stats['lessons'])" hint="Một bài gắn nhiều môn — câu hỏi gắn bài" icon="menu_book" />
    </div>

    <div x-data="{
            tab: @js($activeTab),
            editing: null,
            attaching: null,
            init() {
                @if ($focusId)
                    this.$nextTick(() => {
                        const el = document.getElementById('node-' + this.tab + '-{{ $focusId }}');
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                @endif
            },
        }"
        x-init="init()">

        {{-- Tab bar --}}
        <div class="mb-5 inline-flex rounded-xl border border-outline-variant bg-surface p-1">
            @foreach ($tabs as $tab)
                <button type="button"
                    @click="tab = '{{ $tab['key'] }}'; editing = null; attaching = null;"
                    :class="tab === '{{ $tab['key'] }}'
                        ? 'bg-primary-container text-on-primary-container font-semibold'
                        : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface'"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 font-label-sm transition-colors">
                    <span class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
                    {{ $tab['label'] }}
                    <span class="rounded-md bg-surface-container px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-on-surface-variant">{{ number_format($tab['count']) }}</span>
                </button>
            @endforeach
        </div>

        <div x-show="tab === 'organ-systems'" x-cloak>
            @include('admin::curriculum._organ-systems')
        </div>

        <div x-show="tab === 'subjects'" x-cloak>
            @include('admin::curriculum._subjects')
        </div>

        <div x-show="tab === 'lessons'" x-cloak>
            @include('admin::curriculum._lessons')
        </div>
    </div>
</x-layouts.admin>
