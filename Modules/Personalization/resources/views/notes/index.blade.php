<x-layouts.app title="Ghi chú">
    @php
        $isGrouped = ($result['mode'] ?? 'grouped') === 'grouped';
        $total = (int) ($result['total'] ?? 0);
        $groups = $result['groups'] ?? [];
        $flatNotes = $result['notes'] ?? null;
        $currentView = $filters['view'] ?? 'subject';
        $currentQ = $filters['q'] ?? '';
    @endphp

    <div class="mx-auto max-w-[1200px] space-y-6 p-8"
        x-data="notesManager(@js([
            'storeUrl' => route('notes.store'),
            'colors' => $colors,
            'csrf' => csrf_token(),
        ]))">
        <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="space-y-1">
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Ghi chú</h1>
                <p class="text-body-sm text-on-surface-variant">
                    Ghi chú học tập gắn câu hỏi hoặc sổ tay cá nhân, giúp bạn cá nhân hoá ôn tập.
                </p>
            </div>
            <button type="button" @click="openCreate()"
                class="flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2 font-label-md text-white shadow-sm transition-all hover:opacity-90">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Sổ tay
            </button>
        </div>

        <form method="get" action="{{ route('notes.index') }}"
            class="grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-white p-4 md:grid-cols-12 md:items-end">
            <div class="md:col-span-7">
                <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant">Tìm kiếm</label>
                <input type="search" name="q" value="{{ $currentQ }}"
                    placeholder="{{ $currentView === 'notebook' ? 'Tìm nội dung ghi chú…' : 'Tìm ghi chú, môn hoặc bài học…' }}"
                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
            </div>
            <div class="md:col-span-3">
                <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant">Hiển thị</label>
                <select name="view"
                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    @foreach ($viewOptions as $value => $label)
                        <option value="{{ $value }}" @selected($currentView === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 md:col-span-2 md:justify-end">
                <button type="submit"
                    class="inline-flex h-10 items-center gap-1 rounded-lg bg-primary/10 px-3 font-label-sm text-label-sm text-primary hover:bg-primary/15">
                    <span class="material-symbols-outlined text-[16px]">tune</span>
                    Áp dụng
                </button>
                <a href="{{ route('notes.index') }}"
                    class="inline-flex size-10 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-low"
                    title="Xóa bộ lọc">
                    <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                </a>
            </div>
        </form>

        @if ($total === 0)
            <div
                class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest p-12 text-center">
                <span class="material-symbols-outlined text-[48px] text-primary">sticky_note_2</span>
                <h2 class="font-headline-sm text-headline-sm text-on-surface">
                    {{ $currentQ ? 'Không tìm thấy ghi chú' : 'Chưa có ghi chú' }}
                </h2>
                <p class="max-w-md text-body-md text-on-surface-variant">
                    @if ($currentQ)
                        Thử từ khóa khác hoặc xóa bộ lọc tìm kiếm.
                    @else
                        Viết ghi chú khi làm bài (nút Ghi chú trên thanh công cụ) hoặc tạo mục sổ tay tại đây.
                    @endif
                </p>
                @if (! $currentQ)
                    <button type="button" @click="openCreate()"
                        class="mt-2 flex items-center gap-2 rounded-lg bg-primary-container px-6 py-3 font-label-md text-white shadow-sm hover:opacity-90">
                        <span class="material-symbols-outlined text-[20px]">add</span>
                        Tạo sổ tay
                    </button>
                @endif
            </div>
        @elseif ($isGrouped)
            <p class="text-body-sm text-on-surface-variant">{{ $total }} ghi chú</p>
            <div class="space-y-8">
                @foreach ($groups as $group)
                    <section class="space-y-3">
                        <div class="flex items-baseline gap-2 border-b border-outline-variant pb-2">
                            <h2 class="font-headline-sm text-headline-sm text-on-surface">{{ $group['title'] }}</h2>
                            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ $group['count'] }}</span>
                        </div>
                        <div class="space-y-3">
                            @foreach ($group['notes'] as $note)
                                @include('personalization::notes.partials.card', ['note' => $note, 'groupBy' => $currentView])
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <p class="text-body-sm text-on-surface-variant">{{ $total }} ghi chú</p>
            <div class="space-y-3">
                @foreach ($flatNotes as $note)
                    @include('personalization::notes.partials.card', ['note' => $note, 'groupBy' => 'notebook'])
                @endforeach
            </div>
            <div class="pt-2">
                {{ $flatNotes->links() }}
            </div>
        @endif

        {{-- Editor modal (như bản đầu) --}}
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-on-background/40 backdrop-blur-sm" @click="close()"></div>
            <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.outside="close()">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface"
                        x-text="editingId ? 'Sửa ghi chú' : 'Sổ tay'"></h3>
                    <button type="button" @click="close()"
                        class="rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-low">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="mb-3 flex flex-wrap gap-1 rounded-lg border border-outline-variant bg-surface-container-lowest p-1">
                    <button type="button" @mousedown.prevent @click="format('bold')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="In đậm">
                        <span class="material-symbols-outlined text-[18px]">format_bold</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="format('italic')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="In nghiêng">
                        <span class="material-symbols-outlined text-[18px]">format_italic</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="format('underline')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Gạch chân">
                        <span class="material-symbols-outlined text-[18px]">format_underlined</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="format('insertUnorderedList')" class="flex size-8 items-center justify-center rounded hover:bg-surface-container-high" title="Danh sách">
                        <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                    </button>
                </div>

                <div class="mb-3 flex flex-wrap gap-2">
                    <template x-for="c in colors" :key="c">
                        <button type="button" @click="color = c"
                            class="size-7 rounded-full border-2"
                            :class="color === c ? 'border-on-surface' : 'border-transparent'"
                            :style="`background-color: ${c}`"></button>
                    </template>
                    <button type="button" @click="color = null"
                        class="rounded-full border border-outline-variant px-2 text-[11px] text-on-surface-variant"
                        :class="!color ? 'bg-surface-container' : ''">Không màu</button>
                </div>

                <div x-ref="editor" contenteditable="true"
                    class="min-h-[160px] rounded-lg border border-outline-variant bg-surface-container-lowest p-3 text-body-md text-on-surface focus:border-primary focus:outline-none"
                    data-placeholder="Nhập nội dung ghi chú…"
                    @input="bodyHtml = $event.target.innerHTML"></div>

                <p x-show="error" x-text="error" class="mt-2 text-body-sm text-error"></p>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="close()"
                        class="rounded-lg px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">
                        Hủy
                    </button>
                    <button type="button" @click="save()" :disabled="saving"
                        class="rounded-lg bg-primary-container px-4 py-2 font-label-md text-white hover:opacity-90 disabled:opacity-60">
                        <span x-text="saving ? 'Đang lưu…' : 'Lưu'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        [contenteditable][data-placeholder]:empty:before {
            content: attr(data-placeholder);
            color: var(--color-on-surface-variant, #79747e);
            pointer-events: none;
        }
    </style>

    <script>
        function notesManager(config) {
            return {
                open: false,
                saving: false,
                editingId: null,
                bodyHtml: '',
                color: null,
                error: '',
                colors: config.colors,
                storeUrl: config.storeUrl,
                csrf: config.csrf,
                openCreate() {
                    this.editingId = null;
                    this.bodyHtml = '';
                    this.color = null;
                    this.error = '';
                    this.open = true;
                    this.$nextTick(() => {
                        if (this.$refs.editor) this.$refs.editor.innerHTML = '';
                    });
                },
                openEdit(note) {
                    this.editingId = note.id;
                    this.bodyHtml = note.body_html || '';
                    this.color = note.color || null;
                    this.error = '';
                    this.open = true;
                    this.$nextTick(() => {
                        if (this.$refs.editor) this.$refs.editor.innerHTML = this.bodyHtml;
                    });
                },
                close() {
                    this.open = false;
                },
                format(command) {
                    document.execCommand(command, false, null);
                    if (this.$refs.editor) this.bodyHtml = this.$refs.editor.innerHTML;
                },
                async save() {
                    if (this.saving) return;
                    this.saving = true;
                    this.error = '';
                    if (this.$refs.editor) this.bodyHtml = this.$refs.editor.innerHTML;

                    const url = this.editingId
                        ? `{{ url('/notes') }}/${this.editingId}`
                        : this.storeUrl;
                    const method = this.editingId ? 'PATCH' : 'POST';

                    try {
                        const res = await fetch(url, {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                body_html: this.bodyHtml,
                                color: this.color,
                            }),
                        });
                        if (!res.ok) {
                            const data = await res.json().catch(() => ({}));
                            this.error = data?.error?.message || 'Không lưu được ghi chú.';
                            this.saving = false;
                            return;
                        }
                        window.location.reload();
                    } catch (e) {
                        this.error = 'Không kết nối được máy chủ.';
                        this.saving = false;
                    }
                },
                async removeNote(id) {
                    if (!confirm('Xóa ghi chú này?')) return;
                    const res = await fetch(`{{ url('/notes') }}/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (res.ok || res.status === 204) {
                        window.location.reload();
                    }
                },
            };
        }
    </script>
</x-layouts.app>
