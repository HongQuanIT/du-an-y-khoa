@foreach ($items as $item)
    @php
        /** @var \Modules\QuestionBank\Models\MedicalTaxonomyNode $node */
        $node = $item['node'];
        $depth = (int) ($item['depth'] ?? 0);
        $children = $item['children'] ?? [];
        $path = $item['path'] ?? null;
        $badge = $typeBadge($node->node_type, $nodeTypeLabels);
        $hasChildren = count($children) > 0;
        $pad = min($depth, 8) * 1.25;
        $statusLabel = $statusLabels[$node->status->value] ?? $node->status->value;
    @endphp

    <li id="mt-node-{{ $node->id }}"
        class="group relative"
        :class="focusId === {{ $node->id }} ? 'bg-primary/5' : ''">
        <div class="flex items-start gap-2 px-3 py-2.5 hover:bg-surface-container-lowest/80 sm:px-4"
            style="padding-left: {{ 0.75 + $pad }}rem">
            <div class="mt-0.5 flex w-7 shrink-0 justify-center">
                @if (! $isFiltered && $hasChildren)
                    <button type="button"
                        @click="toggle({{ $node->id }})"
                        class="flex size-7 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
                        :aria-expanded="isOpen({{ $node->id }}) ? 'true' : 'false'"
                        title="Mở / thu gọn mục con">
                        <span class="material-symbols-outlined text-[20px]"
                            x-text="isOpen({{ $node->id }}) ? 'expand_more' : 'chevron_right'"></span>
                    </button>
                @else
                    <span class="flex size-7 items-center justify-center text-on-surface-variant/40">
                        <span class="material-symbols-outlined text-[16px]">{{ $depth === 0 ? 'folder' : 'subdirectory_arrow_right' }}</span>
                    </span>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-on-surface">{{ $node->name }}</p>
                    <span class="inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-bold {{ $badge['class'] }}">
                        {{ $badge['label'] }}
                    </span>
                    @if ($node->status->value !== 'active')
                        <span class="rounded-md bg-surface-container-high px-1.5 py-0.5 text-[10px] font-semibold text-on-surface-variant">
                            {{ $statusLabel }}
                        </span>
                    @endif
                </div>

                @if ($path)
                    <p class="mt-0.5 text-[11px] text-on-surface-variant">
                        <span class="font-medium">Đường dẫn:</span> {{ $path }}
                    </p>
                @endif

                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-on-surface-variant">
                    @if ($node->children_count > 0)
                        <span>{{ $node->children_count }} mục con</span>
                    @endif
                    @if ($node->questions_count > 0)
                        <span>{{ $node->questions_count }} câu hỏi</span>
                    @endif
                    @if ($node->core_clinical_topics_count > 0)
                        <span>{{ $node->core_clinical_topics_count }} liên kết ma trận đề thi</span>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-1 pt-0.5">
                @if ($canUpdate)
                    <button type="button"
                        @click="editingId = editingId === {{ $node->id }} ? null : {{ $node->id }}"
                        class="rounded-lg px-2 py-1 text-xs font-semibold text-on-surface-variant hover:bg-surface-container hover:text-primary"
                        x-text="editingId === {{ $node->id }} ? 'Đóng' : 'Sửa'"></button>
                @endif
                @if ($canCreate && ! $isFiltered)
                    <a href="#create-panel"
                        @click.prevent="
                            editingId = null;
                            const sel = document.getElementById('create-parent');
                            if (sel) sel.value = '{{ $node->id }}';
                            const panel = document.getElementById('create-panel');
                            if (panel) { panel.setAttribute('open', ''); panel.scrollIntoView({ behavior: 'smooth' }); }
                        "
                        class="text-[11px] font-semibold text-primary hover:underline"
                        title="Thêm mục con bên dưới mục này">+ Mục con</a>
                @endif
            </div>
        </div>

        @if ($canUpdate)
            <div x-show="editingId === {{ $node->id }}" x-cloak
                class="border-t border-outline-variant/50 bg-surface-container-lowest px-4 py-4"
                style="padding-left: {{ 1.5 + $pad }}rem">
                <form method="post" action="{{ route('admin.medical-taxonomy.nodes.update', $node) }}"
                    class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                    @csrf @method('PUT')
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Tên mục</label>
                        <input name="name" value="{{ $node->name }}" required
                            class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Loại mục</label>
                        <select name="node_type" class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                            <option value="">— Chưa phân loại —</option>
                            @foreach ($typeGroups as $group)
                                <optgroup label="{{ $group['label'] }}">
                                    @foreach ($group['types'] as $type)
                                        <option value="{{ $type }}" @selected($node->node_type === $type)>
                                            {{ $nodeTypeLabels[$type] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mã định danh</label>
                        <input name="slug" value="{{ $node->slug }}"
                            class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 font-mono text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mục cha</label>
                        <select name="parent_id" class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                            <option value="">— Gốc —</option>
                            @foreach ($parentOptions as $option)
                                @if ($option['id'] !== $node->id)
                                    <option value="{{ $option['id'] }}" @selected($node->parent_id === $option['id'])>
                                        {{ $option['label'] }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Thứ tự</label>
                        <input type="number" name="sort_order" min="0" value="{{ $node->sort_order }}"
                            class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Trạng thái</label>
                        <select name="status" class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($node->status === $status)>
                                    {{ $statusLabels[$status->value] ?? $status->value }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mô tả</label>
                        <textarea name="description" rows="2" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm">{{ $node->description }}</textarea>
                    </div>
                    <div class="flex items-center gap-2 md:col-span-2 lg:col-span-3">
                        <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-on-primary">Lưu thay đổi</button>
                        <button type="button" @click="editingId = null" class="rounded-xl px-3 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container">Hủy</button>
                    </div>
                </form>
            </div>
        @endif

        @if (! $isFiltered && count($children) > 0)
            <ul x-show="isOpen({{ $node->id }})" x-cloak class="border-t border-outline-variant/40 bg-surface">
                @include('admin::medical-taxonomy._tree-nodes', [
                    'items' => $children,
                    'typeBadge' => $typeBadge,
                    'nodeTypeLabels' => $nodeTypeLabels,
                    'statusLabels' => $statusLabels,
                    'parentOptions' => $parentOptions,
                    'typeGroups' => $typeGroups,
                    'statuses' => $statuses,
                    'canUpdate' => $canUpdate,
                    'canCreate' => $canCreate,
                    'taxonomy' => $taxonomy,
                    'isFiltered' => $isFiltered,
                ])
            </ul>
        @endif
    </li>
@endforeach
