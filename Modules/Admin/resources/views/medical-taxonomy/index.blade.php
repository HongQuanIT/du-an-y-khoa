@php
    $typeBadge = static function (?string $type, array $labels): array {
        $label = $labels[$type] ?? ($type ?: 'Khác');
        $class = match ($type) {
            'system' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
            'specialty' => 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200',
            'disease', 'condition' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200',
            'symptom' => 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
            'sign' => 'bg-orange-50 text-orange-900 dark:bg-orange-950/40 dark:text-orange-200',
            'clinical_finding', 'lab_finding', 'imaging_finding' => 'bg-cyan-50 text-cyan-900 dark:bg-cyan-950/40 dark:text-cyan-200',
            'concept' => 'bg-violet-50 text-violet-900 dark:bg-violet-950/40 dark:text-violet-200',
            'procedure' => 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
            'drug' => 'bg-teal-50 text-teal-900 dark:bg-teal-950/40 dark:text-teal-200',
            default => 'bg-surface-container-high text-on-surface-variant',
        };

        return ['label' => $label, 'class' => $class];
    };
@endphp

<x-layouts.admin title="Danh mục y khoa">
    <x-admin.page-header title="Danh mục y khoa"
        description="Cây kiến thức y khoa — phân cấp theo hệ cơ quan, chuyên khoa, bệnh; đồng thời quản lý triệu chứng, dấu hiệu, cận lâm sàng và khái niệm để gắn câu hỏi.">
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'medical'])

    <x-admin.flash />

    @if ($taxonomy === null)
        <div class="rounded-xl border border-outline-variant bg-surface p-8 text-center text-on-surface-variant">
            Chưa có danh mục y khoa. Vui lòng chạy migration và seeder dữ liệu taxonomy.
        </div>
    @else
            {{-- Hướng dẫn kiến trúc --}}
            <div class="mb-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3">
                    <p class="flex items-center gap-2 font-label-md font-semibold text-on-surface">
                        <span class="material-symbols-outlined text-[20px] text-primary">account_tree</span>
                        Cây phân cấp kiến thức
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-on-surface-variant">
                        Dùng quan hệ <strong class="text-on-surface">cha → con</strong> để mô tả lộ trình kiến thức:
                        <span class="text-on-surface">Hệ cơ quan → Chuyên khoa → Bệnh / Hội chứng</span>.
                        Ví dụ: Hệ tim mạch → Tim mạch → Bệnh động mạch vành → STEMI.
                    </p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface px-4 py-3">
                    <p class="flex items-center gap-2 font-label-md font-semibold text-on-surface">
                        <span class="material-symbols-outlined text-[20px] text-on-surface-variant">hub</span>
                        Mục liên kết chéo
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-on-surface-variant">
                        <strong class="text-on-surface">Triệu chứng, dấu hiệu, xét nghiệm, khái niệm</strong> thường đứng ở cấp gốc
                        và được gắn vào câu hỏi (không nhất thiết nằm sâu trong cây bệnh).
                        Tách biệt với ma trận đề thi 128 chủ đề lâm sàng.
                    </p>
                </div>
            </div>

            {{-- Thống kê theo nhóm loại --}}
            <div class="mb-4 rounded-xl border border-outline-variant bg-surface px-4 py-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-label-md font-semibold text-on-surface">{{ $taxonomy->name }}</p>
                        <p class="mt-0.5 text-sm text-on-surface-variant">
                            Tổng <span class="font-semibold text-on-surface tabular-nums">{{ $flatNodes->count() }}</span> mục
                        </p>
                    </div>
                </div>

                @if ($groupedTypeStats !== [])
                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($groupedTypeStats as $groupKey => $group)
                            <div class="rounded-lg border border-outline-variant/70 bg-surface-container-lowest px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px] text-primary">{{ $group['icon'] }}</span>
                                    <p class="text-sm font-semibold text-on-surface">{{ $group['label'] }}</p>
                                    <span class="ml-auto rounded-md bg-surface-container px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-on-surface-variant">{{ $group['count'] }}</span>
                                </div>
                                <p class="mt-1 text-[11px] leading-4 text-on-surface-variant">{{ $group['description'] }}</p>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($group['types'] as $type => $count)
                                        @php $badge = $typeBadge($type, $nodeTypeLabels); @endphp
                                        <a href="{{ route('admin.medical-taxonomy.index', array_filter([
                                                'node_type' => $filters['node_type'] === $type ? null : $type,
                                                'q' => $filters['q'] ?: null,
                                            ])) }}"
                                            class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold {{ $badge['class'] }}
                                                {{ $filters['node_type'] === $type ? 'ring-2 ring-primary ring-offset-1' : '' }}">
                                            {{ $badge['label'] }}
                                            <span class="tabular-nums opacity-80">{{ $count }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Bộ lọc --}}
            <form method="get" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="min-w-[220px] flex-1">
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="mt-q">Tìm mục</label>
                    <input id="mt-q" name="q" value="{{ $filters['q'] }}" placeholder="Tên mục kiến thức…"
                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                </div>
                <div class="w-full sm:w-56">
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="mt-type">Loại mục</label>
                    <select id="mt-type" name="node_type"
                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Tất cả loại</option>
                        @foreach ($typeGroups as $group)
                            <optgroup label="{{ $group['label'] }}">
                                @foreach ($group['types'] as $type)
                                    <option value="{{ $type }}" @selected($filters['node_type'] === $type)>
                                        {{ $nodeTypeLabels[$type] ?? $type }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <button class="h-10 rounded-xl border border-outline-variant px-4 text-sm font-semibold hover:bg-surface-container-low">Lọc</button>
                @if ($isFiltered)
                    <a href="{{ route('admin.medical-taxonomy.index') }}"
                        class="inline-flex h-10 items-center rounded-xl px-3 text-sm text-on-surface-variant hover:text-primary">Xóa bộ lọc</a>
                @endif
            </form>

            {{-- Danh sách / cây --}}
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface"
                x-data="medicalTaxonomyTree(@js([
                    'focusId' => $filters['focus'],
                    'expandAll' => $isFiltered,
                    'defaultOpenIds' => $defaultOpenIds,
                    'idsWithChildren' => $flatNodes->filter(fn ($n) => $n->children_count > 0)->pluck('id')->values()->all(),
                ]))"
                x-init="init()">

                @if ($isFiltered)
                    <div class="flex items-center justify-between border-b border-outline-variant bg-surface-container-low px-4 py-2.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Kết quả tìm kiếm / lọc</p>
                    </div>
                    @if ($tree === [])
                        <div class="px-4 py-12 text-center text-sm text-on-surface-variant">Không tìm thấy mục phù hợp.</div>
                    @else
                        <ul class="divide-y divide-outline-variant/70">
                            @include('admin::medical-taxonomy._tree-nodes', [
                                'items' => $tree,
                                'typeBadge' => $typeBadge,
                                'nodeTypeLabels' => $nodeTypeLabels,
                                'statusLabels' => $statusLabels,
                                'parentOptions' => $parentOptions,
                                'typeGroups' => $typeGroups,
                                'statuses' => $statuses,
                                'canUpdate' => $canUpdate,
                                'canCreate' => $canCreate,
                                'taxonomy' => $taxonomy,
                                'isFiltered' => true,
                            ])
                        </ul>
                    @endif
                @else
                    {{-- Phần 1: Cây khung kiến thức --}}
                    <div class="border-b border-outline-variant">
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-surface-container-low px-4 py-2.5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface">Khung kiến thức</p>
                                <p class="text-[11px] text-on-surface-variant">Hệ cơ quan → Chuyên khoa → Bệnh / Hội chứng</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="expandAllNodes()"
                                    class="rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10">Mở hết</button>
                                <button type="button" @click="collapseAll()"
                                    class="rounded-lg px-2 py-1 text-xs font-semibold text-on-surface-variant hover:bg-surface-container">Thu gọn</button>
                            </div>
                        </div>
                        @if ($structureTree === [])
                            <div class="px-4 py-8 text-center text-sm text-on-surface-variant">Chưa có mục khung kiến thức.</div>
                        @else
                            <ul class="divide-y divide-outline-variant/70">
                                @include('admin::medical-taxonomy._tree-nodes', [
                                    'items' => $structureTree,
                                    'typeBadge' => $typeBadge,
                                    'nodeTypeLabels' => $nodeTypeLabels,
                                    'statusLabels' => $statusLabels,
                                    'parentOptions' => $parentOptions,
                                    'typeGroups' => $typeGroups,
                                    'statuses' => $statuses,
                                    'canUpdate' => $canUpdate,
                                    'canCreate' => $canCreate,
                                    'taxonomy' => $taxonomy,
                                    'isFiltered' => false,
                                ])
                            </ul>
                        @endif
                    </div>

                    {{-- Phần 2: Mục liên kết --}}
                    <div>
                        <div class="bg-surface-container-low px-4 py-2.5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface">Mục liên kết chéo</p>
                            <p class="text-[11px] text-on-surface-variant">Triệu chứng · Dấu hiệu · Cận lâm sàng · Khái niệm · Thuốc · Thủ thuật</p>
                        </div>
                        @if ($linkedTree === [])
                            <div class="px-4 py-8 text-center text-sm text-on-surface-variant">Chưa có mục liên kết.</div>
                        @else
                            <ul class="divide-y divide-outline-variant/70">
                                @include('admin::medical-taxonomy._tree-nodes', [
                                    'items' => $linkedTree,
                                    'typeBadge' => $typeBadge,
                                    'nodeTypeLabels' => $nodeTypeLabels,
                                    'statusLabels' => $statusLabels,
                                    'parentOptions' => $parentOptions,
                                    'typeGroups' => $typeGroups,
                                    'statuses' => $statuses,
                                    'canUpdate' => $canUpdate,
                                    'canCreate' => $canCreate,
                                    'taxonomy' => $taxonomy,
                                    'isFiltered' => false,
                                ])
                            </ul>
                        @endif
                    </div>
                @endif
            </div>

            @if ($canCreate)
                <details id="create-panel" class="mt-6 rounded-xl border border-dashed border-outline-variant bg-surface open:border-solid" {{ $flatNodes->isEmpty() ? 'open' : '' }}>
                    <summary class="cursor-pointer list-none px-4 py-3 font-label-md font-semibold text-on-surface marker:content-none">
                        <span class="inline-flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-primary">add_circle</span>
                            Thêm mục mới
                        </span>
                    </summary>
                    <form method="post" action="{{ route('admin.medical-taxonomy.nodes.store') }}" class="space-y-3 border-t border-outline-variant px-4 py-4">
                        @csrf
                        <input type="hidden" name="medical_taxonomy_id" value="{{ $taxonomy->id }}">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Tên mục *</label>
                                <input name="name" required maxlength="255" placeholder="Ví dụ: Hội chứng vành cấp"
                                    class="h-10 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Loại mục</label>
                                <select name="node_type" class="h-10 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                                    <option value="">— Chọn loại —</option>
                                    @foreach ($typeGroups as $group)
                                        <optgroup label="{{ $group['label'] }}">
                                            @foreach ($group['types'] as $type)
                                                <option value="{{ $type }}">{{ $nodeTypeLabels[$type] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mục cha (phân cấp)</label>
                                <select id="create-parent" name="parent_id" class="h-10 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                                    <option value="">— Gốc (không có cha) —</option>
                                    @foreach ($parentOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-on-surface-variant">Chọn mục cha nếu thuộc cây Hệ → Chuyên khoa → Bệnh. Để trống nếu là triệu chứng / khái niệm độc lập.</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Thứ tự hiển thị</label>
                                <input type="number" name="sort_order" min="0" value="0"
                                    class="h-10 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                            </div>
                            <div class="md:col-span-2 lg:col-span-3">
                                <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mô tả ngắn</label>
                                <textarea name="description" rows="2" maxlength="2000"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm"
                                    placeholder="Tùy chọn"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary hover:bg-primary/90">
                            Thêm vào danh mục
                        </button>
                    </form>
                </details>
            @endif
    @endif
</x-layouts.admin>

<script>
    function medicalTaxonomyTree(config) {
        return {
            open: {},
            editingId: null,
            focusId: config.focusId || null,
            init() {
                const ids = config.expandAll
                    ? (config.idsWithChildren || [])
                    : (config.defaultOpenIds || []);
                ids.forEach((id) => { this.open[id] = true; });
                if (this.focusId) {
                    this.$nextTick(() => {
                        const el = document.getElementById('mt-node-' + this.focusId);
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                }
            },
            isOpen(id) { return !!this.open[id]; },
            toggle(id) { this.open[id] = !this.open[id]; },
            expandAllNodes() {
                (config.idsWithChildren || []).forEach((id) => { this.open[id] = true; });
            },
            collapseAll() {
                this.open = {};
            },
        };
    }
</script>
