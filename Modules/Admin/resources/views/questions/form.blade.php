@php
    $isNew = ! $question->exists;
    $existingOptions = $question->relationLoaded('options')
        ? $question->options
        : collect();
    $oldOptions = old('options');

    if (is_array($oldOptions) && $oldOptions !== []) {
        $optionRows = collect($oldOptions)->map(fn ($row) => [
            'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
            'content' => (string) ($row['content'] ?? ''),
            'is_correct' => ($row['is_correct'] ?? false) === true
                || ($row['is_correct'] ?? false) === 1
                || ($row['is_correct'] ?? false) === '1',
            'explanation' => (string) ($row['explanation'] ?? ''),
        ])->values()->all();
    } elseif ($existingOptions->isEmpty()) {
        $optionRows = [
            ['id' => null, 'content' => '', 'is_correct' => true,  'explanation' => ''],
            ['id' => null, 'content' => '', 'is_correct' => false, 'explanation' => ''],
            ['id' => null, 'content' => '', 'is_correct' => false, 'explanation' => ''],
            ['id' => null, 'content' => '', 'is_correct' => false, 'explanation' => ''],
        ];
    } else {
        $optionRows = $existingOptions->map(fn ($o) => [
            'id'          => $o->id,
            'content'     => $o->content,
            'is_correct'  => (bool) $o->is_correct,
            'explanation' => $o->explanation,
        ])->values()->all();
    }
    $correctIndex = collect($optionRows)->search(fn ($row) => $row['is_correct'] === true);
    if ($correctIndex === false) { $correctIndex = 0; }

    $existingHints = $question->relationLoaded('hints') ? $question->hints : collect();
    $oldHints = old('hints');
    if (is_array($oldHints)) {
        $hintRows = collect($oldHints)->map(fn ($row) => [
            'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
            'content' => (string) ($row['content'] ?? ''),
        ])->values()->all();
    } elseif ($existingHints->isNotEmpty()) {
        $hintRows = $existingHints->map(fn ($h) => [
            'id' => $h->id,
            'content' => $h->content,
        ])->values()->all();
    } elseif (! empty($question->key_info)) {
        $hintRows = collect($question->key_info)->map(fn ($content) => [
            'id' => null,
            'content' => (string) $content,
        ])->values()->all();
    } else {
        $hintRows = [
            ['id' => null, 'content' => ''],
        ];
    }

    $isRejected = ! $isNew && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected;
    $isInstructorRejection = $isRejected && $question->isInstructorRejection();
    $isPublisherRejection = $isRejected && $question->isPublisherRejection();
    $statusBadge = ! $isNew ? match (true) {
        $isInstructorRejection => ['label' => 'Giảng viên từ chối', 'class' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'],
        $isPublisherRejection => ['label' => 'Admin trả về', 'class' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'],
        $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Published => ['label' => 'Đã xuất bản', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'],
        $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview => ['label' => 'Chờ giảng viên', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
        $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InFlagReview => ['label' => 'Chờ reviewer', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300'],
        $question->status === \Modules\QuestionBank\Enums\QuestionStatus::PendingPublish => ['label' => 'Chờ xuất bản', 'class' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300'],
        $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected => ['label' => 'Từ chối', 'class' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'],
        $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Private => ['label' => 'Riêng tư', 'class' => 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300'],
        $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Retired => ['label' => 'Ngừng dùng', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'],
        default => ['label' => 'Bản nháp', 'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
    } : null;
    $stemImagePath = old('stem_image_path', $question->stem_image_path);
    $stemImageUrl = filled($stemImagePath)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($stemImagePath)
        : null;
@endphp

<x-layouts.admin :title="$isNew ? 'Tạo câu hỏi mới' : ($canEditContent ? 'Chỉnh sửa câu hỏi' : 'Chi tiết câu hỏi')">

    {{-- ── HEADER ── --}}
    <header class="mb-6 flex flex-col gap-4 border-b border-outline-variant pb-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.index'))
<a href="{{ route('admin.questions.index') }}"
               aria-label="Quay lại danh sách câu hỏi"
               class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
            </a>
@endif
            <div class="min-w-0">
                <h1 class="font-headline-md text-headline-md font-bold text-on-surface">
                    {{ $isNew ? 'Tạo câu hỏi mới' : ($canEditContent ? 'Chỉnh sửa câu hỏi' : 'Chi tiết câu hỏi') }}
                </h1>
                @if (! $isNew && filled($question->code))
                    <p class="mt-1 font-mono text-sm font-semibold tracking-wide text-on-surface-variant"
                        title="Mã câu hỏi (không thay đổi)">
                        {{ $question->code }}
                    </p>
                @endif
                @if (! $isNew)
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 font-body-sm text-on-surface-variant" aria-label="Thông tin câu hỏi">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge['class'] }}">
                            {{ $statusBadge['label'] }}
                        </span>
                        <span>·</span>
                        @if ($question->version > 0)
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.versions.index'))
<a href="{{ route('admin.questions.versions.index', $question) }}"
                                class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                                title="Xem lịch sử phiên bản">
                                Phiên bản {{ $question->version }}
                                <span class="material-symbols-outlined text-[15px]">history</span>
                            </a>
@endif
                        @else
                            <span title="Phiên bản chỉ được tạo khi Admin xuất bản cấp cuối">Chưa có phiên bản</span>
                        @endif
                        @if ($question->published_version)
                            <span>·</span>
                            <a href="{{ route('admin.questions.compare', $question) }}"
                                class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                                title="So sánh với bản đang dùng">
                                So sánh
                                <span class="material-symbols-outlined text-[15px]">difference</span>
                            </a>
                        @endif
                        @if ($canViewAudit)
                            <span>·</span>
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.audit.index'))
<a href="{{ route('admin.audit.index', ['subject_type' => 'question', 'subject_id' => $question->id]) }}"
                                class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                                title="Xem nhật ký audit">
                                Nhật ký
                                <span class="material-symbols-outlined text-[15px]">policy</span>
                            </a>
@endif
                        @endif
                        <span>·</span>
                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.stats'))
<a href="{{ route('admin.questions.stats', $question) }}"
                            class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                            title="Thống kê chi tiết">
                            Thống kê
                            <span class="material-symbols-outlined text-[15px]">analytics</span>
                        </a>
@endif
                        <span>·</span>
                        <span>Cập nhật {{ $question->updated_at?->diffForHumans() }}</span>
                    </div>
                @else
                    <p class="mt-0.5 font-body-sm text-on-surface-variant">Soạn thảo câu hỏi và lưu bản nháp.</p>
                @endif
            </div>
        </div>

        @if (! $isNew && ($question->published_version || ($canDelete && ! $pendingReview)))
            <div class="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end lg:pt-0.5">
                @if ($question->published_version)
                    <a href="{{ route('admin.questions.compare', $question) }}"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">difference</span>
                        So sánh
                    </a>
                @endif
                @if ($canDelete && ! $pendingReview)
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.destroy'))
<form method="post" action="{{ route('admin.questions.destroy', $question) }}" aria-label="Xóa câu hỏi">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('{{ $isReviewer ? 'Xóa câu hỏi này?' : 'Gửi yêu cầu xóa câu hỏi này để admin duyệt?' }}')"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                        {{ $isReviewer ? 'Xóa' : 'Yêu cầu xóa' }}
                    </button>
                </form>
@endif
                @endif
            </div>
        @endif
    </header>

    <x-admin.flash />

    @if ($pendingReview)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined">pending_actions</span>
                <p class="text-sm font-semibold">
                    Yêu cầu {{ mb_strtolower($pendingReview->action->label()) }} đang chờ admin duyệt.
                    @if (! $isReviewer && $pendingReview->action !== \Modules\QuestionBank\Enums\QuestionReviewAction::Create)
                        Bạn chưa thể gửi thêm thay đổi cho đến khi yêu cầu này được xử lý.
                    @endif
                </p>
            </div>
            @if ($isReviewer)
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.reviews.show'))
<a href="{{ route('admin.questions.reviews.show', $pendingReview) }}"
                    class="inline-flex whitespace-nowrap items-center gap-1 rounded-xl bg-amber-800 px-3 py-2 text-sm font-bold text-white hover:bg-amber-900">
                    Xem và duyệt
                </a>
@endif
            @endif
        </div>
    @endif

    @if (! $isNew)
        @include('admin::questions.partials.review-feedback', [
            'question' => $question,
            'canEditContent' => $canEditContent,
        ])
        @include('admin::questions.partials.review-timeline', [
            'question' => $question,
            'reviewTimeline' => $reviewTimeline ?? null,
            'canAdjudicateQa' => $canAdjudicateQa ?? false,
        ])
    @endif

    @if (! $isNew && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview)
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-sm text-amber-900">
            <div class="flex flex-wrap items-center gap-3">
                <p>
                    Giảng viên được gán đang duyệt chuyên môn. Một phiếu từ chối là fail ngay, chưa sang reviewer.
                    @if (! $isReviewer)
                        Bạn vẫn được sửa; chọn <strong>Lưu &amp; gửi duyệt lại</strong> để reset phiếu giảng viên.
                    @endif
                </p>
            </div>
        </div>
    @endif

    @if (! $isNew && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InFlagReview)
        <div class="mb-5 rounded-2xl border border-orange-200 bg-orange-50/60 px-4 py-3 text-sm text-orange-900">
            <div class="flex flex-wrap items-center gap-3">
                @include('questionbank::partials.instructor-review-flags', ['question' => $question])
                <p>
                    Giảng viên đã duyệt chuyên môn. Câu hỏi đang chờ reviewer gắn cờ.
                    @if (! $isReviewer)
                        Bạn vẫn được sửa; chọn <strong>Lưu &amp; gửi duyệt lại</strong> để reset phiếu GV và 2 cờ.
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- ── MAIN FORM ── --}}
    <form id="admin-question-editor-form" method="post"
          action="{{ $isNew ? route('admin.questions.store') : route('admin.questions.update', $question) }}"
          class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px] lg:items-start"
          x-data='{
              options: @json($optionRows),
              hints: @json($hintRows),
              correct: {{ (int) $correctIndex }},
              add() { this.options.push({ id: null, content: "", is_correct: false, explanation: "" }); },
              remove(i) {
                  if (this.options.length <= 2) return;
                  this.options.splice(i, 1);
                  if (this.correct >= this.options.length) this.correct = 0;
              },
              addHint() { this.hints.push({ id: null, content: "" }); },
              removeHint(i) {
                  if (this.hints.length <= 1) {
                      this.hints = [{ id: null, content: "" }];
                      return;
                  }
                  this.hints.splice(i, 1);
              }
          }'>
        @csrf
        @unless ($isNew) @method('PUT') @endunless
        <input type="hidden" name="requested_status" id="question_requested_status" value="">

            {{-- ── LEFT: Main content ── --}}
            <div @class([
                'space-y-5',
                'pointer-events-none select-none opacity-70' => ! $canEditContent,
            ])>

                {{-- Đề bài --}}
                <div class="rounded-2xl border border-outline-variant bg-surface p-5">
                    <h2 class="mb-4 font-label-lg font-semibold text-on-surface">Đề bài</h2>
                    <x-admin.rich-editor name="stem" label="Nội dung câu hỏi *"
                        :value="old('stem', $question->stem)" required
                        placeholder="Nhập nội dung câu hỏi hoặc ca lâm sàng..." />
                </div>

                {{-- Đáp án --}}
                <div class="rounded-2xl border border-outline-variant bg-surface p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-label-lg font-semibold text-on-surface">Đáp án</h2>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                Chữ A/B/C chỉ là thứ tự trên form. Khi học viên làm bài, thứ tự đáp án sẽ được đảo;
                                hệ thống chấm theo nội dung (id), không theo chữ cái. Không viết “đáp án A” trong stem/giải thích — mô tả theo nội dung lựa chọn.
                            </p>
                        </div>
                        <button type="button" @click="add()"
                                class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-outline-variant px-3 py-1.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                            <span class="material-symbols-outlined text-[16px]">add</span>Thêm
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(opt, index) in options" :key="index">
                            <div class="group relative rounded-xl border border-outline-variant bg-surface-container-lowest p-4 transition-colors"
                                 :class="correct === index ? 'border-primary/40 bg-primary/5' : ''">

                                {{-- Label row --}}
                                <div class="mb-3 flex items-center justify-between">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-on-surface">
                                        <input type="radio" name="correct_option" :value="index"
                                               x-model.number="correct"
                                               class="text-primary focus:ring-primary">
                                        <span class="inline-flex size-6 items-center justify-center rounded-md text-xs font-bold transition-colors"
                                              :class="correct === index ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant'"
                                              x-text="String.fromCharCode(65 + index)"></span>
                                        <span x-show="correct === index" class="text-primary">Đáp án đúng</span>
                                        <span x-show="correct !== index" class="text-on-surface-variant">Đánh dấu đúng</span>
                                    </label>
                                    <button type="button" @click="remove(index)" x-show="options.length > 2"
                                            class="text-sm font-medium text-error opacity-0 transition-opacity group-hover:opacity-100 hover:underline">
                                        Xóa
                                    </button>
                                </div>

                                <input type="hidden" :name="'options['+index+'][id]'" :value="opt.id || ''">
                                <textarea :name="'options['+index+'][content]'" x-model="opt.content"
                                          rows="2" required
                                          class="w-full resize-none rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                          :placeholder="'Nội dung đáp án ' + String.fromCharCode(65 + index)"></textarea>
                                {{-- Mini rich-editor for option explanation (supports images) --}}
                                <div class="admin-rich-editor mini mt-2 overflow-hidden rounded-lg border border-outline-variant bg-surface"
                                     x-init="
                                         (function(currentOpt) {
                                             const container = $el.querySelector('[data-mini-editor]');
                                             const uploadUrl = '{{ route('admin.editor.images') }}';
                                             const q = new window.Quill(container, {
                                                 theme: 'snow',
                                                 modules: { toolbar: [['bold', 'italic'], ['link', 'image'], ['clean']] },
                                                 placeholder: 'Giải thích cho lựa chọn này (không bắt buộc)...'
                                             });
                                             if (typeof window.pinQuillToolbarButtons === 'function') {
                                                 window.pinQuillToolbarButtons(q);
                                             }
                                             if (currentOpt.explanation) {
                                                 const paste = q.clipboard.convert({ html: currentOpt.explanation, text: '' });
                                                 q.setContents(paste, 'silent');
                                             }
                                             q.on('text-change', function() {
                                                 const html = q.root.innerHTML.trim();
                                                 currentOpt.explanation = (html === '<p><br></p>') ? '' : html;
                                             });
                                             // Vietnamese IME fix
                                             const ed = q.root;
                                             ed.addEventListener('compositionstart', function() { ed.classList.remove('ql-blank'); });
                                             ed.addEventListener('compositionend', function() { ed.classList.toggle('ql-blank', q.getLength() <= 1); });
                                             // Image upload handler
                                             q.getModule('toolbar').addHandler('image', function() {
                                                 const inp = document.createElement('input');
                                                 inp.type = 'file';
                                                 inp.accept = 'image/png,image/jpeg,image/gif,image/webp';
                                                 inp.click();
                                                 inp.onchange = async function() {
                                                     const file = inp.files?.[0];
                                                     if (!file) return;
                                                     const body = new FormData();
                                                     body.append('image', file);
                                                     const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                                                     try {
                                                         const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: body, credentials: 'same-origin' });
                                                         const data = await res.json();
                                                         const range = q.getSelection(true) || { index: q.getLength(), length: 0 };
                                                         q.insertEmbed(range.index, 'image', data.url, 'user');
                                                         q.setSelection(range.index + 1, 0, 'silent');
                                                     } catch(e) { alert('Không tải được ảnh. Vui lòng thử lại.'); }
                                                 };
                                             });
                                         })(opt);
                                     ">
                                    <div data-mini-editor class="min-h-[64px] font-body-sm text-on-surface"></div>
                                </div>
                                {{-- Hidden field carries the HTML value on submit --}}
                                <input type="hidden" :name="'options['+index+'][explanation]'" x-model="opt.explanation">
                                <input type="hidden" :name="'options['+index+'][is_correct]'" :value="correct === index ? 1 : 0">
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Hints --}}
                <div class="rounded-2xl border border-outline-variant bg-surface p-5">
                    <h2 class="mb-4 font-label-lg font-semibold text-on-surface">Gợi ý</h2>
                    <div class="space-y-4">
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="text-sm font-semibold text-on-surface">Gợi ý (theo thứ tự)</label>
                                <button type="button" @click="addHint()"
                                        class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-2.5 py-1 text-xs font-semibold text-on-surface hover:bg-surface-container-low">
                                    <span class="material-symbols-outlined text-[14px]">add</span>Thêm gợi ý
                                </button>
                            </div>
                            <p class="mb-3 text-[11px] leading-4 text-on-surface-variant">
                                Gợi ý hiển thị lần lượt — không hiện gợi ý 2 trước gợi ý 1. Không lấy từ khái niệm.
                            </p>
                             <div class="space-y-2">
                                <template x-for="(hint, index) in hints" :key="'hint-'+index">
                                    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-3 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-on-surface-variant" x-text="'Hint ' + (index + 1)"></span>
                                            <button type="button" @click="removeHint(index)"
                                                    class="text-xs font-medium text-error hover:underline">Xóa</button>
                                        </div>
                                        <input type="hidden" :name="'hints['+index+'][id]'" :value="hint.id || ''">

                                        {{-- Mini rich-editor for hint content (supports formatting & images, matching option explanation) --}}
                                        <div class="admin-rich-editor mini overflow-hidden rounded-lg border border-outline-variant bg-surface"
                                             x-init="
                                                 (function(currentHint) {
                                                     const container = $el.querySelector('[data-mini-hint-editor]');
                                                     const uploadUrl = '{{ route('admin.editor.images') }}';
                                                     const q = new window.Quill(container, {
                                                         theme: 'snow',
                                                         modules: { toolbar: [['bold', 'italic'], ['link', 'image'], ['clean']] },
                                                         placeholder: 'Nội dung hint ' + (index + 1) + '...'
                                                     });
                                                     if (typeof window.pinQuillToolbarButtons === 'function') {
                                                         window.pinQuillToolbarButtons(q);
                                                     }
                                                     if (currentHint.content) {
                                                         const paste = q.clipboard.convert({ html: currentHint.content, text: '' });
                                                         q.setContents(paste, 'silent');
                                                     }
                                                     q.on('text-change', function() {
                                                         const html = q.root.innerHTML.trim();
                                                         currentHint.content = (html === '<p><br></p>') ? '' : html;
                                                     });
                                                     const ed = q.root;
                                                     ed.addEventListener('compositionstart', function() { ed.classList.remove('ql-blank'); });
                                                     ed.addEventListener('compositionend', function() { ed.classList.toggle('ql-blank', q.getLength() <= 1); });
                                                     q.getModule('toolbar').addHandler('image', function() {
                                                         const inp = document.createElement('input');
                                                         inp.type = 'file';
                                                         inp.accept = 'image/png,image/jpeg,image/gif,image/webp';
                                                         inp.click();
                                                         inp.onchange = async function() {
                                                             const file = inp.files?.[0];
                                                             if (!file) return;
                                                             const body = new FormData();
                                                             body.append('image', file);
                                                             const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                                                             try {
                                                                 const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: body, credentials: 'same-origin' });
                                                                 const data = await res.json();
                                                                 const range = q.getSelection(true) || { index: q.getLength(), length: 0 };
                                                                 q.insertEmbed(range.index, 'image', data.url, 'user');
                                                                 q.setSelection(range.index + 1, 0, 'silent');
                                                             } catch(e) { alert('Không tải được ảnh. Vui lòng thử lại.'); }
                                                         };
                                                     });
                                                 })(hint);
                                             ">
                                            <div data-mini-hint-editor class="min-h-[64px] font-body-sm text-on-surface"></div>
                                        </div>
                                        <input type="hidden" :name="'hints['+index+'][content]'" :value="hint.content">
                                    </div>
                                </template>
                            </div>
                        </div>

                        <x-admin.rich-editor name="attending_tip" label="Kiến thức / Gợi ý"
                            :value="old('attending_tip', $question->attending_tip)"
                            placeholder="Kiến thức bổ sung..." />
                    </div>
                </div>

            </div>

            {{-- ── RIGHT: Sidebar ── --}}
            <div class="space-y-4">
                {{-- Workflow trước (luôn bấm được); nội dung khóa nằm dưới --}}
                @if (! $isNew && ($canPublish || $canReject) && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::PendingPublish)
                    <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4"
                        x-data="{ returnOpen: false, redOutcome: 'confirmed' }">
                        <h2 class="mb-2 font-label-md font-semibold text-on-surface">Xuất bản</h2>
                        <p class="mb-3 text-xs leading-5 text-on-surface-variant">
                            GV {{ $question->assignedInstructor?->name ?? $question->instructor?->name ?? '—' }} đã duyệt.
                            Xuất bản chỉ tăng phiên bản — không sửa nội dung.
                            <span class="mt-2 block">
                                @include('questionbank::partials.instructor-review-flags', ['question' => $question])
                            </span>
                            @if ($question->hasRedReviewerFlag())
                                <span class="mt-2 block font-semibold text-rose-700">Có ≥1 cờ đỏ — không xuất bản được. Admin phải trả về biên tập.</span>
                            @endif
                            @if ($question->published_version)
                                <span class="mt-2 block">
                                    QBank đang phục vụ phiên bản {{ $question->published_version }}.
                                    <a href="{{ route('admin.questions.compare', $question) }}" class="font-semibold text-primary hover:underline">So sánh</a>
                                </span>
                            @endif
                        </p>
                        <div class="flex flex-col gap-2">
                            @if ($canPublish && ! $question->hasRedReviewerFlag())
                            <button type="submit"
                                form="question-publish-form"
                                onclick="return confirm('Xuất bản câu hỏi này lên ngân hàng? Phiên bản sẽ tăng.')"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90">
                                <span class="material-symbols-outlined text-[18px]">publish</span>
                                Duyệt &amp; xuất bản
                            </button>
                            <button type="submit"
                                form="question-private-form"
                                onclick="return confirm('Ẩn câu này khỏi ngân hàng câu hỏi (private)? Học viên sẽ không thấy câu trong QBank / bài thi mới.')"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-violet-300 py-2.5 font-label-md font-semibold text-violet-800 hover:bg-violet-50">
                                <span class="material-symbols-outlined text-[18px]">lock</span>
                                Ẩn khỏi ngân hàng (private)
                            </button>
                            @endif
                            @if ($canReject)
                            <button type="button"
                                @click="returnOpen = !returnOpen"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-300 py-2.5 font-label-md font-semibold text-rose-700 hover:bg-rose-50 {{ $question->hasRedReviewerFlag() ? 'bg-rose-600 text-white hover:bg-rose-700 border-rose-600' : '' }}">
                                <span class="material-symbols-outlined text-[18px]">close</span>
                                {{ $question->hasRedReviewerFlag() ? 'Trả về biên tập (bắt buộc)' : 'Trả về biên tập' }}
                            </button>

                            <div x-show="returnOpen" x-cloak
                                class="mt-1 space-y-3 rounded-xl border border-rose-200 bg-white p-3">
                                <div>
                                    <label for="reject-return-reason" class="mb-1 block text-xs font-semibold text-on-surface-variant">Lý do trả về</label>
                                    <textarea id="reject-return-reason" x-ref="rejectReason" rows="3" required
                                        class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm"
                                        placeholder="{{ $question->hasRedReviewerFlag() ? 'Tóm tắt lỗi theo ghi chú cờ đỏ…' : 'Vấn đề vận hành / định dạng…' }}"></textarea>
                                </div>
                                @if ($question->hasRedReviewerFlag())
                                    <fieldset class="space-y-2">
                                        <legend class="text-xs font-semibold text-on-surface-variant">Đánh giá cờ đỏ (vòng này)</legend>
                                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-outline-variant px-3 py-2 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50">
                                            <input type="radio" class="mt-1" name="red_flag_outcome_ui" value="confirmed" x-model="redOutcome">
                                            <span class="text-sm">
                                                <span class="font-semibold text-on-surface">Cờ đỏ đúng</span>
                                                <span class="block text-xs text-on-surface-variant">Reviewer gắn đúng · GV approve cùng vòng → duyệt sai.</span>
                                            </span>
                                        </label>
                                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-outline-variant px-3 py-2 has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                                            <input type="radio" class="mt-1" name="red_flag_outcome_ui" value="false_positive" x-model="redOutcome">
                                            <span class="text-sm">
                                                <span class="font-semibold text-on-surface">Cờ đỏ gắn sai</span>
                                                <span class="block text-xs text-on-surface-variant">Reviewer gắn oan · không quy lỗi GV.</span>
                                            </span>
                                        </label>
                                    </fieldset>
                                @endif
                                <button type="button"
                                    class="w-full rounded-xl bg-rose-600 py-2.5 text-sm font-semibold text-white hover:bg-rose-700"
                                    @click="
                                        const reason = ($refs.rejectReason.value || '').trim();
                                        if (!reason) { $refs.rejectReason.focus(); return; }
                                        document.getElementById('question-reject-publish-reason').value = reason;
                                        const outcomeInput = document.getElementById('question-reject-red-flag-outcome');
                                        if (outcomeInput) outcomeInput.value = redOutcome;
                                        document.getElementById('question-reject-publish-form').submit();
                                    ">
                                    Xác nhận trả về
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-950">
                        <p class="font-semibold">Nội dung đã khóa</p>
                        <p class="mt-1 text-xs leading-5">
                            Đang chờ xuất bản — Admin/Super Admin chỉ duyệt trạng thái, không sửa nội dung.
                        </p>
                    </div>
                @elseif (! $isNew && $canPublish && in_array($question->status, [
                    \Modules\QuestionBank\Enums\QuestionStatus::Published,
                    \Modules\QuestionBank\Enums\QuestionStatus::Private,
                ], true))
                    <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4">
                        <h2 class="mb-2 font-label-md font-semibold text-on-surface">Quản lý xuất bản</h2>
                        <p class="mb-3 text-xs leading-5 text-on-surface-variant">
                            Chỉ đổi trạng thái (xuất bản / private / ngừng dùng) hoặc xoá. Không chỉnh sửa nội dung — tránh xung đột với biên tập viên.
                        </p>
                        <div class="flex flex-col gap-2">
                            @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Published)
                                <button type="submit"
                                    form="question-private-form"
                                    onclick="return confirm('Ẩn câu đã xuất bản khỏi ngân hàng câu hỏi (private)?')"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-violet-300 py-2.5 font-label-md font-semibold text-violet-800 hover:bg-violet-50">
                                    <span class="material-symbols-outlined text-[18px]">lock</span>
                                    Ẩn khỏi ngân hàng (private)
                                </button>
                            @else
                                <button type="submit"
                                    form="question-publish-form"
                                    onclick="return confirm('Đưa câu private trở lại ngân hàng công khai?')"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90">
                                    <span class="material-symbols-outlined text-[18px]">publish</span>
                                    Xuất bản công khai
                                </button>
                            @endif
                            <button type="submit"
                                form="question-retire-form"
                                onclick="return confirm('Ngừng dùng câu hỏi này?')"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-300 py-2.5 font-label-md font-semibold text-rose-700 hover:bg-rose-50">
                                <span class="material-symbols-outlined text-[18px]">block</span>
                                Ngừng dùng
                            </button>
                        </div>
                    </div>
                @elseif (! $isNew && $canPublish && ! $canEditContent)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-900">
                        <p class="font-semibold">Chỉ xem nội dung</p>
                        <p class="mt-1 text-xs leading-5">
                            Admin/Super Admin không sửa nội dung câu hỏi.
                            @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview)
                                Đang chờ giảng viên duyệt chuyên môn — không xuất bản trước bước này.
                            @elseif ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::InFlagReview)
                                Đang chờ reviewer gắn cờ — không xuất bản trước bước này.
                            @elseif ($isRejected)
                                Câu hỏi đã bị từ chối. Đang chờ biên tập viên xử lý.
                            @elseif ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Draft)
                                Bản nháp do biên tập viên soạn. Chỉ Content Editor được chỉnh sửa và gửi duyệt.
                            @endif
                            @if ($question->published_version)
                                Ngân hàng vẫn phục vụ phiên bản {{ $question->published_version }}.
                                <a href="{{ route('admin.questions.compare', $question) }}" class="font-semibold text-primary hover:underline">So sánh</a>
                            @endif
                        </p>
                    </div>
                @elseif (! $isNew && $question->published_version && ! $canEditContent)
                    <div class="rounded-2xl border border-sky-200 bg-sky-50/70 p-4 text-sm text-sky-950">
                        <p class="font-semibold">QBank đang phục vụ phiên bản {{ $question->published_version }}</p>
                        <p class="mt-1 text-xs leading-5">
                            Working copy: {{ $question->status->label() }}.
                            <a href="{{ route('admin.questions.compare', $question) }}" class="font-semibold text-primary hover:underline">So sánh</a>
                        </p>
                    </div>
                @endif

                @if (! $isNew)
                    @include('admin::questions.partials.similarity-panel')
                @endif

                <div @class([
                    'space-y-4',
                    'pointer-events-none select-none opacity-70' => ! $canEditContent,
                ])>
                <div class="rounded-2xl border border-outline-variant bg-surface p-4"
                    x-data="questionImageUploader(@js($stemImagePath), @js($stemImageUrl), @js(route('admin.editor.images')), @js(csrf_token()))">
                    <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Ảnh câu hỏi</h2>
                    <input type="hidden" name="stem_image_path" x-ref="pathInput" :value="imagePath">
                    <input type="file" x-ref="fileInput" class="hidden" accept="image/png,image/jpeg,image/gif,image/webp" @change="upload($event)">

                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                         tabindex="0"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="isDragging = false; handleDrop($event)"
                         @paste="handlePaste($event)"
                         :class="isDragging ? 'border-primary ring-2 ring-primary/20 bg-primary/5' : ''">
                        <div x-show="imageUrl" class="bg-white flex justify-center">
                            <img :src="imageUrl" alt="Ảnh minh họa câu hỏi" class="w-full h-auto max-h-[600px] object-contain">
                        </div>
                        <div x-show="!imageUrl" class="flex flex-col aspect-[4/3] items-center justify-center px-4 text-center text-sm text-on-surface-variant">
                            <span class="material-symbols-outlined mb-2 text-[32px] text-on-surface-variant/50">image</span>
                            Kéo thả ảnh vào đây, nhấn Ctrl+V<br>hoặc bấm nút tải ảnh bên dưới
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="chooseFile()" :disabled="uploading"
                            class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-on-primary disabled:opacity-60">
                            <span class="material-symbols-outlined text-[18px]">upload</span>
                            <span x-text="uploading ? 'Đang tải...' : 'Tải ảnh'"></span>
                        </button>
                        <button type="button" @click="remove()" :disabled="!imagePath || uploading"
                            class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-2 text-sm font-semibold text-on-surface-variant disabled:opacity-40">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                            Xóa ảnh
                        </button>
                    </div>
                    <p class="mt-2 text-[11px] leading-4 text-on-surface-variant">
                        Khuyến nghị: ảnh ngang 4:3 hoặc 1:1, dung lượng tối đa 5MB.
                    </p>
                    <p x-show="error" x-cloak class="mt-2 text-xs font-medium text-error" x-text="error"></p>
                </div>

                {{-- Gửi duyệt + chọn GV — CTA rõ ràng, không dùng dropdown trạng thái --}}
                @include('admin::questions.partials.editor-submit-panel')

                @if (! $isNew && $canEditContent && $question->published_version && $question->status !== \Modules\QuestionBank\Enums\QuestionStatus::Published)
                    <div class="rounded-2xl border border-sky-200 bg-sky-50/70 p-4 text-sm text-sky-950">
                        <p class="font-semibold">QBank đang phục vụ phiên bản {{ $question->published_version }}</p>
                        <p class="mt-1 text-xs leading-5">
                            Working copy: {{ $question->status->label() }}. Nội dung mới chỉ lên ngân hàng sau khi GV duyệt và admin xuất bản.
                            <a href="{{ route('admin.questions.compare', $question) }}" class="mt-1 block font-semibold text-primary hover:underline">So sánh</a>
                        </p>
                    </div>
                @endif

                {{-- Phân loại --}}
                <div class="rounded-2xl border border-outline-variant bg-surface p-4">
                    <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Phân loại</h2>
                    <div class="space-y-3">
                        @include('admin::questions.partials.taxonomy-fields')

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="difficulty">Độ khó *</label>
                            <select id="difficulty" name="difficulty" required
                                    class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                @foreach ($difficulties as $d)
                                    <option value="{{ $d->value }}" @selected(old('difficulty', $question->difficulty?->value) === $d->value)>{{ $d->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <p class="mb-1.5 text-xs font-semibold text-on-surface-variant">Truy cập</p>
                            <label class="flex min-h-11 cursor-pointer items-start gap-2 rounded-xl border border-outline-variant px-3 py-2.5 transition-colors hover:bg-surface-container-low has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="checkbox" name="is_free" value="1"
                                       @checked(old('is_free', $question->is_free))
                                       class="mt-0.5 size-4 rounded text-primary focus:ring-primary">
                                <span>
                                    <span class="block text-sm font-semibold text-on-surface">Miễn phí</span>
                                    <span class="mt-0.5 block text-[11px] leading-4 text-on-surface-variant">
                                        Tick nếu tài khoản miễn phí được làm câu này. Không tick → chỉ Premium; tài khoản Premium vẫn thấy mọi câu.
                                    </span>
                                </span>
                            </label>
                        </div>
                        <label class="flex min-h-11 cursor-pointer items-start gap-2 rounded-xl border border-outline-variant px-3 py-2.5 transition-colors hover:bg-surface-container-low has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="checkbox" name="is_priority" value="1"
                                   @checked(old('is_priority', $question->is_priority))
                                   class="mt-0.5 size-4 rounded text-primary focus:ring-primary">
                            <span>
                                <span class="block text-sm font-semibold text-on-surface">Câu ưu tiên</span>
                                <span class="mt-0.5 block text-[11px] leading-4 text-on-surface-variant">
                                    Câu hỏi từ kỳ thi quan trọng gần đây — dùng khi chọn nội dung livestream chữa đề.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Thông tin --}}
                @if (! $isNew)
                    <div class="rounded-2xl border border-outline-variant bg-surface p-4">
                        <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Thông tin</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">Người tạo</dt>
                                <dd class="text-right font-semibold text-on-surface">{{ $question->creator?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">Giảng viên được gán</dt>
                                <dd class="text-right font-semibold text-on-surface">{{ $question->assignedInstructor?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">Người xuất bản</dt>
                                <dd class="text-right font-semibold text-on-surface">{{ $question->publisher?->name ?? $question->reviewer?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">Phiên bản QBank</dt>
                                <dd class="text-right font-semibold text-on-surface">
                                    @if ((int) $question->published_version > 0)
                                        v{{ $question->published_version }}
                                    @else
                                        Chưa xuất bản
                                    @endif
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">Xuất bản lúc</dt>
                                <dd class="text-right font-semibold text-on-surface">
                                    @if (! empty($publishedVersionAt))
                                        {{ \Illuminate\Support\Carbon::parse($publishedVersionAt)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">Tạo lúc</dt>
                                <dd class="text-right font-semibold text-on-surface">{{ $question->created_at?->format('d/m/Y H:i') }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">Cập nhật</dt>
                                <dd class="text-right font-semibold text-on-surface">{{ $question->updated_at?->diffForHumans() }}</dd>
                            </div>
                        </dl>
                        @if ($isRejected && filled($rejectionReason))
                            <div class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                <p class="font-semibold">{{ $isInstructorRejection ? 'Góp ý của giảng viên' : 'Lý do trả về' }}</p>
                                <p class="mt-1">{{ $rejectionReason }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                </div>{{-- /locked content --}}
            </div>{{-- /sidebar --}}
    </form>

    @if (! $isNew && $canEditContent && in_array($question->status, [
        \Modules\QuestionBank\Enums\QuestionStatus::InReview,
        \Modules\QuestionBank\Enums\QuestionStatus::Rejected,
    ], true))
        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.transition'))
<form id="editor-return-draft-form" method="post" action="{{ route('admin.questions.transition', $question) }}" class="hidden">
            @csrf
            <input type="hidden" name="status" value="{{ \Modules\QuestionBank\Enums\QuestionStatus::Draft->value }}">
        </form>
@endif
    @endif

    @if (! $isNew && ($canPublish || $canReject) && in_array($question->status, [
        \Modules\QuestionBank\Enums\QuestionStatus::PendingPublish,
        \Modules\QuestionBank\Enums\QuestionStatus::Published,
        \Modules\QuestionBank\Enums\QuestionStatus::Private,
    ], true))
        @if ($canPublish)
        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.transition'))
<form id="question-publish-form" method="post" action="{{ route('admin.questions.transition', $question) }}" class="hidden">
            @csrf
            <input type="hidden" name="status" value="{{ \Modules\QuestionBank\Enums\QuestionStatus::Published->value }}">
        </form>
@endif
        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.transition'))
<form id="question-private-form" method="post" action="{{ route('admin.questions.transition', $question) }}" class="hidden">
            @csrf
            <input type="hidden" name="status" value="{{ \Modules\QuestionBank\Enums\QuestionStatus::Private->value }}">
        </form>
@endif
        @endif
        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.transition'))
<form id="question-retire-form" method="post" action="{{ route('admin.questions.transition', $question) }}" class="hidden">
            @csrf
            <input type="hidden" name="status" value="{{ \Modules\QuestionBank\Enums\QuestionStatus::Retired->value }}">
        </form>
@endif
        @if ($canReject)
        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.transition'))
<form id="question-reject-publish-form" method="post" action="{{ route('admin.questions.transition', $question) }}" class="hidden">
            @csrf
            <input type="hidden" name="status" value="{{ \Modules\QuestionBank\Enums\QuestionStatus::Rejected->value }}">
            <input type="hidden" name="rejection_reason" id="question-reject-publish-reason" value="">
            <input type="hidden" name="red_flag_outcome" id="question-reject-red-flag-outcome" value="confirmed">
        </form>
@endif
        @endif
    @endif

</x-layouts.admin>
