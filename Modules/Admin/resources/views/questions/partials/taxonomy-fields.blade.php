@php
    $lessons = $question->relationLoaded('lessons')
        ? $question->lessons
        : collect();

    $selectedLessons = $lessons->map(fn ($l) => [
        'id' => (int) $l->id,
        'name' => $l->name,
        'subject_names' => $l->relationLoaded('subjects')
            ? $l->subjects->pluck('name')->unique()->values()->all()
            : [],
        'organ_system_names' => $l->relationLoaded('subjects')
            ? $l->subjects
                ->flatMap(fn ($s) => $s->relationLoaded('organSystems')
                    ? $s->organSystems->pluck('name')
                    : collect())
                ->unique()
                ->values()
                ->all()
            : [],
    ])->values()->all();

    $selectedLessonIds = collect(old(
        'lesson_ids',
        $lessons->pluck('id')->all(),
    ))->map(fn ($id) => (int) $id)->unique()->values()->all();

    $selectedTags = $question->relationLoaded('tags')
        ? $question->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all()
        : [];

    $selectedTagIds = collect(old(
        'tag_ids',
        collect($selectedTags)->pluck('id')->all(),
    ))->map(fn ($id) => (int) $id)->unique()->values()->all();

    $inferredCoreTopics = $question->exists
        ? $question->inferredCoreClinicalTopics()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'section_name' => $t->section?->name,
        ])->values()->all()
        : [];
@endphp

