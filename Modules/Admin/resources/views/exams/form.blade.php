@php
    $isNew = ! $exam->exists;
    $availableQuestions = $availableQuestions ?? collect();
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
    $initialExamTopics = old('exam_topics');
    if (! is_array($initialExamTopics)) {
        $initialExamTopics = $exam->exists && $exam->relationLoaded('examTopics')
            ? $exam->examTopics->map(fn ($row) => [
                'core_clinical_topic_id' => $row->core_clinical_topic_id,
                'question_count' => $row->question_count,
                'sort_order' => $row->sort_order,
                'topic_name' => $row->coreClinicalTopic?->name,
                'section_name' => $row->coreClinicalTopic?->section?->name,
            ])->values()->all()
            : [];
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
                        ? 'Nhập thông tin, chọn câu hỏi và lưu nháp hoặc xuất bản ngay trong một trang.'
                        : 'Cấu hình nội dung hiển thị, thời gian, câu hỏi và trạng thái kỳ thi trong cùng một màn.' }}
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
                <p class="mt-1 font-headline-sm text-headline-sm text-on-surface" x-text="selected.length">{{ $questionsCount }}</p>
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
                            Cấu hình số câu theo chủ đề lâm sàng — hệ thống tự lấy từ exam pool (<code>private</code> + <code>exam_flag</code>).
                        </p>
                    </div>
                    <div class="space-y-4 p-5">
                        @error('exam_topics')
                            <p class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">{{ $message }}</p>
                        @enderror

                        <div class="overflow-x-auto rounded-lg border border-outline-variant">
                            <table class="min-w-full text-sm">
                                <thead class="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        <th class="px-3 py-2">Chủ đề</th>
                                        <th class="px-3 py-2 w-28">Số câu</th>
                                        <th class="px-3 py-2 w-28">Eligible</th>
                                        <th class="px-3 py-2 w-16"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in examTopics" :key="'topic-row-' + index">
                                        <tr class="border-t border-outline-variant/60">
                                            <td class="px-3 py-2">
                                                <input type="hidden" :name="'exam_topics[' + index + '][core_clinical_topic_id]'" :value="row.core_clinical_topic_id || ''">
                                                <input type="hidden" :name="'exam_topics[' + index + '][sort_order]'" :value="index">
                                                <div class="relative">
                                                    <input type="search" x-model="row.topicSearch" @focus="row.showPicker = true"
                                                        @input.debounce.300ms="searchExamTopic(index)"
                                                        :placeholder="row.topic_name || 'Tìm core clinical topic...'"
                                                        class="w-full rounded-lg bg-surface-container-low px-3 py-2 text-sm">
                                                    <div x-show="row.showPicker" @click.outside="row.showPicker = false"
                                                        class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-outline-variant bg-white shadow-lg">
                                                        <template x-for="item in row.topicResults" :key="item.id">
                                                            <button type="button" @click="selectExamTopic(index, item)"
                                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-container-low">
                                                                <span x-text="item.name"></span>
                                                                <span x-show="item.section_name" class="ml-1 text-xs text-on-surface-variant" x-text="'(' + item.section_name + ')'"></span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                                <p x-show="row.section_name" class="mt-1 text-xs text-on-surface-variant" x-text="row.section_name"></p>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="1" :name="'exam_topics[' + index + '][question_count]'"
                                                    x-model.number="row.question_count" @change="refreshEligibility()"
                                                    class="w-full rounded-lg bg-surface-container-low px-2 py-2 text-center">
                                            </td>
                                            <td class="px-3 py-2 text-center font-semibold"
                                                :class="row.eligible !== null && row.question_count > row.eligible ? 'text-error' : 'text-on-surface'"
                                                x-text="row.eligible ?? '…'"></td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" @click="removeExamTopic(index)" class="text-error hover:underline">Xóa</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" @click="addExamTopic()"
                                class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-2 text-sm font-semibold hover:bg-surface-container-low">
                                <span class="material-symbols-outlined text-[18px]">add</span>
                                Thêm chủ đề
                            </button>
                            <p class="text-sm text-on-surface-variant">
                                Tổng cấu hình: <strong x-text="examTopics.reduce((sum, row) => sum + (Number(row.question_count) || 0), 0)"></strong> câu
                            </p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-outline-variant bg-surface">
                        <div class="flex flex-col gap-3 border-b border-outline-variant px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 class="font-label-lg text-on-surface">Đề thi (chọn thủ công — tùy chọn)</h2>
                                <p class="mt-1 font-label-sm text-on-surface-variant">
                                    Đã chọn <span x-text="selected.length">{{ $questionsCount }}</span> câu.
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
                                        <p class="mt-1 font-label-sm text-on-surface-variant">Chọn câu hỏi từ thư viện bên phải để tạo đề.</p>
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
                            <span class="material-symbols-outlined text-[20px]" :class="selected.length > 0 ? 'text-primary' : 'text-outline'">check_circle</span>
                            <span class="font-body-sm text-on-surface">Có ít nhất 1 câu hỏi</span>
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
            return {
                available: @json($availableQuestionsMapped),
                selected: @json($questionRows),
                examTopics: @json($initialExamTopics).map((row) => ({
                    core_clinical_topic_id: row.core_clinical_topic_id ?? null,
                    question_count: row.question_count ?? 1,
                    sort_order: row.sort_order ?? 0,
                    topic_name: row.topic_name ?? '',
                    section_name: row.section_name ?? '',
                    topicSearch: row.topic_name ?? '',
                    topicResults: [],
                    showPicker: false,
                    eligible: null,
                })),
                topicSearchUrl: @json(route('admin.taxonomy.lookups.core-topics.search')),
                eligibilityUrl: @json(route('admin.exams.topic-eligibility')),
                search: '',
                duration: @json($duration),
                draggingIndex: null,
                isSearching: false,
                searchTimeout: null,
                init() {
                    this.$watch('search', (value) => {
                        this.fetchQuestions(value);
                    });
                    this.refreshEligibility();
                },
                addExamTopic() {
                    this.examTopics.push({
                        core_clinical_topic_id: null,
                        question_count: 1,
                        sort_order: this.examTopics.length,
                        topic_name: '',
                        section_name: '',
                        topicSearch: '',
                        topicResults: [],
                        showPicker: false,
                        eligible: null,
                    });
                },
                removeExamTopic(index) {
                    this.examTopics.splice(index, 1);
                    this.refreshEligibility();
                },
                async searchExamTopic(index) {
                    const row = this.examTopics[index];
                    const q = (row.topicSearch || '').trim();
                    if (q.length < 2) {
                        row.topicResults = [];
                        return;
                    }
                    const res = await fetch(`${this.topicSearchUrl}?q=${encodeURIComponent(q)}`);
                    const json = await res.json();
                    row.topicResults = json.data ?? [];
                    row.showPicker = true;
                },
                selectExamTopic(index, item) {
                    const row = this.examTopics[index];
                    row.core_clinical_topic_id = item.id;
                    row.topic_name = item.name;
                    row.section_name = item.section_name || '';
                    row.topicSearch = item.name;
                    row.showPicker = false;
                    this.refreshEligibility();
                },
                async refreshEligibility() {
                    const ids = this.examTopics
                        .map((row) => Number(row.core_clinical_topic_id))
                        .filter((id) => id > 0);
                    if (!ids.length) {
                        this.examTopics.forEach((row) => { row.eligible = null; });
                        return;
                    }
                    const params = new URLSearchParams();
                    ids.forEach((id) => params.append('core_clinical_topic_ids[]', String(id)));
                    const res = await fetch(`${this.eligibilityUrl}?${params}`);
                    const json = await res.json();
                    const counts = json.data ?? {};
                    this.examTopics.forEach((row) => {
                        const id = Number(row.core_clinical_topic_id);
                        row.eligible = id > 0 ? (counts[id] ?? 0) : null;
                    });
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
