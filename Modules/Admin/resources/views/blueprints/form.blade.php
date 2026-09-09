@php $isNew = ! $blueprint->exists; @endphp
<x-layouts.admin :title="$isNew ? 'Tạo ma trận đề thi' : 'Sửa ma trận đề thi'">
    <x-admin.page-header :title="$isNew ? 'Tạo ma trận đề thi' : $blueprint->name"
        :description="$isNew ? 'Tạo ma trận mới, sau đó thêm các phần, chủ đề lâm sàng và map sang bài học.' : 'Chỉnh metadata, phần, chủ đề lâm sàng. Map CCT ↔ bài học để câu hỏi (đã gắn bài học) tự khớp ma trận — không gắn câu hỏi trực tiếp.'">
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
        <div
            class="mt-8 space-y-6"
            x-data="{
                confirmOpen: false,
                confirmTitle: '',
                confirmMessage: '',
                confirmAction: '',
                confirmLabel: 'Xóa',
                openConfirm({ title, message, action, label }) {
                    this.confirmTitle = title;
                    this.confirmMessage = message;
                    this.confirmAction = action;
                    this.confirmLabel = label || 'Xóa';
                    this.confirmOpen = true;
                    this.$nextTick(() => this.$refs.confirmCancel?.focus());
                },
                closeConfirm() {
                    this.confirmOpen = false;
                },
            }"
            @keydown.escape.window="confirmOpen && closeConfirm()"
            @blueprint-confirm="openConfirm($event.detail)"
        >
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
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-surface-container-high px-2.5 py-1 text-xs font-semibold">{{ $section->coreClinicalTopics->count() }} chủ đề</span>
                            @if ($canDelete)
                                <button
                                    type="button"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-error/20 px-2.5 text-xs font-semibold text-error transition hover:bg-error/5"
                                    @click="openConfirm({
                                        title: 'Xóa phần này?',
                                        message: @js('Phần «'.$section->name.'» và '.$section->coreClinicalTopics->count().' chủ đề lâm sàng bên trong sẽ bị xóa vĩnh viễn. Liên kết bài học và tag cũng bị gỡ.'),
                                        action: @js(route('admin.blueprint-sections.destroy', $section)),
                                        label: 'Xóa phần',
                                    })"
                                >
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                                    Xóa phần
                                </button>
                            @endif
                        </div>
                    </div>

                    <ul class="mb-4 space-y-3 text-sm">
                        @forelse ($section->coreClinicalTopics as $topic)
                            @php
                                $topicLessons = $topic->lessons->map(fn ($l) => [
                                    'id' => (int) $l->id,
                                    'name' => $l->name,
                                ])->values()->all();
                                $topicTags = $topic->tags->map(fn ($t) => [
                                    'id' => (int) $t->id,
                                    'name' => $t->name,
                                ])->values()->all();
                            @endphp
                            <li
                                class="rounded-lg border border-outline-variant/60 p-3"
                                x-data="blueprintTopicLinkMapper({
                                    lessonLookupUrl: @js(route('admin.taxonomy.lookups.lessons')),
                                    tagLookupUrl: @js(route('admin.taxonomy.lookups.tags')),
                                    syncUrl: @js(route('admin.core-clinical-topics.medical-nodes.sync', $topic)),
                                    csrfToken: @js(csrf_token()),
                                    canUpdate: @js((bool) $canUpdate),
                                    initialLessons: @js(collect($topicLessons)->keyBy('id')->all()),
                                    initialTags: @js(collect($topicTags)->keyBy('id')->all()),
                                })"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <button type="button" @click="open = !open" class="text-left font-medium hover:text-primary">
                                        <span class="text-on-surface-variant">{{ $topic->sort_order }}.</span> {{ $topic->name }}
                                    </button>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span
                                            class="rounded bg-secondary-container px-2 py-0.5 text-on-secondary-container"
                                            x-text="linkCountLabel()"
                                        >{{ count($topicLessons) + count($topicTags) }} liên kết</span>
                                        @if ($canUpdate)
                                            <button type="button" @click="open = !open" class="font-semibold text-primary" x-text="open ? 'Đóng' : 'Liên kết'"></button>
                                        @endif
                                        @if ($canDelete)
                                            <button
                                                type="button"
                                                class="inline-flex size-8 items-center justify-center rounded-lg text-error/80 transition hover:bg-error/5 hover:text-error"
                                                aria-label="Xóa chủ đề {{ $topic->name }}"
                                                @click="$dispatch('blueprint-confirm', {
                                                    title: 'Xóa chủ đề lâm sàng?',
                                                    message: @js('Chủ đề «'.$topic->name.'» sẽ bị xóa vĩnh viễn. Liên kết bài học và tag của chủ đề này cũng bị gỡ.'),
                                                    action: @js(route('admin.core-clinical-topics.destroy', $topic)),
                                                    label: 'Xóa chủ đề',
                                                })"
                                            >
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Preview chips when collapsed --}}
                                <div
                                    x-show="!open && (selectedLessonIds.length > 0 || selectedTagIds.length > 0)"
                                    class="mt-2 flex flex-wrap gap-1.5"
                                >
                                    <template x-for="id in selectedLessonIds" :key="'preview-lesson-'+id">
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-surface-container px-2 py-0.5 text-xs text-on-surface">
                                            <span class="truncate" x-text="selectedLessons[id]?.name || ('#'+id)"></span>
                                        </span>
                                    </template>
                                    <template x-for="id in selectedTagIds" :key="'preview-tag-'+id">
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-surface-container px-2 py-0.5 text-xs text-on-surface">
                                            <span class="material-symbols-outlined text-[12px] text-on-surface-variant" aria-hidden="true">sell</span>
                                            <span class="truncate" x-text="selectedTags[id]?.name || ('#'+id)"></span>
                                        </span>
                                    </template>
                                </div>

                                <div x-show="open" x-cloak class="mt-3 space-y-3 border-t border-outline-variant/60 pt-3">
                                    <p class="text-xs text-on-surface-variant">Map bài học hoặc tag cho chủ đề này. Câu hỏi đã gắn các bài học/tag đó sẽ tự khớp ma trận — không cần gắn lại từng câu.</p>

                                    {{-- Selected links --}}
                                    <div x-show="selectedLessonIds.length > 0 || selectedTagIds.length > 0" class="flex flex-wrap gap-1.5">
                                        <template x-for="id in selectedLessonIds" :key="'chip-lesson-'+id">
                                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                                <span class="truncate" x-text="selectedLessons[id]?.name || ('#'+id)"></span>
                                                @if ($canUpdate)
                                                    <button
                                                        type="button"
                                                        @click="removeLesson(id)"
                                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-primary/70 transition hover:bg-primary/15 hover:text-primary"
                                                        :aria-label="'Xóa ' + (selectedLessons[id]?.name || id)"
                                                    >
                                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">close</span>
                                                    </button>
                                                @endif
                                            </span>
                                        </template>
                                        <template x-for="id in selectedTagIds" :key="'chip-tag-'+id">
                                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg bg-secondary-container px-2 py-1 text-xs font-medium text-on-secondary-container">
                                                <span class="material-symbols-outlined text-[12px]" aria-hidden="true">sell</span>
                                                <span class="truncate" x-text="selectedTags[id]?.name || ('#'+id)"></span>
                                                <span class="shrink-0 text-[10px] font-normal opacity-70">Tag</span>
                                                @if ($canUpdate)
                                                    <button
                                                        type="button"
                                                        @click="removeTag(id)"
                                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md opacity-70 transition hover:bg-on-secondary-container/10 hover:opacity-100"
                                                        :aria-label="'Xóa tag ' + (selectedTags[id]?.name || id)"
                                                    >
                                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">close</span>
                                                    </button>
                                                @endif
                                            </span>
                                        </template>
                                    </div>

                                    @if ($canUpdate)
                                        <div class="flex gap-1 rounded-lg bg-surface-container-low p-1">
                                            <button
                                                type="button"
                                                @click="linkMode = 'lesson'; clearSearch()"
                                                class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                                :class="linkMode === 'lesson' ? 'bg-surface text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                                            >Bài học</button>
                                            <button
                                                type="button"
                                                @click="linkMode = 'tag'; clearSearch()"
                                                class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                                :class="linkMode === 'tag' ? 'bg-surface text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                                            >Tag</button>
                                        </div>

                                        <div class="relative">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-on-surface-variant" aria-hidden="true">
                                                    <span class="material-symbols-outlined text-[18px]">search</span>
                                                </span>
                                                <input
                                                    type="search"
                                                    x-model="searchQuery"
                                                    @input.debounce.300ms="runSearch()"
                                                    @keydown.enter.prevent="addFirstResult()"
                                                    @keydown.escape.prevent="clearSearch()"
                                                    :placeholder="linkMode === 'tag' ? 'Tìm tag…' : 'Tìm bài học…'"
                                                    autocomplete="off"
                                                    class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                >
                                            </div>

                                            <div
                                                x-show="searchResults.length > 0"
                                                x-cloak
                                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-outline-variant bg-surface py-1 shadow-md"
                                                role="listbox"
                                            >
                                                <template x-for="item in searchResults" :key="linkMode + '-' + item.id">
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        @click="addResult(item)"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-surface-container-low"
                                                        :class="isResultSelected(item.id) && 'bg-primary/5'"
                                                    >
                                                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant" aria-hidden="true"
                                                              x-text="isResultSelected(item.id) ? 'check_circle' : 'add_circle'"></span>
                                                        <span class="min-w-0 flex-1 truncate font-medium" x-text="item.name"></span>
                                                        <span
                                                            class="shrink-0 text-[10px] uppercase text-on-surface-variant"
                                                            x-text="linkMode === 'tag' ? 'Tag' : 'Bài học'"
                                                        ></span>
                                                    </button>
                                                </template>
                                            </div>

                                            <p
                                                x-show="searchQuery.trim().length > 0 && !searching && searchResults.length === 0"
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

            {{-- Confirm delete dialog --}}
            <div
                x-show="confirmOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="presentation"
            >
                <div
                    class="absolute inset-0 bg-on-surface/40 backdrop-blur-[2px]"
                    @click="closeConfirm()"
                    aria-hidden="true"
                ></div>
                <div
                    class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-6 shadow-xl"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="blueprint-confirm-title"
                    aria-describedby="blueprint-confirm-desc"
                    @click.stop
                >
                    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-error-container/50 text-error">
                        <span class="material-symbols-outlined text-[28px]" aria-hidden="true">delete</span>
                    </div>
                    <h2 id="blueprint-confirm-title" class="font-headline-sm text-headline-sm text-on-surface" x-text="confirmTitle"></h2>
                    <p id="blueprint-confirm-desc" class="mt-2 font-body-sm leading-6 text-on-surface-variant" x-text="confirmMessage"></p>
                    <form method="post" :action="confirmAction" class="mt-6 flex flex-wrap justify-end gap-3">
                        @csrf
                        @method('DELETE')
                        <button
                            type="button"
                            x-ref="confirmCancel"
                            @click="closeConfirm()"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-outline-variant px-4 font-label-md font-semibold text-on-surface-variant transition hover:bg-surface-container-low"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-error px-4 font-label-md font-semibold text-on-error transition hover:opacity-90"
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                            <span x-text="confirmLabel">Xóa</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @once
        <script>
            function blueprintTopicLinkMapper(config) {
                const initialLessons = { ...(config.initialLessons || {}) };
                const initialLessonIds = Object.keys(initialLessons).map((id) => Number(id)).sort((a, b) => a - b);
                const initialTags = { ...(config.initialTags || {}) };
                const initialTagIds = Object.keys(initialTags).map((id) => Number(id)).sort((a, b) => a - b);

                return {
                    open: false,
                    linkMode: 'lesson',
                    searchQuery: '',
                    searchResults: [],
                    searching: false,
                    saving: false,
                    statusMessage: '',
                    statusError: false,
                    canUpdate: Boolean(config.canUpdate),
                    lessonLookupUrl: config.lessonLookupUrl,
                    tagLookupUrl: config.tagLookupUrl,
                    syncUrl: config.syncUrl,
                    csrfToken: config.csrfToken,
                    selectedLessons: { ...initialLessons },
                    selectedLessonIds: [...initialLessonIds],
                    savedLessonIds: [...initialLessonIds],
                    selectedTags: { ...initialTags },
                    selectedTagIds: [...initialTagIds],
                    savedTagIds: [...initialTagIds],

                    get isDirty() {
                        return ! this.sameIdList(this.selectedLessonIds, this.savedLessonIds)
                            || ! this.sameIdList(this.selectedTagIds, this.savedTagIds);
                    },

                    sameIdList(a, b) {
                        const left = [...a].map(Number).sort((x, y) => x - y);
                        const right = [...b].map(Number).sort((x, y) => x - y);
                        if (left.length !== right.length) {
                            return false;
                        }

                        return left.every((id, index) => id === right[index]);
                    },

                    linkCountLabel() {
                        const total = this.selectedLessonIds.length + this.selectedTagIds.length;

                        return total + ' liên kết';
                    },

                    isResultSelected(id) {
                        const value = Number(id);

                        return this.linkMode === 'tag'
                            ? this.selectedTagIds.includes(value)
                            : this.selectedLessonIds.includes(value);
                    },

                    clearSearch() {
                        this.searchQuery = '';
                        this.searchResults = [];
                    },

                    async runSearch() {
                        const q = this.searchQuery.trim();
                        if (q.length < 1) {
                            this.searchResults = [];
                            this.searching = false;
                            return;
                        }

                        this.searching = true;
                        const url = this.linkMode === 'tag' ? this.tagLookupUrl : this.lessonLookupUrl;
                        try {
                            const response = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                                headers: { Accept: 'application/json' },
                            });
                            const json = await response.json();
                            this.searchResults = json.data ?? [];
                        } catch {
                            this.searchResults = [];
                        } finally {
                            this.searching = false;
                        }
                    },

                    addFirstResult() {
                        if (this.searchResults.length) {
                            this.addResult(this.searchResults[0]);
                        }
                    },

                    addResult(item) {
                        if (this.linkMode === 'tag') {
                            this.addTag(item);
                            return;
                        }

                        this.addLesson(item);
                    },

                    addLesson(lesson) {
                        const id = Number(lesson.id);
                        if (this.selectedLessonIds.includes(id)) {
                            this.removeLesson(id);
                            return;
                        }

                        this.selectedLessonIds.push(id);
                        this.selectedLessons[id] = {
                            id,
                            name: lesson.name,
                        };
                        this.statusMessage = '';
                        this.clearSearch();
                    },

                    removeLesson(id) {
                        const lessonId = Number(id);
                        this.selectedLessonIds = this.selectedLessonIds.filter((item) => item !== lessonId);
                        delete this.selectedLessons[lessonId];
                        this.statusMessage = '';
                    },

                    addTag(tag) {
                        const id = Number(tag.id);
                        if (this.selectedTagIds.includes(id)) {
                            this.removeTag(id);
                            return;
                        }

                        this.selectedTagIds.push(id);
                        this.selectedTags[id] = {
                            id,
                            name: tag.name,
                        };
                        this.statusMessage = '';
                        this.clearSearch();
                    },

                    removeTag(id) {
                        const tagId = Number(id);
                        this.selectedTagIds = this.selectedTagIds.filter((item) => item !== tagId);
                        delete this.selectedTags[tagId];
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
                            this.selectedLessonIds.forEach((id) => {
                                body.append('lesson_ids[]', String(id));
                            });
                            this.selectedTagIds.forEach((id) => {
                                body.append('tag_ids[]', String(id));
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

                            this.savedLessonIds = [...this.selectedLessonIds].map(Number).sort((a, b) => a - b);
                            this.savedTagIds = [...this.selectedTagIds].map(Number).sort((a, b) => a - b);
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
