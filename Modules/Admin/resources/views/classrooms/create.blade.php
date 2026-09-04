@php
    use Modules\Classroom\Enums\ClassroomPurpose;

    $initialSource = old('content_source', 'questions');
    $selectedQuestionRows = $selectedQuestions->map(fn ($question) => [
        'id' => (string) $question->getKey(),
        'code' => $question->code,
        'text' => trim(strip_tags(html_entity_decode($question->stem, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
        'topic' => $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp',
        'core_topic' => $question->coreClinicalTopics->pluck('name')->join(', '),
        'difficulty' => $question->difficulty->label(),
        'feedback_count' => (int) ($question->open_feedback_count ?? 0),
        'edit_url' => route('admin.questions.edit', $question),
    ])->values();
@endphp

<x-layouts.admin title="Tạo lớp học">
    <div x-data="adminClassroomCreator(@js(route('admin.classrooms.content.questions')), @js($initialSource), @js($selectedQuestionRows))"
        x-init="init()"
        class="space-y-6">
        <x-admin.page-header title="Tạo lớp học"
            description="Tạo lớp cho giảng viên và chuẩn bị sẵn nội dung chữa bài trong cùng một quy trình.">
            <x-slot:actions>
                <a href="{{ route('admin.classrooms.index') }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-outline-variant bg-surface px-4 py-2.5 font-label-md text-label-md font-semibold text-on-surface hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Danh sách lớp
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.flash />

        @if ($errors->any())
            <section role="alert" class="rounded-xl border border-error/30 bg-error-container p-5 text-on-error-container">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined shrink-0 text-[22px]" aria-hidden="true">error</span>
                    <div>
                        <h2 class="font-semibold">Chưa thể tạo lớp học</h2>
                        <ul class="mt-1.5 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>
        @endif

        <form method="post" action="{{ route('admin.classrooms.store') }}"
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
            novalidate>
            @csrf
            <input type="hidden" name="purpose" :value="source === 'exam' ? @js(ClassroomPurpose::ExamReview->value) : @js(ClassroomPurpose::FeedbackReview->value)">
            <input type="hidden" name="content_source" :value="source">

            <main class="space-y-6">
                <section class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-start gap-3 border-b border-outline-variant pb-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <span class="material-symbols-outlined text-[22px]">school</span>
                        </span>
                        <div>
                            <h2 class="font-title-md text-title-md font-semibold text-on-surface">1. Thông tin lớp và giảng viên</h2>
                            <p class="mt-0.5 text-sm text-on-surface-variant">Lớp được admin duyệt ngay và bàn giao cho giảng viên đã chọn.</p>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="md:col-span-2 block" for="host_user_id">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">
                                Giảng viên phụ trách <span class="text-error">*</span>
                            </span>
                            <select id="host_user_id" name="host_user_id" required
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option value="">-- Chọn giảng viên đảm nhận lớp --</option>
                                @foreach ($instructors as $instructor)
                                    <option value="{{ $instructor->id }}" @selected((string) old('host_user_id') === (string) $instructor->id)>
                                        {{ $instructor->name }} ({{ $instructor->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('host_user_id')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                        </label>

                        <label class="md:col-span-2 block" for="title">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">
                                Tên lớp <span class="text-error">*</span>
                            </span>
                            <input id="title" name="title" value="{{ old('title') }}" required maxlength="200"
                                placeholder="Ví dụ: Chữa câu hỏi Nội khoa — tuần 12"
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            @error('title')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                        </label>

                        <label class="md:col-span-2 block" for="description">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Mô tả lớp học</span>
                            <textarea id="description" name="description" rows="3" maxlength="5000"
                                placeholder="Mục tiêu, phạm vi nội dung và đối tượng học viên..."
                                class="w-full resize-y rounded-lg border border-outline-variant bg-surface px-3 py-2.5 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ old('description') }}</textarea>
                        </label>

                        <label class="block" for="visibility">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">
                                Trạng thái hiển thị <span class="text-error">*</span>
                            </span>
                            <select id="visibility" name="visibility" required
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                @foreach ($visibilities as $visibility)
                                    <option value="{{ $visibility->value }}" @selected(old('visibility', 'public') === $visibility->value)>
                                        {{ $visibility->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block" for="max_members">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Số học viên tối đa</span>
                            <input id="max_members" name="max_members" type="number" min="2" max="5000"
                                value="{{ old('max_members') }}" placeholder="Không giới hạn"
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-start gap-3 border-b border-outline-variant pb-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <span class="material-symbols-outlined text-[22px]">library_books</span>
                        </span>
                        <div>
                            <h2 class="font-title-md text-title-md font-semibold text-on-surface">2. Chọn nguồn nội dung</h2>
                            <p class="mt-0.5 text-sm text-on-surface-variant">Truy cập toàn bộ câu đã xuất bản, đề thi và hàng chờ phản hồi.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <button type="button" @click="chooseSource('questions')"
                            :class="source === 'questions' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-outline-variant bg-surface hover:border-primary/50'"
                            class="rounded-xl border p-4 text-left transition">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <span class="material-symbols-outlined text-[20px]">quiz</span>
                            </span>
                            <span class="mt-3 block font-semibold text-on-surface">Ngân hàng câu hỏi</span>
                            <span class="mt-1 block text-xs text-on-surface-variant">{{ number_format($contentCounts['questions']) }} câu đã xuất bản</span>
                        </button>

                        <button type="button" @click="chooseSource('exam')"
                            :class="source === 'exam' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-outline-variant bg-surface hover:border-primary/50'"
                            class="rounded-xl border p-4 text-left transition">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <span class="material-symbols-outlined text-[20px]">assignment</span>
                            </span>
                            <span class="mt-3 block font-semibold text-on-surface">Bài thi Exam</span>
                            <span class="mt-1 block text-xs text-on-surface-variant">{{ number_format($contentCounts['exams']) }} đề đã xuất bản</span>
                        </button>

                        <button type="button" @click="chooseSource('feedback')"
                            :class="source === 'feedback' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-outline-variant bg-surface hover:border-primary/50'"
                            class="rounded-xl border p-4 text-left transition">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-error/10 text-error">
                                <span class="material-symbols-outlined text-[20px]">feedback</span>
                            </span>
                            <span class="mt-3 block font-semibold text-on-surface">Câu cần chữa feedback</span>
                            <span class="mt-1 block text-xs text-on-surface-variant">{{ number_format($contentCounts['feedback']) }} câu cần xử lý</span>
                        </button>
                    </div>

                    <div x-show="source === 'exam'" x-cloak class="mt-6 border-t border-outline-variant pt-5">
                        <div class="grid max-h-[520px] gap-3 overflow-y-auto pr-1 md:grid-cols-2">
                            @forelse ($publishedExams as $exam)
                                <label class="cursor-pointer rounded-xl border border-outline-variant bg-surface p-4 transition hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:checked]:ring-2 has-[:checked]:ring-primary/20">
                                    <span class="flex items-start gap-3">
                                        <input type="radio" name="exam_id" value="{{ $exam->id }}" @checked((string) old('exam_id') === (string) $exam->id)
                                            class="mt-1 size-4 text-primary focus:ring-primary/30">
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-semibold text-on-surface">{{ $exam->title }}</span>
                                            <span class="mt-1 flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
                                                <span>{{ $exam->questions_count }} câu</span>
                                                <span>·</span>
                                                <span>{{ $exam->duration_minutes }} phút</span>
                                            </span>
                                            @if ($exam->description)
                                                <span class="mt-2 line-clamp-2 block text-xs text-on-surface-variant">{{ $exam->description }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @empty
                                <div class="col-span-2 rounded-xl border border-dashed border-outline-variant py-12 text-center text-sm text-on-surface-variant">
                                    Chưa có đề thi nào đã xuất bản.
                                </div>
                            @endforelse
                        </div>
                        @error('exam_id')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="source === 'questions' || source === 'feedback'" x-cloak class="mt-6 grid gap-5 border-t border-outline-variant pt-5 lg:grid-cols-2">
                        <div class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-on-surface">Câu hỏi đã chọn</h3>
                                <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary" x-text="selected.length + ' câu'"></span>
                            </div>
                            <div class="max-h-[460px] space-y-2 overflow-y-auto pr-1">
                                <template x-for="(question, index) in selected" :key="question.id">
                                    <article class="flex items-start gap-3 rounded-lg border border-outline-variant bg-surface p-3">
                                        <input type="hidden" name="question_ids[]" :value="question.id">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-xs font-bold text-primary" x-text="index + 1"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="line-clamp-2 text-xs leading-relaxed text-on-surface" x-text="question.text"></p>
                                            <p class="mt-1 text-[11px] text-on-surface-variant" x-text="question.topic + ' · ' + question.difficulty"></p>
                                        </div>
                                        <button type="button" @click="remove(question.id)"
                                            class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-on-surface-variant hover:bg-error/10 hover:text-error"
                                            aria-label="Bỏ câu hỏi">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                        </button>
                                    </article>
                                </template>
                                <div x-show="selected.length === 0" class="rounded-lg border border-dashed border-outline-variant py-12 text-center">
                                    <span class="material-symbols-outlined text-3xl text-on-surface-variant/40">playlist_add</span>
                                    <p class="mt-2 text-xs text-on-surface-variant">Chưa chọn câu hỏi nào từ thư viện.</p>
                                </div>
                            </div>
                            @error('question_ids')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0 rounded-xl border border-outline-variant bg-surface p-4">
                            <label for="content-search" class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Tìm kiếm câu hỏi</label>
                            <div class="relative">
                                <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                                <input id="content-search" type="search" x-model="search" @input.debounce.350ms="loadQuestions(true)"
                                    placeholder="Tìm nội dung, mã câu hoặc chuyên khoa..."
                                    class="h-10 w-full rounded-lg border border-outline-variant bg-surface py-2 pl-10 pr-10 text-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <span x-show="loading" class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 animate-spin text-[18px] text-primary">progress_activity</span>
                            </div>

                            <div class="mt-2.5 grid gap-2 sm:grid-cols-3 lg:grid-cols-1 2xl:grid-cols-3">
                                <select x-model="coreTopicId" @change="loadQuestions(true)"
                                    class="h-9 w-full rounded-lg border border-outline-variant bg-surface px-2.5 text-xs text-on-surface focus:border-primary focus:outline-none">
                                    <option value="">Tất cả chủ đề lâm sàng</option>
                                    @foreach ($coreTopicOptions as $topic)
                                        <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                                    @endforeach
                                </select>

                                <select x-model="medicalTopicId" @change="loadQuestions(true)"
                                    class="h-9 w-full rounded-lg border border-outline-variant bg-surface px-2.5 text-xs text-on-surface focus:border-primary focus:outline-none">
                                    <option value="">Tất cả phân loại y khoa</option>
                                    @foreach ($medicalTopicOptions as $topic)
                                        <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                                    @endforeach
                                </select>

                                <select x-model="difficulty" @change="loadQuestions(true)"
                                    class="h-9 w-full rounded-lg border border-outline-variant bg-surface px-2.5 text-xs text-on-surface focus:border-primary focus:outline-none">
                                    <option value="">Tất cả độ khó</option>
                                    @foreach ($difficulties as $difficulty)
                                        <option value="{{ $difficulty->value }}">{{ $difficulty->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <p class="mt-2.5 text-[11px] text-on-surface-variant">
                                Tìm thấy <span class="font-semibold text-on-surface" x-text="total"></span> câu hỏi phù hợp.
                            </p>

                            <div class="mt-3 max-h-[380px] space-y-2 overflow-y-auto pr-1">
                                <template x-for="question in available" :key="question.id">
                                    <article class="rounded-lg border border-outline-variant bg-surface-container-lowest p-3 transition hover:border-primary">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="mb-1.5 flex flex-wrap gap-1 text-[10px] font-semibold uppercase">
                                                    <span class="rounded-md bg-primary/10 px-2 py-0.5 text-primary" x-text="question.topic"></span>
                                                    <span x-show="question.core_topic" class="rounded-md bg-surface-container px-2 py-0.5 text-on-surface-variant" x-text="question.core_topic"></span>
                                                    <span class="rounded-md bg-surface-container px-2 py-0.5 text-on-surface-variant" x-text="question.difficulty"></span>
                                                    <span x-show="question.feedback_count > 0" class="rounded-md bg-error/10 px-2 py-0.5 text-error" x-text="question.feedback_count + ' phản hồi'"></span>
                                                </div>
                                                <p class="line-clamp-3 text-xs leading-relaxed text-on-surface" x-text="question.text"></p>
                                            </div>
                                            <button type="button" @click="add(question)" :disabled="isSelected(question.id)"
                                                class="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-primary text-on-primary hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                                aria-label="Thêm câu hỏi">
                                                <span class="material-symbols-outlined text-[16px]" x-text="isSelected(question.id) ? 'check' : 'add'"></span>
                                            </button>
                                        </div>
                                        <a x-show="source === 'feedback'" :href="question.edit_url" target="_blank"
                                            class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline">
                                            Mở câu hỏi để chỉnh sửa <span class="material-symbols-outlined text-[12px]">open_in_new</span>
                                        </a>
                                    </article>
                                </template>
                                <p x-show="!loading && available.length === 0" class="py-10 text-center text-xs text-on-surface-variant">
                                    Không tìm thấy câu hỏi phù hợp.
                                </p>
                            </div>
                            <button type="button" x-show="page < lastPage" @click="loadMore()" :disabled="loading"
                                class="mt-3 w-full rounded-lg border border-outline-variant bg-surface py-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low disabled:opacity-50">
                                Tải thêm câu hỏi
                            </button>
                        </div>
                    </div>
                </section>

                <section x-show="source !== 'none'" x-cloak class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-start gap-3 border-b border-outline-variant pb-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <span class="material-symbols-outlined text-[22px]">event</span>
                        </span>
                        <div>
                            <h2 class="font-title-md text-title-md font-semibold text-on-surface">3. Buổi trực tiếp đầu tiên</h2>
                            <p class="mt-0.5 text-sm text-on-surface-variant">Nội dung đã chọn sẽ được nạp sẵn vào buổi học này.</p>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="md:col-span-2 block" for="session_title">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">
                                Tên buổi trực tiếp <span class="text-error">*</span>
                            </span>
                            <input id="session_title" name="session_title" value="{{ old('session_title') }}" maxlength="200"
                                placeholder="Ví dụ: Buổi 1 — Chữa câu hỏi trọng tâm"
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            @error('session_title')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                        </label>

                        <label class="block" for="scheduled_at">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Thời gian dự kiến</span>
                            <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}"
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        </label>

                        <label class="block" for="expected_duration_minutes">
                            <span class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Thời lượng dự kiến</span>
                            <select id="expected_duration_minutes" name="expected_duration_minutes"
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                @foreach ([30, 45, 60, 90, 120, 180] as $minutes)
                                    <option value="{{ $minutes }}" @selected((int) old('expected_duration_minutes', 60) === $minutes)>
                                        {{ $minutes }} phút
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>
            </main>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
                    <span class="inline-block rounded-full bg-primary/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-primary">
                        Tóm tắt thiết lập
                    </span>
                    <h2 class="mt-3 font-title-md text-title-md font-semibold text-on-surface">Sẵn sàng tạo lớp</h2>

                    <dl class="mt-4 divide-y divide-outline-variant text-sm">
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="text-on-surface-variant">Nguồn nội dung</dt>
                            <dd class="font-semibold text-on-surface" x-text="sourceLabel()"></dd>
                        </div>
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="text-on-surface-variant">Số câu đã chọn</dt>
                            <dd class="font-semibold text-on-surface" x-text="source === 'exam' ? 'Theo đề thi' : selected.length + ' câu'"></dd>
                        </div>
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="text-on-surface-variant">Trạng thái lớp</dt>
                            <dd class="font-semibold text-primary">Duyệt ngay</dd>
                        </div>
                    </dl>

                    <div class="mt-4 rounded-lg bg-primary/5 p-3.5 text-xs leading-relaxed text-on-surface-variant">
                        Giảng viên có thể sử dụng ngay bộ nội dung admin đã chuẩn bị sau khi lớp được tạo.
                    </div>

                    <div class="mt-5 space-y-2">
                        <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                            <span class="material-symbols-outlined text-[20px]">add_circle</span>
                            <span x-text="source === 'none' ? 'Tạo lớp học' : 'Tạo lớp và nạp nội dung'">Tạo lớp và nạp nội dung</span>
                        </button>

                        <button type="button" @click="chooseSource('none')"
                            class="w-full rounded-lg border border-outline-variant bg-surface px-4 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface">
                            Chỉ tạo lớp, thêm nội dung sau
                        </button>
                    </div>

                    <p x-show="source === 'none'" x-cloak class="mt-2.5 text-center text-xs font-medium text-tertiary">
                        Đang chọn chế độ chỉ tạo lớp.
                    </p>
                </section>
            </aside>
        </form>
    </div>

    <script>
        function adminClassroomCreator(searchUrl, initialSource, initialSelected) {
            return {
                source: initialSource,
                selected: initialSelected,
                available: [],
                search: '',
                coreTopicId: '',
                medicalTopicId: '',
                difficulty: '',
                loading: false,
                page: 1,
                lastPage: 1,
                total: 0,
                init() {
                    if (this.source === 'questions' || this.source === 'feedback') {
                        this.loadQuestions(true);
                    }
                },
                chooseSource(source) {
                    const switchesQuestionLibrary = ['questions', 'feedback'].includes(this.source)
                        && ['questions', 'feedback'].includes(source)
                        && this.source !== source;
                    this.source = source;
                    if (switchesQuestionLibrary) {
                        this.selected = [];
                    }
                    if (source === 'questions' || source === 'feedback') {
                        this.loadQuestions(true);
                    }
                },
                sourceLabel() {
                    return {
                        questions: 'Ngân hàng câu hỏi',
                        exam: 'Bài thi Exam',
                        feedback: 'Câu có feedback',
                        none: 'Thêm sau',
                    }[this.source] || 'Thêm sau';
                },
                isSelected(id) {
                    return this.selected.some(question => question.id === id);
                },
                add(question) {
                    if (!this.isSelected(question.id)) {
                        this.selected.push(question);
                    }
                },
                remove(id) {
                    this.selected = this.selected.filter(question => question.id !== id);
                },
                async loadQuestions(reset = false) {
                    if (reset) {
                        this.page = 1;
                        this.available = [];
                    }
                    this.loading = true;
                    try {
                        const url = new URL(searchUrl, window.location.origin);
                        url.searchParams.set('source', this.source);
                        url.searchParams.set('q', this.search.trim());
                        url.searchParams.set('core_topic_id', this.coreTopicId);
                        url.searchParams.set('medical_topic_id', this.medicalTopicId);
                        url.searchParams.set('difficulty', this.difficulty);
                        url.searchParams.set('page', this.page);
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!response.ok) {
                            throw new Error('Không tải được thư viện nội dung.');
                        }
                        const payload = await response.json();
                        this.available = reset ? payload.data : [...this.available, ...payload.data];
                        this.page = payload.meta.current_page;
                        this.lastPage = payload.meta.last_page;
                        this.total = payload.meta.total;
                    } finally {
                        this.loading = false;
                    }
                },
                loadMore() {
                    if (this.page < this.lastPage && !this.loading) {
                        this.page += 1;
                        this.loadQuestions(false);
                    }
                },
            };
        }
    </script>
</x-layouts.admin>
