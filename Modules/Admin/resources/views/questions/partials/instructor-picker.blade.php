@php
    $assignedId = (int) old('assigned_instructor_id', $question->assigned_instructor_id);
    $assignedName = $question->assignedInstructor?->name;
    $assignedSubjects = $question->assignedInstructor
        ? $question->assignedInstructor->instructorSubjects->pluck('name')->filter()->values()->all()
        : [];
@endphp
<div
    data-testid="assigned-instructor-picker"
    x-data="assignedInstructorPicker({
        selectedId: @js($assignedId ?: null),
        selectedName: @js($assignedName),
        selectedSubjects: @js($assignedSubjects),
        url: @js(route('admin.questions.eligible-instructors')),
    })"
>
    <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="assigned_instructor_id_ui">
        Giảng viên chuyên môn *
    </label>
    <p class="mb-2 text-[11px] leading-4 text-on-surface-variant">
        Chỉ hiện giảng viên có môn học giao với bài học đã chọn.
    </p>
    {{-- Hidden luôn submit: select bị disabled lúc reload (loading) nên browser bỏ name nếu gắn trên select. --}}
    <input type="hidden" name="assigned_instructor_id" :value="selectedId ?? ''">
    <select id="assigned_instructor_id_ui"
        x-model.number="selectedId"
        class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        :disabled="loading || (empty && !selectedId)">
        <option value="">— Chọn giảng viên —</option>
        <template x-for="instructor in instructors" :key="instructor.id">
            <option :value="instructor.id" x-text="instructor.name"></option>
        </template>
    </select>
    <p x-show="loading" x-cloak class="mt-1 text-[11px] text-on-surface-variant">Đang tải giảng viên…</p>
    <p x-show="!loading && empty" x-cloak class="mt-1 text-[11px] text-amber-700">
        Chưa có giảng viên khớp môn. Hãy chọn bài học hoặc nhờ admin gán môn cho giảng viên.
    </p>
    @error('assigned_instructor_id')
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>

<script>
    document.addEventListener('alpine:init', () => {
        if (window.__assignedInstructorPickerRegistered) return;
        window.__assignedInstructorPickerRegistered = true;

        Alpine.data('assignedInstructorPicker', (config) => ({
            selectedId: config.selectedId,
            instructors: config.selectedId && config.selectedName
                ? [{
                    id: config.selectedId,
                    name: config.selectedName,
                    email: '',
                    subjects: config.selectedSubjects || [],
                }]
                : [],
            empty: false,
            loading: false,
            url: config.url,

            init() {
                const form = this.$root.closest('form');
                form?.addEventListener('question-lessons-changed', (event) => {
                    const ids = event.detail?.lessonIds;
                    this.reload(Array.isArray(ids) ? ids : null);
                });
                this.$watch('selectedId', (value) => {
                    this.$dispatch('instructor-assignment-changed', {
                        selectedId: value,
                        lessonCount: this.lessonIdsFromForm().length,
                        instructorCount: this.instructors.length,
                    });
                });
                this.reload();
            },

            lessonIdsFromForm() {
                return Array.from(this.$root.closest('form')?.querySelectorAll('input[name="lesson_ids[]"]') ?? [])
                    .map((el) => el.value)
                    .filter(Boolean);
            },

            async reload(lessonIds = null) {
                const ids = lessonIds ?? this.lessonIdsFromForm();
                const params = new URLSearchParams();
                ids.forEach((id) => params.append('lesson_ids[]', String(id)));
                this.loading = true;
                try {
                    const response = await fetch(`${this.url}?${params.toString()}`, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (! response.ok) {
                        return;
                    }
                    const data = await response.json();
                    this.instructors = data.instructors || [];
                    this.empty = this.instructors.length === 0;
                    if (this.selectedId && ! this.instructors.some((row) => Number(row.id) === Number(this.selectedId))) {
                        this.selectedId = null;
                    }
                    this.$dispatch('instructor-assignment-changed', {
                        selectedId: this.selectedId,
                        lessonCount: ids.length,
                        instructorCount: this.instructors.length,
                    });
                } catch {
                    // keep current options
                } finally {
                    this.loading = false;
                }
            },
        }));
    });
</script>
