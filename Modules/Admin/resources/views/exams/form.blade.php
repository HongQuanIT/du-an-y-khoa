@php
    use Modules\Exam\Models\ExamTopic;
    use Modules\QuestionBank\Enums\Difficulty;

    $isNew = ! $exam->exists;
    $availableQuestions = $availableQuestions ?? collect();
    $difficultyLevels = $difficultyLevels ?? array_map(
        fn (Difficulty $case): array => ['value' => $case->value, 'label' => $case->label()],
        Difficulty::cases(),
    );
    $selectedQuestionIds = old('questions');
    $questionSource = is_array($selectedQuestionIds)
        ? $availableQuestions->whereIn('id', $selectedQuestionIds)
            ->sortBy(fn ($question) => array_search((string) $question->id, array_map('strval', $selectedQuestionIds), true))
        : ($exam->exists ? $exam->questions : collect());
    $questionRows = $questionSource
        ->map(fn ($question) => [
            'id' => (string) $question->id,
            'text' => strip_tags($question->stem),
            'topic' => $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp',
            'topics' => $question->medicalTaxonomyNodes->pluck('name')->values()->all(),
            'difficulty' => $question->difficulty?->label(),
        ])->values()->all();
    $availableQuestionsMapped = $availableQuestions->map(fn ($question) => [
        'id' => (string) $question->id,
        'text' => strip_tags($question->stem),
        'topic' => $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp',
        'topics' => $question->medicalTaxonomyNodes->pluck('name')->values()->all(),
        'difficulty' => $question->difficulty?->label(),
    ])->values()->all();
    $questionsCount = (int) ($exam->questions_count ?? count($questionRows));
    $statusValue = old('status', $exam->status?->value ?? 'draft');
    $published = $statusValue === 'published';
    $duration = (int) old('duration_minutes', $exam->duration_minutes ?? 90);
    $initialBlueprintId = old('blueprint_id', $exam->blueprint_id);
    $initialSectionIds = old('section_ids');
    if (! is_array($initialSectionIds)) {
        $initialSectionIds = $exam->exists && $exam->relationLoaded('examTopics')
            ? $exam->examTopics
                ->map(fn ($row) => $row->coreClinicalTopic?->blueprint_section_id)
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];
    }
    $initialExamTopics = old('exam_topics');
    if (! is_array($initialExamTopics)) {
        $initialExamTopics = $exam->exists && $exam->relationLoaded('examTopics')
            ? $exam->examTopics->map(fn ($row) => [
                'core_clinical_topic_id' => $row->core_clinical_topic_id,
                'difficulty_counts' => ExamTopic::normalizeDifficultyCounts($row->difficulty_counts, $row->question_count),
                'question_count' => $row->question_count,
                'sort_order' => $row->sort_order,
                'topic_name' => $row->coreClinicalTopic?->name,
                'section_id' => $row->coreClinicalTopic?->blueprint_section_id,
                'section_name' => $row->coreClinicalTopic?->section?->name,
            ])->values()->all()
            : [];
    } else {
        $initialExamTopics = collect($initialExamTopics)->map(function ($row) {
            $counts = ExamTopic::normalizeDifficultyCounts(
                is_array($row['difficulty_counts'] ?? null) ? $row['difficulty_counts'] : null
            );

            return [
                'core_clinical_topic_id' => $row['core_clinical_topic_id'] ?? null,
                'difficulty_counts' => $counts,
                'question_count' => ExamTopic::sumDifficultyCounts($counts),
                'sort_order' => $row['sort_order'] ?? 0,
                'topic_name' => $row['topic_name'] ?? '',
                'section_id' => $row['section_id'] ?? null,
                'section_name' => $row['section_name'] ?? '',
            ];
        })->values()->all();
    }
@endphp

