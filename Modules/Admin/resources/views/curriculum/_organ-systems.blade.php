{{-- Hệ cơ quan (Organ systems) — CRUD cấp cao nhất --}}
<div class="space-y-4">
    @if ($canCreate)
        <details class="rounded-xl border border-dashed border-outline-variant bg-surface open:border-solid" {{ $organSystems->isEmpty() ? 'open' : '' }}>
            <summary class="cursor-pointer list-none px-4 py-3 font-label-md font-semibold text-on-surface marker:content-none">
                <span class="inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">add_circle</span>
                    Thêm hệ cơ quan
                </span>
            </summary>
            <form method="post" action="{{ route('admin.curriculum.organ-systems.store') }}"
                class="grid grid-cols-1 gap-3 border-t border-outline-variant px-4 py-4 md:grid-cols-2 lg:grid-cols-4">
                @csrf
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Tên hệ cơ quan *</label>
                    <input name="name" required maxlength="255" placeholder="Ví dụ: Hệ tim mạch"
                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mã (tùy chọn)</label>
                    <input name="code" maxlength="100"
                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-mono text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Thứ tự</label>
                    <input type="number" name="sort_order" min="0" value="0"
                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                </div>
                <input type="hidden" name="status" value="active">
                <div class="md:col-span-2 lg:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mô tả ngắn</label>
                    <textarea name="description" rows="2" maxlength="2000" placeholder="Tùy chọn"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm"></textarea>
                </div>
                <div class="md:col-span-2 lg:col-span-4">
                    <button type="submit" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary hover:bg-primary/90">
                        Thêm hệ cơ quan
                    </button>
                </div>
            </form>
        </details>
    @endif

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <ul class="divide-y divide-outline-variant/70">
            @forelse ($organSystems as $os)
                <li id="node-organ-systems-{{ $os->id }}" class="group"
                    :class="'{{ $focusId }}' === '{{ $os->id }}' ? 'bg-primary/5' : ''">
                    <div class="flex items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-on-surface">{{ $os->name }}</p>
                                @if ($os->code)
                                    <span class="rounded-md bg-surface-container-high px-1.5 py-0.5 font-mono text-[11px] text-on-surface-variant">{{ $os->code }}</span>
                                @endif
                                @if ($os->status->value !== 'active')
                                    <span class="rounded-md bg-surface-container-high px-1.5 py-0.5 text-[10px] font-semibold text-on-surface-variant">{{ $statusLabels[$os->status->value] ?? $os->status->value }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-[11px] text-on-surface-variant">{{ $os->subjects_count }} môn học</p>
                            @if ($os->subjects->isNotEmpty())
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach ($os->subjects as $subject)
                                        <span class="inline-flex items-center rounded-md bg-surface-container px-1.5 py-0.5 text-[11px] text-on-surface-variant">{{ $subject->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($os->description)
                                <p class="mt-1 line-clamp-2 text-xs text-on-surface-variant">{{ $os->description }}</p>
                            @endif
                        </div>
                        @if ($canUpdate)
                            <button type="button"
                                @click="editing = editing === 'os-{{ $os->id }}' ? null : 'os-{{ $os->id }}'"
                                class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-on-surface-variant hover:bg-surface-container hover:text-primary"
                                x-text="editing === 'os-{{ $os->id }}' ? 'Đóng' : 'Sửa'"></button>
                        @endif
                    </div>

                    @if ($canUpdate)
                        <div x-show="editing === 'os-{{ $os->id }}'" x-cloak
                            class="border-t border-outline-variant/50 bg-surface-container-lowest px-4 py-4">
                            <form method="post" action="{{ route('admin.curriculum.organ-systems.update', $os) }}"
                                class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                @csrf @method('PUT')
                                <div class="lg:col-span-2">
                                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Tên</label>
                                    <input name="name" value="{{ $os->name }}" required maxlength="255"
                                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mã định danh</label>
                                    <input name="slug" value="{{ $os->slug }}"
                                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 font-mono text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mã (code)</label>
                                    <input name="code" value="{{ $os->code }}" maxlength="100"
                                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 font-mono text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Thứ tự</label>
                                    <input type="number" name="sort_order" min="0" value="{{ $os->sort_order }}"
                                        class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Trạng thái</label>
                                    <select name="status" class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->value }}" @selected($os->status === $status)>{{ $statusLabels[$status->value] ?? $status->value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2 lg:col-span-4">
                                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mô tả</label>
                                    <textarea name="description" rows="2" maxlength="2000"
                                        class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm">{{ $os->description }}</textarea>
                                </div>
                                <div class="flex items-center gap-2 md:col-span-2 lg:col-span-4">
                                    <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-on-primary">Lưu thay đổi</button>
                                    <button type="button" @click="editing = null" class="rounded-xl px-3 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container">Hủy</button>
                                </div>
                            </form>
                            <p class="mt-2 text-[11px] text-on-surface-variant">Liên kết với môn học được quản lý ở tab «Môn học».</p>
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-on-surface-variant">Chưa có hệ cơ quan nào.</li>
            @endforelse
        </ul>
    </div>
</div>
