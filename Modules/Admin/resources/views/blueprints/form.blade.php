@php $isNew = ! $blueprint->exists; @endphp
<x-layouts.admin :title="$isNew ? 'Tạo ma trận đề thi' : 'Sửa ma trận đề thi'">
    <x-admin.page-header :title="$isNew ? 'Tạo ma trận đề thi' : $blueprint->name"
        :description="$isNew ? 'Tạo ma trận mới, sau đó thêm các phần và chủ đề lâm sàng.' : 'Chỉnh metadata, phần, chủ đề lâm sàng và mapping danh mục y khoa.'">
        <x-slot:actions>
            <a href="{{ route('admin.blueprints.index') }}" class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    @unless($isNew)
        @include('admin::taxonomy._sub-nav', ['active' => 'blueprints'])
    @endunless

    <x-admin.flash />

    <form method="post" action="{{ $isNew ? route('admin.blueprints.store') : route('admin.blueprints.update', $blueprint) }}" class="max-w-3xl space-y-6">
        @csrf @unless($isNew) @method('PUT') @endunless
        <div class="space-y-4 rounded-xl border border-outline-variant bg-surface p-5">
            <h2 class="font-label-md font-semibold">Thông tin ma trận</h2>
            <div>
                <label class="mb-1 block text-xs font-semibold">Tên *</label>
                <input name="name" value="{{ old('name', $blueprint->name) }}" required class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold">Slug</label>
                    <input name="slug" value="{{ old('slug', $blueprint->slug) }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold">Code</label>
                    <input name="code" value="{{ old('code', $blueprint->code) }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold">Mô tả</label>
                <textarea name="description" rows="3" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>{{ old('description', $blueprint->description) }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold">Trạng thái</label>
                    <select name="status" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $blueprint->status?->value) === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold">Thứ tự</label>
                    <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $blueprint->sort_order ?? 0) }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
                </div>
            </div>
            @if ($canUpdate)
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-semibold text-on-primary">Lưu ma trận</button>
            @endif
        </div>
    </form>

    @if (! $isNew)
        <div class="mt-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-label-lg font-semibold">Phần & Chủ đề lâm sàng</h2>
                <span class="text-sm text-on-surface-variant">{{ $blueprint->sections->sum(fn ($s) => $s->coreClinicalTopics->count()) }} chủ đề</span>
            </div>

            @foreach ($blueprint->sections as $section)
                <div class="rounded-xl border border-outline-variant bg-surface p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-semibold">{{ $section->name }}</h3>
                            <p class="text-xs text-on-surface-variant">slug: {{ $section->slug }}</p>
                        </div>
                        <span class="rounded-full bg-surface-container-high px-2.5 py-1 text-xs font-semibold">{{ $section->coreClinicalTopics->count() }} chủ đề</span>
                    </div>

                    <ul class="mb-4 space-y-3 text-sm">
                        @forelse ($section->coreClinicalTopics as $topic)
                            <li class="rounded-lg border border-outline-variant/60 p-3" x-data="{ open: false, nodeSearch: '', nodeResults: [], selectedNodeIds: @json($topic->medicalTaxonomyNodes->pluck('id')->all()) }">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <button type="button" @click="open = !open" class="text-left font-medium hover:text-primary">
                                        <span class="text-on-surface-variant">{{ $topic->sort_order }}.</span> {{ $topic->name }}
                                    </button>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="rounded bg-secondary-container px-2 py-0.5 text-on-secondary-container">{{ $topic->medicalTaxonomyNodes->count() }} node y khoa</span>
                                        @if ($canUpdate)
                                            <button type="button" @click="open = !open" class="font-semibold text-primary" x-text="open ? 'Đóng' : 'Mapping'"></button>
                                        @endif
                                    </div>
                                </div>

                                @if ($topic->medicalTaxonomyNodes->isNotEmpty() && ! $canUpdate)
                                    <p class="mt-2 text-xs text-on-surface-variant">
                                        {{ $topic->medicalTaxonomyNodes->pluck('name')->join(', ') }}
                                    </p>
                                @endif

                                <div x-show="open" x-cloak class="mt-3 space-y-2 border-t border-outline-variant/60 pt-3">
                                    <p class="text-xs text-on-surface-variant">Gán node danh mục y khoa cho chủ đề lâm sàng này.</p>
                                    <input type="search" x-model="nodeSearch"
                                        @input.debounce.300ms="fetch(`{{ route('admin.taxonomy.lookups.medical-nodes') }}?q=${encodeURIComponent(nodeSearch)}`).then(r => r.json()).then(j => nodeResults = j.data ?? [])"
                                        placeholder="Tìm node danh mục y khoa..."
                                        class="w-full rounded-lg bg-surface-container-low px-3 py-2 text-sm">
                                    <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
                                        <template x-for="node in nodeResults" :key="node.id">
                                            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-surface-container-low">
                                                <input type="checkbox" :checked="selectedNodeIds.includes(node.id)"
                                                    @change="selectedNodeIds.includes(node.id) ? selectedNodeIds.splice(selectedNodeIds.indexOf(node.id), 1) : selectedNodeIds.push(node.id)"
                                                    class="size-4 rounded text-primary">
                                                <span x-text="node.name" class="text-sm"></span>
                                                <span class="text-[10px] uppercase text-on-surface-variant" x-text="node.node_type || ''"></span>
                                            </label>
                                        </template>
                                    </div>
                                    @if ($canUpdate)
                                        <form method="post" action="{{ route('admin.core-clinical-topics.medical-nodes.sync', $topic) }}">
                                            @csrf @method('PUT')
                                            <template x-for="id in selectedNodeIds" :key="'map-'+id">
                                                <input type="hidden" name="medical_taxonomy_node_ids[]" :value="id">
                                            </template>
                                            <button type="submit" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-on-primary">Lưu mapping</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="text-on-surface-variant">Chưa có chủ đề lâm sàng.</li>
                        @endforelse
                    </ul>

                    @if ($canUpdate)
                        <form method="post" action="{{ route('admin.blueprint-sections.core-topics.store', $section) }}" class="flex flex-wrap gap-2">
                            @csrf
                            <input name="name" placeholder="Tên chủ đề lâm sàng" required class="min-w-[200px] flex-1 rounded-lg bg-surface-container-low px-3 py-2 text-sm">
                            <input type="number" name="sort_order" min="0" value="0" class="w-20 rounded-lg bg-surface-container-low px-2 py-2 text-sm" title="Thứ tự">
                            <button class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-on-primary">Thêm chủ đề</button>
                        </form>
                    @endif
                </div>
            @endforeach

            @if ($canUpdate)
                <form method="post" action="{{ route('admin.blueprints.sections.store', $blueprint) }}" class="flex flex-wrap gap-2 rounded-xl border border-dashed border-outline-variant p-4">
                    @csrf
                    <input name="name" placeholder="Tên phần mới" required class="min-w-[200px] flex-1 rounded-lg bg-surface-container-low px-3 py-2">
                    <input type="number" name="sort_order" min="0" value="0" class="w-20 rounded-lg bg-surface-container-low px-2 py-2" title="Thứ tự">
                    <button class="rounded-lg border border-outline-variant px-4 py-2 font-semibold hover:bg-surface-container-low">Thêm phần</button>
                </form>
            @endif
        </div>
    @endif
</x-layouts.admin>
