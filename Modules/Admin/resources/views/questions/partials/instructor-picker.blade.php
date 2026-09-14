@php
    $assignedId = (int) old('assigned_instructor_id', $question->assigned_instructor_id);
    $assignedName = $question->assignedInstructor?->name;
@endphp
<div class="border-t border-outline-variant pt-3"
    x-data="assignedInstructorPicker({
        selectedId: @js($assignedId ?: null),
        selectedName: @js($assignedName),
        url: @js(route('admin.questions.eligible-instructors')),
    })">
    <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="assigned_instructor_id">
        Giảng viên chuyên môn *
    </label>
    <p class="mb-2 text-[11px] leading-4 text-on-surface-variant">
        Chỉ hiện giảng viên có môn học giao với bài học đã chọn. Chọn đúng chuyên môn — không gửi mọi giảng viên.
    </p>
    <select id="assigned_instructor_id" name="assigned_instructor_id" required
        x-model.number="selectedId"
        class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
        <option value="">— Chọn giảng viên —</option>
        <template x-for="instructor in instructors" :key="instructor.id">
            <option :value="instructor.id" x-text="instructor.name" :selected="selectedId === instructor.id"></option>
        </template>
    </select>
    <button type="button" @click="reload()"
        class="mt-2 text-[11px] font-semibold text-primary hover:underline">
        Làm mới danh sách theo bài học
    </button>
    <p x-show="empty" x-cloak class="mt-1 text-[11px] text-amber-700">
        Chưa có giảng viên khớp môn. Hãy chọn bài học hoặc nhờ admin gán môn cho giảng viên.
    </p>
    @error('assigned_instructor_id')
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('assignedInstructorPicker', (config) => ({
            selectedId: config.selectedId,
            instructors: config.selectedId && config.selectedName
                ? [{ id: config.selectedId, name: config.selectedName }]
                : [],
            empty: false,
            url: config.url,
            init() {
                this.reload();
            },
            lessonIds() {
                return Array.from(this.$root.closest('form').querySelectorAll('input[name="lesson_ids[]"]'))
                    .map((el) => el.value)
                    .filter(Boolean);
            },
            async reload() {
                const params = new URLSearchParams();
                this.lessonIds().forEach((id) => params.append('lesson_ids[]', id));
                try {
                    const response = await fetch(`${this.url}?${params.toString()}`, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) {
                        return;
                    }
                    const data = await response.json();
                    this.instructors = data.instructors || [];
                    this.empty = this.instructors.length === 0;
                    if (this.selectedId && !this.instructors.some((row) => row.id === this.selectedId)) {
                        this.selectedId = null;
                    }
                } catch {
                    // keep current options
                }
            },
        }));
    });
</script>