<x-layouts.admin :title="$isNew ? 'Tạo kỳ thi' : 'Sửa kỳ thi'">
    <form action="{{ $isNew ? route('admin.exams.store') : route('admin.exams.update', $exam) }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="examQuestions()"
        class="space-y-6">
        @csrf
        @if ($exam->exists)
            @method('PUT')
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('admin.exams.index') }}"
                    class="inline-flex items-center gap-1.5 font-label-sm text-primary hover:underline">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Danh sách kỳ thi
                </a>
                <h1 class="mt-2 font-headline-md text-headline-md text-on-surface">
                    {{ $isNew ? 'Tạo kỳ thi mới' : $exam->title }}
                </h1>
                <p class="mt-1 max-w-2xl font-body-sm text-on-surface-variant">
                    {{ $isNew
                        ? 'Chọn ma trận đề thi, phân bổ số câu theo chủ đề và mức độ — hệ thống tự lấy từ exam pool.'
                        : 'Cập nhật thông tin, phân bổ ma trận và trạng thái kỳ thi.' }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.exams.index') }}"
                    class="inline-flex h-10 items-center rounded-lg border border-outline-variant px-4 font-label-md text-on-surface-variant hover:bg-surface-container-low">
                    Hủy
                </a>
                <button type="submit" name="status" value="draft"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-outline-variant bg-surface px-4 font-label-md text-on-surface hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[18px]">draft</span>
                    Lưu nháp
                </button>
                <button type="submit" name="status" value="published"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 font-label-md text-on-primary hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]">publish</span>
                    {{ $isNew ? 'Tạo và xuất bản' : 'Lưu và xuất bản' }}
                </button>
            </div>
        </div>

        <x-admin.flash />

        @if ($errors->any())
            <div class="rounded-lg border border-error/30 bg-error/10 px-4 py-3 font-body-sm text-error">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-outline-variant bg-surface px-4 py-3">
                <p class="font-label-sm text-on-surface-variant">Số câu</p>
                <p class="mt-1 font-headline-sm text-headline-sm text-on-surface" x-text="configuredTotal || selected.length">{{ $questionsCount }}</p>
            </div>
            <div class="rounded-lg border border-outline-variant bg-surface px-4 py-3">
                <p class="font-label-sm text-on-surface-variant">Thời gian</p>
                <p class="mt-1 font-headline-sm text-headline-sm text-on-surface"><span x-text="duration">{{ $duration }}</span> phút</p>
            </div>
            <div class="rounded-lg border border-outline-variant bg-surface px-4 py-3">
                <p class="font-label-sm text-on-surface-variant">Trạng thái</p>
                <p class="mt-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $published ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">
                        {{ $published ? 'Đã xuất bản' : 'Bản nháp' }}
                    </span>
                </p>
            </div>
            <div class="rounded-lg border border-outline-variant bg-surface px-4 py-3">
                <p class="font-label-sm text-on-surface-variant">Hiển thị học viên</p>
                <p class="mt-1 font-label-md text-on-surface">{{ $published ? 'Đã bật' : 'Đang tắt' }}</p>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <main class="space-y-6">
                <section class="overflow-hidden rounded-lg border border-outline-variant bg-surface">
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h2 class="font-label-lg text-on-surface">Thông tin kỳ thi</h2>
                        <p class="mt-1 font-label-sm text-on-surface-variant">Tên, mô tả và thời gian sẽ được học viên nhìn thấy trước khi bắt đầu.</p>
                    </div>

                    <div class="space-y-5 p-5">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                            <div>
                                <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="title">
                                    Tên kỳ thi <span class="text-error">*</span>
                                </label>
                                <input id="title" name="title" type="text" required maxlength="255"
                                    value="{{ old('title', $exam->title) }}"
                                    class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-on-surface focus:ring-2 focus:ring-primary">
                                @error('title')
                                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="duration_minutes">
                                    Thời gian <span class="text-error">*</span>
                                </label>
                                <div class="relative">
                                    <input id="duration_minutes" name="duration_minutes" type="number" min="1" required
                                        value="{{ $duration }}"
                                        x-model.number="duration"
                                        class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 pr-14 font-body-sm text-on-surface focus:ring-2 focus:ring-primary">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center font-label-sm text-on-surface-variant">phút</span>
                                </div>
                                @error('duration_minutes')
                                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="description">
                                Mô tả kỳ thi
                            </label>
                            <textarea id="description" name="description" rows="4"
                                placeholder="Mô tả ngắn hiển thị trên card kỳ thi của học viên..."
                                class="block w-full resize-y rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-on-surface focus:ring-2 focus:ring-primary">{{ old('description', $exam->description) }}</textarea>
                            @error('description')
                                <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_240px]">
                            <div>
                                <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="icon">Ảnh icon</label>
                                <input id="icon" name="icon" type="file" accept="image/*"
                                    class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-on-surface file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:font-label-sm file:text-on-primary">
                                @error('icon')
                                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center gap-3 rounded-lg border border-outline-variant bg-surface-container-lowest p-3">
                                @if ($exam->icon)
                                    <img src="{{ Storage::disk('public')->url($exam->icon) }}" alt="Icon hiện tại" class="size-12 rounded-lg object-cover">
                                @else
                                    <span class="flex size-12 items-center justify-center rounded-lg bg-primary-container text-on-primary-container">
                                        <span class="material-symbols-outlined">assignment</span>
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-label-md text-on-surface">Icon kỳ thi</p>
                                    <p class="truncate font-label-sm text-on-surface-variant">{{ $exam->icon ? 'Đang dùng ảnh đã tải' : 'Đang dùng icon mặc định' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-outline-variant bg-surface">
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h2 class="font-label-lg text-on-surface">Phân bổ theo ma trận đề thi</h2>
                        <p class="mt-1 font-label-sm text-on-surface-variant">
                            Chọn ma trận → chọn phần → nhập số câu theo từng mức độ. Hệ thống lấy từ exam pool (<code>private</code> + <code>exam_flag</code>).
                        </p>
                    </div>
                    <div class="space-y-4 p-5">
                        @error('exam_topics')
                            <p class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">{{ $message }}</p>
                        @enderror

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="blueprint_id">Ma trận đề thi</label>
                                <select id="blueprint_id" name="blueprint_id" x-model="blueprintId" @change="onBlueprintChange()"
                                    class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-on-surface focus:ring-2 focus:ring-primary">
                                    <option value="">— Chọn ma trận —</option>
                                    <template x-for="bp in blueprints" :key="bp.id">
                                        <option :value="String(bp.id)" x-text="bp.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <p class="font-label-sm text-on-surface-variant" x-show="isLoadingSections || isLoadingTopics">
                                    <span class="material-symbols-outlined align-middle animate-spin text-[16px]">progress_activity</span>
                                    Đang tải…
                                </p>
                            </div>
                        </div>

                        <div x-show="blueprintId && sections.length" class="space-y-2">
                            <p class="font-label-sm text-on-surface-variant">Chọn phần (section) để phân bổ</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="section in sections" :key="section.id">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-outline-variant px-3 py-2 font-label-sm hover:bg-surface-container-low"
                                        :class="selectedSectionIds.includes(Number(section.id)) ? 'border-primary bg-primary/5' : ''">
                                        <input type="checkbox" name="section_ids[]" :value="section.id"
                                            :checked="selectedSectionIds.includes(Number(section.id))"
                                            @change="toggleSection(section.id)"
                                            class="rounded border-outline-variant text-primary focus:ring-primary">
                                        <span x-text="section.name"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <div x-show="allocationRows.length" class="overflow-x-auto rounded-lg border border-outline-variant">
                            <table class="min-w-full text-sm">
                                <thead class="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        <th class="px-3 py-2 sticky left-0 bg-surface-container-low min-w-[200px]">Chủ đề</th>
                                        <template x-for="level in difficultyLevels" :key="'h-' + level.value">
                                            <th class="px-2 py-2 text-center whitespace-nowrap" x-text="level.label"></th>
                                        </template>
                                        <th class="px-3 py-2 text-center">Tổng</th>
                                        <th class="px-3 py-2 text-center min-w-[140px]">Eligible</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(group, gIndex) in groupedAllocation" :key="'sec-' + group.section_id">
                                        <template x-for="(row, rowIndex) in group.rows" :key="'cct-' + row.core_clinical_topic_id">
                                            <tr class="border-t border-outline-variant/60">
                                                <td class="px-3 py-2 sticky left-0 bg-surface">
                                                    <input type="hidden" :name="'exam_topics[' + row.formIndex + '][core_clinical_topic_id]'" :value="row.core_clinical_topic_id">
                                                    <input type="hidden" :name="'exam_topics[' + row.formIndex + '][sort_order]'" :value="row.formIndex">
                                                    <p class="font-label-md text-on-surface" x-text="row.topic_name"></p>
                                                    <p class="text-xs text-on-surface-variant" x-text="row.section_name"></p>
                                                </td>
                                                <template x-for="level in difficultyLevels" :key="'c-' + row.core_clinical_topic_id + '-' + level.value">
                                                    <td class="px-1 py-2">
                                                        <input type="number" min="0"
                                                            :name="'exam_topics[' + row.formIndex + '][difficulty_counts][' + level.value + ']'"
                                                            x-model.number="row.difficulty_counts[level.value]"
                                                            @change="refreshEligibility()"
                                                            class="w-14 rounded-lg bg-surface-container-low px-1 py-1.5 text-center text-sm">
                                                    </td>
                                                </template>
                                                <td class="px-3 py-2 text-center font-semibold" x-text="rowTotal(row)"></td>
                                                <td class="px-3 py-2 text-center text-xs">
                                                    <template x-if="row.eligible">
                                                        <div class="space-y-0.5">
                                                            <template x-for="level in difficultyLevels" :key="'e-' + row.core_clinical_topic_id + '-' + level.value">
                                                                <div x-show="Number(row.difficulty_counts[level.value] || 0) > 0"
                                                                    :class="Number(row.difficulty_counts[level.value] || 0) > (row.eligible.by_difficulty?.[level.value] ?? 0) ? 'text-error font-semibold' : 'text-on-surface-variant'">
                                                                    <span x-text="level.label"></span>:
                                                                    <span x-text="row.eligible.by_difficulty?.[level.value] ?? 0"></span>
                                                                </div>
                                                            </template>
                                                            <div class="font-semibold text-on-surface" x-text="'Σ ' + (row.eligible.total ?? 0)"></div>
                                                        </div>
                                                    </template>
                                                    <span x-show="!row.eligible" class="text-on-surface-variant">…</span>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3" x-show="blueprintId">
                            <p class="text-sm text-on-surface-variant" x-show="!selectedSectionIds.length">
                                Chọn ít nhất một phần để hiện chủ đề phân bổ.
                            </p>
                            <p class="text-sm text-on-surface-variant" x-show="selectedSectionIds.length && !allocationRows.length && !isLoadingTopics">
                                Không có chủ đề lâm sàng trong các phần đã chọn.
                            </p>
                            <p class="ml-auto text-sm text-on-surface-variant">
                                Tổng cấu hình: <strong x-text="configuredTotal">0</strong> câu
                            </p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-outline-variant bg-surface" x-show="!usesMatrixAllocation">
                        <div class="flex flex-col gap-3 border-b border-outline-variant px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 class="font-label-lg text-on-surface">Đề thi (chọn thủ công — tùy chọn)</h2>
                                <p class="mt-1 font-label-sm text-on-surface-variant">
                                    Dùng khi không phân bổ theo ma trận. Đã chọn <span x-text="selected.length">{{ $questionsCount }}</span> câu.
                                </p>
                            </div>

                            <div class="relative w-full lg:w-[360px]">
                                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                                <input type="search" x-model="search"
                                    placeholder="Tìm câu hỏi để thêm..."
                                    class="h-10 w-full rounded-lg border-none bg-surface-container-low py-2 pr-10 pl-10 font-body-sm text-on-surface focus:ring-2 focus:ring-primary">
                                <span x-show="isSearching" class="material-symbols-outlined pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 animate-spin text-[18px] text-primary">progress_activity</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 divide-y divide-outline-variant xl:grid-cols-[minmax(0,1fr)_360px] xl:divide-x xl:divide-y-0">
                            <div class="min-w-0 p-5">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h3 class="font-label-md text-on-surface">Câu hỏi đã chọn</h3>
                                    <span class="rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary-container" x-text="selected.length + ' câu'">{{ $questionsCount }} câu</span>
                                </div>

                                <div class="max-h-[620px] space-y-2 overflow-y-auto pr-1">
                                    <template x-for="(question, index) in selected" :key="question.id">
                                        <div
                                            draggable="true"
                                            @dragstart="startDrag(index, $event)"
                                            @dragover.prevent
                                            @drop.prevent="dropQuestion(index)"
                                            class="grid grid-cols-[40px_minmax(0,1fr)_40px] items-start gap-3 rounded-lg border border-outline-variant bg-surface-container-lowest p-3 transition-colors"
                                            :class="draggingIndex === index ? 'border-primary bg-primary/5' : 'hover:border-primary'"
                                        >
                                            <input type="hidden" :name="'questions[' + index + ']'" :value="question.id">

                                            <div class="flex flex-col items-center gap-1">
                                                <button type="button" class="flex size-7 items-center justify-center rounded-md text-on-surface-variant hover:bg-surface-container-high cursor-grab active:cursor-grabbing"
                                                    title="Kéo để sắp xếp"
                                                    @mousedown.prevent
                                                    @dragstart.prevent>
                                                    <span class="material-symbols-outlined text-[18px]">drag_indicator</span>
                                                </button>
                                                <button type="button" @click="moveUp(index)" :disabled="index === 0"
                                                    class="flex size-7 items-center justify-center rounded-md text-on-surface-variant hover:bg-surface-container-high disabled:opacity-30">
                                                    <span class="material-symbols-outlined text-[18px]">keyboard_arrow_up</span>
                                                </button>
                                                <span class="font-label-sm text-on-surface" x-text="index + 1"></span>
                                                <button type="button" @click="moveDown(index)" :disabled="index === selected.length - 1"
                                                    class="flex size-7 items-center justify-center rounded-md text-on-surface-variant hover:bg-surface-container-high disabled:opacity-30">
                                                    <span class="material-symbols-outlined text-[18px]">keyboard_arrow_down</span>
                                                </button>
                                            </div>

                                            <div class="min-w-0 pt-1">
                                                <div class="mb-1.5 flex flex-wrap gap-1.5">
                                                    <span class="rounded bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary" x-text="question.topic || 'Tổng hợp'"></span>
                                                    <span class="rounded bg-surface-container-high px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant" x-show="question.difficulty" x-text="question.difficulty"></span>
                                                </div>
                                                <p class="font-body-sm leading-6 text-on-surface" x-text="question.text"></p>
                                            </div>

                                            <button type="button" @click="removeQuestion(index)"
                                                class="flex size-9 items-center justify-center rounded-md text-error hover:bg-error/10">
                                                <span class="material-symbols-outlined text-[20px]">close</span>
                                            </button>
                                        </div>
                                    </template>

                                    <div x-show="selected.length === 0" class="rounded-lg border border-dashed border-outline-variant px-4 py-12 text-center">
                                        <p class="font-label-md text-on-surface">Chưa có câu hỏi nào</p>
                                        <p class="mt-1 font-label-sm text-on-surface-variant">Chọn câu hỏi từ thư viện bên phải hoặc phân bổ theo ma trận.</p>
                                    </div>
                                </div>
                            </div>

                            <aside class="min-w-0 bg-surface-container-lowest p-5">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h3 class="font-label-md text-on-surface">Thư viện câu hỏi</h3>
                                    <span class="font-label-sm text-on-surface-variant" x-text="filteredAvailable.length + ' câu'"></span>
                                </div>

                                <div class="max-h-[620px] space-y-2 overflow-y-auto pr-1">
                                    <template x-for="question in filteredAvailable" :key="question.id">
                                        <button type="button" @click="addQuestion(question)"
                                            class="block w-full rounded-lg border border-outline-variant bg-surface p-3 text-left hover:border-primary hover:bg-primary/5">
                                            <div class="mb-1.5 flex flex-wrap gap-1.5">
                                                <span class="rounded bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary" x-text="question.topic || 'Tổng hợp'"></span>
                                                <span class="rounded bg-surface-container-high px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant" x-show="question.difficulty" x-text="question.difficulty"></span>
                                            </div>
                                            <span class="line-clamp-3 font-body-sm leading-6 text-on-surface" x-text="question.text"></span>
                                            <span class="mt-2 inline-flex items-center gap-1 font-label-sm text-primary">
                                                <span class="material-symbols-outlined text-[16px]">add_circle</span>
                                                Thêm vào đề
                                            </span>
                                        </button>
                                    </template>

                                    <div x-show="filteredAvailable.length === 0" class="rounded-lg border border-dashed border-outline-variant bg-surface px-4 py-10 text-center">
                                        <p class="font-label-md text-on-surface">Không còn câu phù hợp</p>
                                        <p class="mt-1 font-label-sm text-on-surface-variant">Thử đổi từ khóa hoặc kiểm tra câu đã thêm.</p>
                                    </div>
                                </div>
                            </aside>
                        </div>
                </section>

                <section x-show="usesMatrixAllocation" class="rounded-lg border border-outline-variant bg-surface-container-lowest px-5 py-4">
                    <p class="font-label-md text-on-surface">Đang dùng phân bổ ma trận</p>
                    <p class="mt-1 font-body-sm text-on-surface-variant">
                        Hệ thống sẽ tự chọn câu từ exam pool theo chủ đề × mức độ khi lưu. Chọn câu thủ công bị ẩn để tránh nhầm lẫn.
                    </p>
                </section>
            </main>

            <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
                <section class="rounded-lg border border-outline-variant bg-surface p-5">
                    <h2 class="font-label-lg text-on-surface">Checklist</h2>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px] text-primary">check_circle</span>
                            <span class="font-body-sm text-on-surface">Thông tin kỳ thi</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]" :class="(configuredTotal > 0 || selected.length > 0) ? 'text-primary' : 'text-outline'">check_circle</span>
                            <span class="font-body-sm text-on-surface">Có phân bổ hoặc câu hỏi</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]" :class="duration > 0 ? 'text-primary' : 'text-outline'">check_circle</span>
                            <span class="font-body-sm text-on-surface">Thời gian hợp lệ</span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </form>

    <script>
        function examQuestions() {
            const difficultyLevels = @json($difficultyLevels);
            const emptyCounts = () => Object.fromEntries(difficultyLevels.map((level) => [level.value, 0]));

            return {
                available: @json($availableQuestionsMapped),
                selected: @json($questionRows),
                difficultyLevels,
                blueprints: [],
                sections: [],
                blueprintId: @json($initialBlueprintId ? (string) $initialBlueprintId : ''),
                selectedSectionIds: @json(array_map('intval', $initialSectionIds)),
                savedTopicAllocations: @json($initialExamTopics),
                allocationRows: [],
                blueprintsUrl: @json(route('admin.taxonomy.lookups.blueprints')),
                sectionsUrlTemplate: @json(route('admin.taxonomy.lookups.sections', ['blueprint' => '__BP__'])),
                topicsUrl: @json(route('admin.taxonomy.lookups.core-topics.search')),
                eligibilityUrl: @json(route('admin.exams.topic-eligibility')),
                search: '',
                duration: @json($duration),
                draggingIndex: null,
                isSearching: false,
                isLoadingSections: false,
                isLoadingTopics: false,
                searchTimeout: null,
                eligibilityTimeout: null,
                async init() {
                    this.$watch('search', (value) => {
                        this.fetchQuestions(value);
                    });
                    await this.loadBlueprints();
                    if (this.blueprintId) {
                        await this.loadSections(false);
                        if (this.selectedSectionIds.length) {
                            await this.loadTopicsFromSections(true);
                        }
                    }
                },
                get usesMatrixAllocation() {
                    return this.configuredTotal > 0;
                },
                get configuredTotal() {
                    return this.allocationRows.reduce((sum, row) => sum + this.rowTotal(row), 0);
                },
                get groupedAllocation() {
                    const map = new Map();
                    this.allocationRows.forEach((row, index) => {
                        row.formIndex = index;
                        const key = Number(row.section_id) || 0;
                        if (!map.has(key)) {
                            map.set(key, {
                                section_id: key,
                                section_name: row.section_name || '',
                                rows: [],
                            });
                        }
                        map.get(key).rows.push(row);
                    });
                    return Array.from(map.values());
                },
                rowTotal(row) {
                    return this.difficultyLevels.reduce(
                        (sum, level) => sum + (Number(row.difficulty_counts?.[level.value]) || 0),
                        0,
                    );
                },
                async loadBlueprints() {
                    const res = await fetch(this.blueprintsUrl);
                    const json = await res.json();
                    this.blueprints = json.data ?? [];
                },
                async onBlueprintChange() {
                    this.selectedSectionIds = [];
                    this.sections = [];
                    this.allocationRows = [];
                    this.savedTopicAllocations = [];
                    if (!this.blueprintId) {
                        return;
                    }
                    await this.loadSections(true);
                },
                async loadSections() {
                    if (!this.blueprintId) {
                        return;
                    }
                    this.isLoadingSections = true;
                    try {
                        const url = this.sectionsUrlTemplate.replace('__BP__', encodeURIComponent(this.blueprintId));
                        const res = await fetch(url);
                        const json = await res.json();
                        this.sections = json.data ?? [];
                    } finally {
                        this.isLoadingSections = false;
                    }
                },
                async toggleSection(sectionId) {
                    const id = Number(sectionId);
                    const idx = this.selectedSectionIds.indexOf(id);
                    if (idx >= 0) {
                        this.selectedSectionIds.splice(idx, 1);
                    } else {
                        this.selectedSectionIds.push(id);
                    }
                    await this.loadTopicsFromSections(false);
                },
                async loadTopicsFromSections(preserveSavedCounts) {
                    if (!this.selectedSectionIds.length) {
                        this.allocationRows = [];
                        return;
                    }

                    this.isLoadingTopics = true;
                    try {
                        const params = new URLSearchParams();
                        this.selectedSectionIds.forEach((id) => params.append('section_ids[]', String(id)));
                        const res = await fetch(`${this.topicsUrl}?${params}`);
                        const json = await res.json();
                        const topics = json.data ?? [];
                        const previous = new Map(
                            this.allocationRows.map((row) => [Number(row.core_clinical_topic_id), row]),
                        );
                        const saved = new Map(
                            (this.savedTopicAllocations || []).map((row) => [Number(row.core_clinical_topic_id), row]),
                        );

                        this.allocationRows = topics.map((topic) => {
                            const id = Number(topic.id);
                            const prev = previous.get(id);
                            const fromSaved = preserveSavedCounts ? saved.get(id) : null;
                            const counts = { ...emptyCounts(), ...(prev?.difficulty_counts || fromSaved?.difficulty_counts || {}) };
                            this.difficultyLevels.forEach((level) => {
                                counts[level.value] = Number(counts[level.value] || 0);
                            });

                            return {
                                core_clinical_topic_id: id,
                                topic_name: topic.name,
                                section_id: topic.blueprint_section_id,
                                section_name: topic.section_name || '',
                                difficulty_counts: counts,
                                eligible: prev?.eligible ?? null,
                                formIndex: 0,
                            };
                        });

                        this.refreshEligibility();
                    } finally {
                        this.isLoadingTopics = false;
                    }
                },
                async refreshEligibility() {
                    clearTimeout(this.eligibilityTimeout);
                    this.eligibilityTimeout = setTimeout(async () => {
                        const ids = this.allocationRows
                            .map((row) => Number(row.core_clinical_topic_id))
                            .filter((id) => id > 0);
                        if (!ids.length) {
                            return;
                        }
                        const params = new URLSearchParams();
                        ids.forEach((id) => params.append('core_clinical_topic_ids[]', String(id)));
                        const res = await fetch(`${this.eligibilityUrl}?${params}`);
                        const json = await res.json();
                        const counts = json.data ?? {};
                        this.allocationRows.forEach((row) => {
                            const id = Number(row.core_clinical_topic_id);
                            row.eligible = id > 0 ? (counts[id] ?? null) : null;
                        });
                    }, 250);
                },
                fetchQuestions(term) {
                    this.isSearching = true;
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        fetch(`/admin/exams/questions/search?q=${encodeURIComponent(term.trim())}`)
                            .then(res => res.json())
                            .then(data => {
                                this.available = data;
                                this.isSearching = false;
                            })
                            .catch(err => {
                                console.error('Search failed:', err);
                                this.isSearching = false;
                            });
                    }, 300);
                },
                get filteredAvailable() {
                    const selectedIds = new Set(this.selected.map((question) => String(question.id)));
                    return this.available.filter((question) => !selectedIds.has(String(question.id)));
                },
                addQuestion(question) {
                    if (!question || this.selected.find((item) => item.id == question.id)) return;
                    this.selected.push({
                        id: question.id,
                        text: question.text,
                        topic: question.topic,
                        topics: question.topics || [],
                        difficulty: question.difficulty
                    });
                },
                removeQuestion(index) {
                    this.selected.splice(index, 1);
                },
                startDrag(index, event) {
                    this.draggingIndex = index;
                    event.dataTransfer?.setData('text/plain', String(index));
                    event.dataTransfer?.setDragImage?.(event.currentTarget, 0, 0);
                },
                dropQuestion(index) {
                    if (this.draggingIndex === null || this.draggingIndex === index) {
                        this.draggingIndex = null;
                        return;
                    }

                    const moved = this.selected.splice(this.draggingIndex, 1)[0];
                    const targetIndex = this.draggingIndex < index ? index - 1 : index;
                    this.selected.splice(targetIndex, 0, moved);
                    this.draggingIndex = null;
                },
                endDrag() {
                    this.draggingIndex = null;
                },
                moveUp(index) {
                    if (index <= 0) return;
                    const previous = this.selected[index - 1];
                    this.selected[index - 1] = this.selected[index];
                    this.selected[index] = previous;
                },
                moveDown(index) {
                    if (index >= this.selected.length - 1) return;
                    const next = this.selected[index + 1];
                    this.selected[index + 1] = this.selected[index];
                    this.selected[index] = next;
                },
            };
        }
    </script>
</x-layouts.admin>
