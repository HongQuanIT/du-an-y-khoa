{{-- Môn học (Subjects) — thuộc nhiều hệ cơ quan, gom nhiều bài học --}}
<div class="space-y-4">
    @if ($canCreate)
        <details class="rounded-xl border border-dashed border-outline-variant bg-surface open:border-solid" {{ $subjects->isEmpty() ? 'open' : '' }}>
            <summary class="cursor-pointer list-none px-4 py-3 font-label-md font-semibold text-on-surface marker:content-none">
                <span class="inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">add_circle</span>
                    Thêm môn học
                </span>
            </summary>
            <form method="post" action="{{ route('admin.curriculum.subjects.store') }}"
                class="space-y-3 border-t border-outline-variant px-4 py-4">
                @csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Tên môn học *</label>
                        <input name="name" required maxlength="255" placeholder="Ví dụ: Nội khoa, Dược lý…"
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
                </div>
                @if ($organSystems->isNotEmpty())
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">
                            Thuộc hệ cơ quan
                            <span class="font-normal">(chọn nhiều)</span>
                        </label>
                        <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto rounded-xl border border-outline-variant/70 bg-surface-container-lowest p-2.5">
                            @foreach ($organSystems as $os)
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                                    <input type="checkbox" name="organ_system_ids[]" value="{{ $os->id }}" class="rounded border-outline-variant text-primary focus:ring-primary">
                                    {{ $os->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <button type="submit" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary hover:bg-primary/90">
                    Thêm môn học
                </button>
            </form>
        </details>
    @endif

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <ul class="divide-y divide-outline-variant/70">
            @forelse ($subjects as $subject)
                <li id="node-subjects-{{ $subject->id }}" class="group"
                    :class="'{{ $focusId }}' === '{{ $subject->id }}' ? 'bg-primary/5' : ''">
                    <div class="flex items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-on-surface">{{ $subject->name }}</p>
                                @if ($subject->code)
                                    <span class="rounded-md bg-surface-container-high px-1.5 py-0.5 font-mono text-[11px] text-on-surface-variant">{{ $subject->code }}</span>
                                @endif
                                @if ($subject->status->value !== 'active')
                                    <span class="rounded-md bg-surface-container-high px-1.5 py-0.5 text-[10px] font-semibold text-on-surface-variant">{{ $statusLabels[$subject->status->value] ?? $subject->status->value }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-[11px] text-on-surface-variant">{{ $subject->organ_systems_count }} hệ cơ quan · {{ $subject->lessons_count }} bài học</p>
                        </div>
                        @if ($canUpdate)
                            <button type="button"
                                @click="editing = editing === 'subj-{{ $subject->id }}' ? null : 'subj-{{ $subject->id }}'"
                                class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-on-surface-variant hover:bg-surface-container hover:text-primary"
                                x-text="editing === 'subj-{{ $subject->id }}' ? 'Đóng' : 'Sửa'"></button>
                        @endif
                    </div>

                    {{-- Liên kết hệ cơ quan (M:N) --}}
                    <div class="px-4 pb-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">Hệ cơ quan:</span>
                            @forelse ($subject->organSystems as $os)
                                <span class="inline-flex items-center gap-1 rounded-md bg-secondary-container px-1.5 py-0.5 text-[11px] text-on-secondary-container">
                                    {{ $os->name }}
                                    @if ($canUpdate)
                                        <form method="post" action="{{ route('admin.curriculum.subjects.organ-systems.detach', [$subject, $os]) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Gỡ liên kết" class="flex items-center text-on-secondary-container/70 hover:text-error">
                                                <span class="material-symbols-outlined text-[14px]">close</span>
                                            </button>
                                        </form>
                                    @endif
                                </span>
                            @empty
                                <span class="text-[11px] italic text-on-surface-variant">Chưa gắn hệ cơ quan nào</span>
                            @endforelse
                        </div>

                        @if ($canUpdate)
                            @php $availableOrganSystems = $organSystems->whereNotIn('id', $subject->organSystems->pluck('id')); @endphp
                            @if ($availableOrganSystems->isNotEmpty())
                                <form method="post" action="{{ route('admin.curriculum.subjects.organ-systems.attach', $subject) }}"
                                    class="mt-2 rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest/60 p-2">
                                    @csrf
                                    <p class="mb-1.5 text-[11px] font-medium text-on-surface-variant">Gắn thêm hệ cơ quan (chọn nhiều):</p>
                                    <div class="mb-2 flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">
                                        @foreach ($availableOrganSystems as $os)
                                            <label class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-outline-variant bg-surface px-2 py-1 text-[11px] has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                                                <input type="checkbox" name="organ_system_ids[]" value="{{ $os->id }}" class="size-3.5 rounded border-outline-variant text-primary">
                                                {{ $os->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <button type="submit" class="rounded-lg bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary hover:bg-primary/20">Gắn đã chọn</button>
                                </form>
                            @endif
                        @endif
                    </div>

                    @if ($canUpdate)
                        <div x-show="editing === 'subj-{{ $subject->id }}'" x-cloak
                            class="border-t border-outline-variant/50 bg-surface-container-lowest px-4 py-4">
                            <form method="post" action="{{ route('admin.curriculum.subjects.update', $subject) }}"
                                class="space-y-3">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                    <div class="lg:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Tên</label>
                                        <input name="name" value="{{ $subject->name }}" required maxlength="255"
                                            class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mã định danh</label>
                                        <input name="slug" value="{{ $subject->slug }}"
                                            class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 font-mono text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mã (code)</label>
                                        <input name="code" value="{{ $subject->code }}" maxlength="100"
                                            class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 font-mono text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Thứ tự</label>
                                        <input type="number" name="sort_order" min="0" value="{{ $subject->sort_order }}"
                                            class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Trạng thái</label>
                                        <select name="status" class="h-10 w-full rounded-xl border border-outline-variant bg-surface px-3 text-sm">
                                            @foreach ($statuses as $status)
                                                <option value="{{ $status->value }}" @selected($subject->status === $status)>{{ $statusLabels[$status->value] ?? $status->value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-2 lg:col-span-4">
                                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Mô tả</label>
                                        <textarea name="description" rows="2" maxlength="2000"
                                            class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm">{{ $subject->description }}</textarea>
                                    </div>
                                </div>

                                @if ($organSystems->isNotEmpty())
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant">
                                            Hệ cơ quan liên kết
                                            <span class="font-normal">(chọn nhiều — lưu sẽ đồng bộ)</span>
                                        </label>
                                        <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto rounded-xl border border-outline-variant bg-surface p-2.5">
                                            @foreach ($organSystems as $os)
                                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-outline-variant bg-surface-container-lowest px-2.5 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                                                    <input type="checkbox" name="organ_system_ids[]" value="{{ $os->id }}"
                                                        @checked($subject->organSystems->contains('id', $os->id))
                                                        class="rounded border-outline-variant text-primary focus:ring-primary">
                                                    {{ $os->name }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center gap-2">
                                    <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-on-primary">Lưu thay đổi</button>
                                    <button type="button" @click="editing = null" class="rounded-xl px-3 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container">Hủy</button>
                                </div>
                            </form>
                            <p class="mt-2 text-[11px] text-on-surface-variant">Liên kết với bài học được quản lý ở tab «Bài học».</p>
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-on-surface-variant">Chưa có môn học nào.</li>
            @endforelse
        </ul>
    </div>
</div>
