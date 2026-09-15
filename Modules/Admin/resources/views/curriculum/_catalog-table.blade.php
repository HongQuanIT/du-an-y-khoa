{{--
    Shared SaaS catalog table for organ systems / subjects.
    Expected: $catalogKey, $catalogLabel, $catalogSingular, $items, $storeRoute,
    $updateRoute, $destroyRoute, $namePlaceholder, $emptyHint
--}}
@php
    $editingId = (int) old('_editing_id', 0);
    $reopenPanel = old('_catalog') === $catalogKey && $errors->isNotEmpty()
        ? ($editingId > 0 ? 'edit' : 'create')
        : null;
    $reopenUpdateUrl = $editingId > 0
        ? route($updateRoute, $editingId)
        : '';
@endphp

<div class="space-y-4"
     x-data="taxonomyCatalog({
        key: @js($catalogKey),
        indexUrl: @js(route('admin.curriculum.index')),
        items: @js($catalogItems),
        meta: @js($catalogMeta),
        query: @js($filters['q'] ?? ''),
        status: @js($filters['status'] ?? 'all'),
        dir: @js($filters['dir'] ?? 'asc'),
        focusId: @js($focusId),
        storeUrl: @js($storeRoute),
        canCreate: @js($canCreate),
        canUpdate: @js($canUpdate),
        canDelete: @js($canDelete),
        reopenPanel: @js($reopenPanel),
        fieldErrors: @js([
            'name' => $reopenPanel ? $errors->first('name') : '',
            'slug' => $reopenPanel ? $errors->first('slug') : '',
        ]),
        oldForm: @js([
            'id' => $editingId ?: null,
            'update_url' => $reopenUpdateUrl,
            'name' => old('name', ''),
            'slug' => old('slug', ''),
            'description' => old('description', ''),
            'status' => old('status', 'active'),
        ]),
     })">
    <div class="flex flex-col gap-3 rounded-xl border border-outline-variant bg-surface p-4 lg:flex-row lg:items-center">
        <div class="relative min-w-0 flex-1">
            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
            <input type="search" x-model="query" @input="applySearch()"
                placeholder="Tìm {{ $catalogSingular }} theo tên hoặc đường dẫn…"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <select x-model="statusFilter" @change="changeFilter()"
            class="h-11 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            <option value="all">Mọi trạng thái</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ $statusLabels[$status->value] ?? $status->value }}</option>
            @endforeach
        </select>
        <p class="text-xs tabular-nums text-on-surface-variant lg:min-w-[7rem]">
            <span x-text="total"></span> mục
        </p>
        @if ($canCreate)
            <button type="button" @click="openCreate()"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary hover:opacity-90">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Thêm {{ $catalogSingular }}
            </button>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface" :class="loading && 'opacity-70'">
        <div class="w-full overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                <caption class="sr-only">Danh sách {{ $catalogLabel }}</caption>
                <thead class="border-b border-outline-variant bg-surface-container-low text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">
                    <tr>
                        <th scope="col" class="px-5 py-3">
                            <button type="button" @click="toggleNameSort()"
                                class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-on-surface">
                                Tên
                                <span class="material-symbols-outlined text-[16px]"
                                    x-text="nameSort === 'asc' ? 'arrow_upward' : 'arrow_downward'"></span>
                            </button>
                        </th>
                        <th scope="col" class="w-[130px] px-4 py-3">Trạng thái</th>
                        <th scope="col" class="w-[100px] px-4 py-3 text-right">Bài học</th>
                        <th scope="col" class="w-[160px] px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    <template x-for="item in items" :key="catalogKey + '-' + item.id">
                        <tr class="transition-colors hover:bg-surface-container-low"
                            :id="'node-' + catalogKey + '-' + item.id"
                            :class="focusId === item.id ? 'bg-primary/5' : ''">
                            <td class="px-5 py-3.5 align-middle">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-on-surface" x-text="item.name" :title="item.name"></p>
                                    <p class="truncate font-mono text-[11px] text-on-surface-variant" x-text="item.slug"></p>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                    :class="item.status === 'active'
                                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                    x-text="item.status === 'active' ? 'Đang dùng' : 'Ngừng dùng'"></span>
                            </td>
                            <td class="px-4 py-3.5 text-right align-middle tabular-nums text-on-surface" x-text="item.lessons_count"></td>
                            <td class="px-5 py-3.5 text-right align-middle">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button" x-show="canUpdate" @click="openEdit(item)"
                                        class="inline-flex h-8 items-center rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface hover:bg-surface-container-low">
                                        Sửa
                                    </button>
                                    <button type="button" x-show="canDelete" @click="confirming = item"
                                        class="inline-flex h-8 items-center rounded-lg px-2.5 text-xs font-medium text-error hover:bg-error/10">
                                        Xoá
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="items.length === 0" class="px-5 py-14 text-center">
            <span class="material-symbols-outlined mb-2 text-[32px] text-on-surface-variant/50">folder_off</span>
            <p class="text-sm font-medium text-on-surface">Không có {{ $catalogSingular }} phù hợp.</p>
            <p class="mt-1 text-xs text-on-surface-variant">{{ $emptyHint }}</p>
        </div>

        @include('admin::curriculum._catalog-pager')
    </div>

    {{-- Create / edit drawer --}}
    <template x-teleport="body">
        <div x-show="panel !== null" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-on-surface/40" @click="closePanel()"></div>
            <aside class="relative flex h-full w-full max-w-md flex-col bg-surface shadow-2xl"
                @keydown.escape.window="closePanel()">
                <div class="flex items-center justify-between border-b border-outline-variant px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-on-surface" x-text="panel === 'create' ? 'Thêm {{ $catalogSingular }}' : 'Sửa {{ $catalogSingular }}'"></h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">Liên kết với bài học được quản lý ở tab Bài học.</p>
                    </div>
                    <button type="button" @click="closePanel()" class="rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form :action="formAction" method="post" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <input type="hidden" name="_method" :value="panel === 'edit' ? 'PUT' : 'POST'">
                    <input type="hidden" name="_catalog" :value="catalogKey">
                    <input type="hidden" name="_editing_id" :value="panel === 'edit' && form.id ? form.id : ''">
                    <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Tên *</label>
                            <input name="name" x-model="form.name" required maxlength="255" :placeholder="@js($namePlaceholder)"
                                class="h-11 w-full rounded-lg border bg-surface-container-lowest px-3 text-sm outline-none focus:ring-2"
                                :class="fieldErrors.name ? 'border-error focus:border-error focus:ring-error/20' : 'border-outline-variant focus:border-primary focus:ring-primary/20'">
                            <p x-show="fieldErrors.name" x-text="fieldErrors.name" class="mt-1.5 text-xs text-error"></p>
                        </div>
                        <div x-show="panel === 'edit'">
                            <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Đường dẫn định danh</label>
                            <input name="slug" x-model="form.slug"
                                class="h-11 w-full rounded-lg border bg-surface-container-lowest px-3 font-mono text-sm outline-none focus:ring-2"
                                :class="fieldErrors.slug ? 'border-error focus:border-error focus:ring-error/20' : 'border-outline-variant focus:border-primary focus:ring-primary/20'">
                            <p x-show="fieldErrors.slug" x-text="fieldErrors.slug" class="mt-1.5 text-xs text-error"></p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Trạng thái</label>
                            <select name="status" x-model="form.status"
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $statusLabels[$status->value] ?? $status->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">Mô tả</label>
                            <textarea name="description" x-model="form.description" rows="3" maxlength="2000" placeholder="Tùy chọn"
                                class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-outline-variant px-5 py-4">
                        <button type="button" @click="closePanel()"
                            class="h-10 rounded-lg px-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
                        <button type="submit"
                            class="h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary">
                            <span x-text="panel === 'create' ? 'Thêm {{ $catalogSingular }}' : 'Lưu thay đổi'"></span>
                        </button>
                    </div>
                </form>
            </aside>
        </div>
    </template>

    {{-- Delete confirm --}}
    <template x-teleport="body">
        <div x-show="confirming" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-on-surface/40" @click="confirming = null"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl">
                <h3 class="text-base font-semibold text-on-surface">Xoá {{ $catalogSingular }}?</h3>
                <p class="mt-2 text-sm text-on-surface-variant">
                    «<span x-text="confirming?.name"></span>» sẽ bị gỡ khỏi danh mục.
                    Nếu còn bài học được gắn với {{ $catalogSingular }} này, hãy mở bài học đó, chọn thêm {{ $catalogSingular }} khác, rồi quay lại xoá.
                </p>
                <form x-show="confirming" :action="confirming?.destroy_url" method="post" class="mt-5 flex justify-end gap-2">
                    @csrf @method('DELETE')
                    <button type="button" @click="confirming = null"
                        class="h-10 rounded-lg px-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
                    <button type="submit"
                        class="h-10 rounded-lg bg-error px-4 text-sm font-semibold text-white hover:opacity-90">Xoá</button>
                </form>
            </div>
        </div>
    </template>
</div>