<div class="space-y-4 border-t border-outline-variant pt-3"
     x-data="questionLessonPicker({
         selectedLessons: @js(collect($selectedLessons)->keyBy('id')->all()),
         selectedLessonIds: @js($selectedLessonIds),
         selectedTags: @js(collect($selectedTags)->keyBy('id')->all()),
         selectedTagIds: @js($selectedTagIds),
         inferredCoreTopics: @js($inferredCoreTopics),
         urls: {
             organSystems: @js(route('admin.taxonomy.lookups.organ-systems')),
             subjects: @js(route('admin.taxonomy.lookups.subjects')),
             lessons: @js(route('admin.taxonomy.lookups.lessons')),
             tags: @js(route('admin.taxonomy.lookups.tags')),
         },
     })">
    <p class="text-[11px] leading-4 text-on-surface-variant">
        Câu hỏi gắn một hoặc nhiều <strong>bài học</strong> (bắt buộc), không phân biệt chính/phụ.
        Môn học và hệ cơ quan được suy ra từ bài học đã chọn — không gắn trực tiếp.
    </p>

    <div>
        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Bài học *</label>
        <input type="search" x-model="lessonSearch" @input.debounce.300ms="searchLessons()"
               placeholder="Tìm bài học (tăng huyết áp, viêm phổi…)"
               class="mb-2 h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">

        <details class="mb-2 rounded-lg border border-outline-variant/70 bg-surface-container-low/40">
            <summary class="cursor-pointer px-3 py-2 text-[11px] font-medium text-on-surface-variant">
                Lọc tìm kiếm (tuỳ chọn) — hệ cơ quan / môn học
            </summary>
            <div class="grid grid-cols-1 gap-2 border-t border-outline-variant/60 p-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-[11px] text-on-surface-variant">Hệ cơ quan</label>
                    <select x-model.number="organSystemId" @change="onOrganSystemChange()"
                            class="h-9 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                        <option :value="null">— Tất cả —</option>
                        <template x-for="os in organSystems" :key="'os-'+os.id">
                            <option :value="os.id" x-text="os.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-on-surface-variant">Môn học</label>
                    <select x-model.number="subjectId" @change="onSubjectChange()"
                            class="h-9 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
                        <option :value="null">— Tất cả —</option>
                        <template x-for="s in subjects" :key="'sub-'+s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                </div>
            </div>
        </details>

        <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
            <template x-for="lesson in lessonResults" :key="'lesson-'+lesson.id">
                <label class="flex cursor-pointer items-start gap-2 rounded px-2 py-1.5 text-sm hover:bg-surface-container-low">
                    <input type="checkbox" :checked="selectedLessonIds.includes(lesson.id)"
                           @change="toggleLesson(lesson)" class="mt-0.5 size-4 rounded text-primary">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium" x-text="lesson.name"></span>
                        <span class="block text-[11px] text-on-surface-variant"
                              x-show="(lesson.subject_names || []).length || (lesson.organ_system_names || []).length"
                              x-text="lessonContext(lesson)"></span>
                    </span>
                </label>
            </template>
            <p x-show="lessonResults.length === 0 && !lessonLookupError" class="px-2 py-1 text-[11px] text-on-surface-variant">
                Không có bài học phù hợp.
            </p>
            <p x-show="lessonLookupError" class="px-2 py-1 text-[11px] text-error" x-text="lessonLookupError"></p>
        </div>

        <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="id in selectedLessonIds" :key="'lesson-chip-'+id">
                <span class="inline-flex max-w-full items-center gap-1 rounded-lg bg-surface-container px-2 py-1 text-xs font-medium text-on-surface">
                    <span class="min-w-0 truncate" x-text="selectedLessons[id]?.name || ('#'+id)"></span>
                    <button type="button" @click="removeLesson(id)" class="material-symbols-outlined shrink-0 text-[14px]">close</button>
                </span>
            </template>
        </div>
        <p class="mt-1 text-[10px] text-on-surface-variant"
           x-show="selectedLessonIds.length"
           x-text="selectedLessonIds.map(id => lessonContext(selectedLessons[id] || {})).filter(Boolean).join(' · ')"></p>

        <template x-for="id in selectedLessonIds" :key="'lesson-input-'+id">
            <input type="hidden" name="lesson_ids[]" :value="id">
        </template>
        <p x-show="selectedLessonIds.length === 0" class="mt-1 text-xs text-error">Chọn ít nhất một bài học.</p>
    </div>

    <div class="rounded-lg border border-outline-variant/70 bg-surface-container-low/60 p-3"
         x-show="inferredCurriculum.subjects.length || inferredCurriculum.organSystems.length">
        <p class="mb-1.5 text-xs font-semibold text-on-surface-variant">Suy ra từ bài học đã chọn</p>
        <div class="space-y-2">
            <div x-show="inferredCurriculum.subjects.length">
                <p class="mb-1 text-[10px] uppercase tracking-wide text-on-surface-variant">Môn học</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="name in inferredCurriculum.subjects" :key="'inf-sub-'+name">
                        <span class="rounded-lg bg-surface-container-high px-2 py-1 text-xs text-on-surface" x-text="name"></span>
                    </template>
                </div>
            </div>
            <div x-show="inferredCurriculum.organSystems.length">
                <p class="mb-1 text-[10px] uppercase tracking-wide text-on-surface-variant">Hệ cơ quan</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="name in inferredCurriculum.organSystems" :key="'inf-os-'+name">
                        <span class="rounded-lg bg-surface-container-high px-2 py-1 text-xs text-on-surface" x-text="name"></span>
                    </template>
                </div>
            </div>
        </div>
    </div>

    @if (count($inferredCoreTopics) > 0)
        <div class="rounded-lg border border-outline-variant/70 bg-surface-container-low/60 p-3">
            <p class="mb-1.5 text-xs font-semibold text-on-surface-variant">Chủ đề lâm sàng (suy ra từ ma trận)</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($inferredCoreTopics as $topic)
                    <span class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                        {{ $topic['name'] }}
                        @if (! empty($topic['section_name']))
                            <span class="font-normal text-primary/70">· {{ $topic['section_name'] }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <label class="mb-1 block text-xs font-semibold text-on-surface-variant">Thẻ</label>
        <input type="search" x-model="tagSearch" @input.debounce.300ms="searchTags()"
               placeholder="Tìm thẻ (ECG, cấp cứu…)"
               class="mb-2 h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm">
        <div class="max-h-28 space-y-1 overflow-y-auto rounded-lg border border-outline-variant p-2">
            <template x-for="tag in tagResults" :key="tag.id">
                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-surface-container-low">
                    <input type="checkbox" :checked="selectedTagIds.includes(tag.id)"
                           @change="toggleTag(tag)" class="size-4 rounded text-primary">
                    <span x-text="tag.name"></span>
                </label>
            </template>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="id in selectedTagIds" :key="'tag-chip-'+id">
                <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    <span x-text="selectedTags[id]?.name || ('#'+id)"></span>
                    <button type="button" @click="removeTag(id)" class="material-symbols-outlined text-[14px]">close</button>
                </span>
            </template>
        </div>
        <template x-for="id in selectedTagIds" :key="'tag-'+id">
            <input type="hidden" name="tag_ids[]" :value="id">
        </template>
    </div>
</div>

<script>
    function questionLessonPicker(config) {
        return {
            ...config,
            organSystems: [],
            organSystemId: null,
            subjects: [],
            subjectId: null,
            lessonSearch: '',
            lessonResults: [],
            lessonLookupError: '',
            tagSearch: '',
            tagResults: [],
            get inferredCurriculum() {
                const subjects = new Set();
                const organSystems = new Set();
                for (const id of this.selectedLessonIds) {
                    const lesson = this.selectedLessons[id];
                    if (! lesson) continue;
                    (lesson.subject_names || []).forEach(n => subjects.add(n));
                    (lesson.organ_system_names || []).forEach(n => organSystems.add(n));
                }
                return {
                    subjects: [...subjects].sort((a, b) => a.localeCompare(b, 'vi')),
                    organSystems: [...organSystems].sort((a, b) => a.localeCompare(b, 'vi')),
                };
            },
            lessonContext(lesson) {
                if (! lesson) return '';
                const parts = [];
                const subjects = lesson.subject_names || [];
                const systems = lesson.organ_system_names || [];
                if (subjects.length) parts.push(subjects.join(', '));
                if (systems.length) parts.push(systems.join(', '));
                return parts.join(' · ');
            },
            async init() {
                try {
                    await this.loadOrganSystems();
                    await this.loadSubjects();
                } catch (_) {
                    // Optional filters; lesson search below still runs.
                }
                await this.searchLessons();
            },
            async fetchJson(url) {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (! res.ok) {
                    throw new Error(res.status === 403
                        ? 'Không có quyền tải danh mục. Tải lại trang hoặc liên hệ admin.'
                        : 'Không tải được danh mục.');
                }
                return res.json();
            },
            async loadOrganSystems() {
                const json = await this.fetchJson(this.urls.organSystems);
                this.organSystems = json.data ?? [];
            },
            async loadSubjects() {
                const params = new URLSearchParams();
                if (this.organSystemId) params.set('organ_system_id', this.organSystemId);
                const url = params.toString() ? `${this.urls.subjects}?${params}` : this.urls.subjects;
                const json = await this.fetchJson(url);
                this.subjects = json.data ?? [];
            },
            async onOrganSystemChange() {
                this.subjectId = null;
                await this.loadSubjects();
                await this.searchLessons();
            },
            async onSubjectChange() {
                await this.searchLessons();
            },
            async searchLessons() {
                const params = new URLSearchParams();
                const q = this.lessonSearch.trim();
                if (q.length >= 1) params.set('q', q);
                if (this.subjectId) params.set('subject_id', this.subjectId);
                if (this.organSystemId) params.set('organ_system_id', this.organSystemId);
                const url = params.toString() ? `${this.urls.lessons}?${params}` : this.urls.lessons;
                try {
                    const json = await this.fetchJson(url);
                    this.lessonResults = json.data ?? [];
                    this.lessonLookupError = '';
                } catch (error) {
                    this.lessonResults = [];
                    this.lessonLookupError = error?.message || 'Không tải được bài học.';
                }
            },
            toggleLesson(lesson) {
                const idx = this.selectedLessonIds.indexOf(lesson.id);
                if (idx >= 0) {
                    this.removeLesson(lesson.id);
                } else {
                    this.selectedLessonIds.push(lesson.id);
                    this.selectedLessons[lesson.id] = lesson;
                }
            },
            removeLesson(id) {
                this.selectedLessonIds = this.selectedLessonIds.filter(x => x !== id);
                delete this.selectedLessons[id];
            },
            async searchTags() {
                const q = this.tagSearch.trim();
                if (q.length < 1) { this.tagResults = []; return; }
                try {
                    const json = await this.fetchJson(`${this.urls.tags}?q=${encodeURIComponent(q)}`);
                    this.tagResults = json.data ?? [];
                } catch (_) {
                    this.tagResults = [];
                }
            },
            toggleTag(tag) {
                const idx = this.selectedTagIds.indexOf(tag.id);
                if (idx >= 0) {
                    this.selectedTagIds.splice(idx, 1);
                    delete this.selectedTags[tag.id];
                } else {
                    this.selectedTagIds.push(tag.id);
                    this.selectedTags[tag.id] = tag;
                }
            },
            removeTag(id) {
                this.selectedTagIds = this.selectedTagIds.filter(x => x !== id);
                delete this.selectedTags[id];
            },
        };
    }
</script>
