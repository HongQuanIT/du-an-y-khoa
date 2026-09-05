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

    <form method="post" action="{{ $isNew ? route('admin.blueprints.store') : route('admin.blueprints.update', $blueprint) }}" class="max-w-4xl space-y-6">
        @csrf @unless($isNew) @method('PUT') @endunless
        <section class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm" aria-labelledby="blueprint-information-heading">
            <div class="mb-6 flex items-start gap-3 border-b border-outline-variant pb-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                    <span class="material-symbols-outlined text-[22px]">account_tree</span>
                </span>
                <div>
                    <h2 id="blueprint-information-heading" class="font-headline-sm text-headline-sm text-on-surface">Thông tin ma trận</h2>
                    <p class="mt-1 font-body-sm text-on-surface-variant">Thiết lập tên hiển thị, mã nhận diện và thứ tự của ma trận đề thi.</p>
                </div>
            </div>

            <div class="space-y-5">
            <div>
                <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-name">Tên ma trận <span class="text-error">*</span></label>
                <input id="blueprint-name" name="name" value="{{ old('name', $blueprint->name) }}" required
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                @error('name') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-slug">Đường dẫn định danh</label>
                    <input id="blueprint-slug" name="slug" value="{{ old('slug', $blueprint->slug) }}" placeholder="vi-du-ma-tran"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-mono text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                    @error('slug') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-code">Mã nội bộ</label>
                    <input id="blueprint-code" name="code" value="{{ old('code', $blueprint->code) }}" placeholder="medical_practice_licensing_exam"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-mono text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                    @error('code') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-description">Mô tả</label>
                <textarea id="blueprint-description" name="description" rows="4" placeholder="Mô tả phạm vi nội dung, số phần và chủ đề của ma trận…"
                    class="block w-full resize-y rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2.5 font-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>{{ old('description', $blueprint->description) }}</textarea>
                @error('description') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-status">Trạng thái</label>
                    <select id="blueprint-status" name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $blueprint->status?->value) === $status->value)>{{ $status->value === 'active' ? 'Đang hoạt động' : 'Ngừng sử dụng' }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-sort-order">Thứ tự hiển thị</label>
                    <input id="blueprint-sort-order" type="number" name="sort_order" min="0" value="{{ old('sort_order', $blueprint->sort_order ?? 0) }}"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                    @error('sort_order') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($canUpdate)
                <div class="flex items-center justify-end border-t border-outline-variant pt-5">
                    <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-5 font-label-md font-medium text-on-primary transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                        Lưu thay đổi
                    </button>
                </div>
            @endif
            </div>
        </section>
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
                            @php
                                $topicNodes = $topic->medicalTaxonomyNodes->map(fn ($n) => [
                                    'id' => (int) $n->id,
                                    'name' => $n->name,
                                    'node_type' => $n->node_type,
                                ])->values()->all();
                            @endphp
                            <li
                                class="rounded-lg border border-outline-variant/60 p-3"
                                x-data="blueprintTopicNodeMapper({
                                    lookupUrl: @js(route('admin.taxonomy.lookups.medical-nodes')),
                                    syncUrl: @js(route('admin.core-clinical-topics.medical-nodes.sync', $topic)),
                                    csrfToken: @js(csrf_token()),
                                    canUpdate: @js((bool) $canUpdate),
                                    nodeTypeLabels: @js(\Modules\QuestionBank\Support\MedicalTaxonomyNodeTypes::LABELS),
                                    initialNodes: @js(collect($topicNodes)->keyBy('id')->all()),
                                })"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <button type="button" @click="open = !open" class="text-left font-medium hover:text-primary">
                                        <span class="text-on-surface-variant">{{ $topic->sort_order }}.</span> {{ $topic->name }}
                                    </button>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span
                                            class="rounded bg-secondary-container px-2 py-0.5 text-on-secondary-container"
                                            x-text="selectedNodeIds.length + ' node y khoa'"
                                        >{{ count($topicNodes) }} node y khoa</span>
                                        @if ($canUpdate)
                                            <button type="button" @click="open = !open" class="font-semibold text-primary" x-text="open ? 'Đóng' : 'Liên kết danh mục'"></button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Preview tags when collapsed --}}
                                <div
                                    x-show="!open && selectedNodeIds.length > 0"
                                    class="mt-2 flex flex-wrap gap-1.5"
                                >
                                    <template x-for="id in selectedNodeIds" :key="'preview-'+id">
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-surface-container px-2 py-0.5 text-xs text-on-surface">
                                            <span class="truncate" x-text="selectedNodes[id]?.name || ('#'+id)"></span>
                                        </span>
                                    </template>
                                </div>

                                <div x-show="open" x-cloak class="mt-3 space-y-3 border-t border-outline-variant/60 pt-3">
                                    <p class="text-xs text-on-surface-variant">Gán mục danh mục y khoa cho chủ đề lâm sàng này. Tìm và thêm; lưu khi xong.</p>

                                    {{-- Selected tags --}}
                                    <div x-show="selectedNodeIds.length > 0" class="flex flex-wrap gap-1.5">
                                        <template x-for="id in selectedNodeIds" :key="'chip-'+id">
                                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                                <span class="truncate" x-text="selectedNodes[id]?.name || ('#'+id)"></span>
                                                <span
                                                    class="shrink-0 text-[10px] font-normal text-primary/70"
                                                    x-show="selectedNodes[id]?.node_type"
                                                    x-text="nodeTypeLabel(selectedNodes[id]?.node_type)"
                                                ></span>
                                                @if ($canUpdate)
                                                    <button
                                                        type="button"
                                                        @click="removeNode(id)"
                                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-primary/70 transition hover:bg-primary/15 hover:text-primary"
                                                        :aria-label="'Xóa ' + (selectedNodes[id]?.name || id)"
                                                    >
                                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">close</span>
                                                    </button>
                                                @endif
                                            </span>
                                        </template>
                                    </div>

                                    @if ($canUpdate)
                                        <div class="relative">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-on-surface-variant" aria-hidden="true">
                                                    <span class="material-symbols-outlined text-[18px]">search</span>
                                                </span>
                                                <input
                                                    type="search"
                                                    x-model="nodeSearch"
                                                    @input.debounce.300ms="searchNodes()"
                                                    @keydown.enter.prevent="nodeResults.length && addNode(nodeResults[0])"
                                                    @keydown.escape.prevent="clearSearch()"
                                                    placeholder="Tìm mục danh mục y khoa…"
                                                    autocomplete="off"
                                                    class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                >
                                            </div>

                                            <div
                                                x-show="nodeResults.length > 0"
                                                x-cloak
                                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-outline-variant bg-surface py-1 shadow-md"
                                                role="listbox"
                                            >
                                                <template x-for="node in nodeResults" :key="node.id">
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        @click="addNode(node)"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-surface-container-low"
                                                        :class="isSelected(node.id) && 'bg-primary/5'"
                                                    >
                                                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant" aria-hidden="true"
                                                              x-text="isSelected(node.id) ? 'check_circle' : 'add_circle'"></span>
                                                        <span class="min-w-0 flex-1 truncate font-medium" x-text="node.name"></span>
                                                        <span class="shrink-0 text-[10px] uppercase text-on-surface-variant" x-text="nodeTypeLabel(node.node_type)"></span>
                                                    </button>
                                                </template>
                                            </div>

                                            <p
                                                x-show="nodeSearch.trim().length > 0 && !searching && nodeResults.length === 0"
                                                x-cloak
                                                class="mt-1.5 text-xs text-on-surface-variant"
                                            >Không tìm thấy mục phù hợp.</p>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-outline-variant/60 pt-3">
                                            <p
                                                class="mr-auto min-h-[1.25rem] text-xs"
                                                :class="statusError ? 'text-error' : (statusMessage ? 'text-primary' : 'text-on-surface-variant')"
                                                x-text="statusMessage || (isDirty ? 'Có thay đổi chưa lưu' : '')"
                                            ></p>
                                            <button
                                                type="button"
                                                @click="save()"
                                                :disabled="!isDirty || saving"
                                                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3.5 text-xs font-semibold text-on-primary transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true" x-text="saving ? 'progress_activity' : 'save'"></span>
                                                <span x-text="saving ? 'Đang lưu…' : 'Lưu liên kết'"></span>
                                            </button>
                                        </div>
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

    @once
        <script>
            function blueprintTopicNodeMapper(config) {
                const initialNodes = { ...(config.initialNodes || {}) };
                const initialIds = Object.keys(initialNodes).map((id) => Number(id)).sort((a, b) => a - b);

                return {
                    open: false,
                    nodeSearch: '',
                    nodeResults: [],
                    searching: false,
                    saving: false,
                    statusMessage: '',
                    statusError: false,
                    canUpdate: Boolean(config.canUpdate),
                    lookupUrl: config.lookupUrl,
                    syncUrl: config.syncUrl,
                    csrfToken: config.csrfToken,
                    nodeTypeLabels: config.nodeTypeLabels || {},
                    selectedNodes: { ...initialNodes },
                    selectedNodeIds: [...initialIds],
                    savedIds: [...initialIds],

                    get isDirty() {
                        const current = [...this.selectedNodeIds].map(Number).sort((a, b) => a - b);
                        if (current.length !== this.savedIds.length) {
                            return true;
                        }

                        return current.some((id, index) => id !== this.savedIds[index]);
                    },

                    nodeTypeLabel(type) {
                        if (! type) {
                            return '';
                        }

                        return this.nodeTypeLabels[type] || type;
                    },

                    isSelected(id) {
                        return this.selectedNodeIds.includes(Number(id));
                    },

                    clearSearch() {
                        this.nodeSearch = '';
                        this.nodeResults = [];
                    },

                    async searchNodes() {
                        const q = this.nodeSearch.trim();
                        if (q.length < 1) {
                            this.nodeResults = [];
                            this.searching = false;
                            return;
                        }

                        this.searching = true;
                        try {
                            const response = await fetch(`${this.lookupUrl}?q=${encodeURIComponent(q)}`, {
                                headers: { Accept: 'application/json' },
                            });
                            const json = await response.json();
                            this.nodeResults = json.data ?? [];
                        } catch {
                            this.nodeResults = [];
                        } finally {
                            this.searching = false;
                        }
                    },

                    addNode(node) {
                        const id = Number(node.id);
                        if (this.isSelected(id)) {
                            this.removeNode(id);
                            return;
                        }

                        this.selectedNodeIds.push(id);
                        this.selectedNodes[id] = {
                            id,
                            name: node.name,
                            node_type: node.node_type || null,
                        };
                        this.statusMessage = '';
                        this.clearSearch();
                    },

                    removeNode(id) {
                        const nodeId = Number(id);
                        this.selectedNodeIds = this.selectedNodeIds.filter((item) => item !== nodeId);
                        delete this.selectedNodes[nodeId];
                        this.statusMessage = '';
                    },

                    async save() {
                        if (! this.isDirty || this.saving) {
                            return;
                        }

                        this.saving = true;
                        this.statusMessage = '';
                        this.statusError = false;

                        try {
                            const body = new FormData();
                            body.append('_token', this.csrfToken);
                            body.append('_method', 'PUT');
                            this.selectedNodeIds.forEach((id) => {
                                body.append('medical_taxonomy_node_ids[]', String(id));
                            });

                            const response = await fetch(this.syncUrl, {
                                method: 'POST',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body,
                            });

                            if (! response.ok) {
                                throw new Error('save_failed');
                            }

                            this.savedIds = [...this.selectedNodeIds].map(Number).sort((a, b) => a - b);
                            this.statusMessage = 'Đã lưu liên kết.';
                            this.statusError = false;
                        } catch {
                            this.statusMessage = 'Không lưu được. Thử lại.';
                            this.statusError = true;
                        } finally {
                            this.saving = false;
                        }
                    },
                };
            }
        </script>
    @endonce
</x-layouts.admin>
