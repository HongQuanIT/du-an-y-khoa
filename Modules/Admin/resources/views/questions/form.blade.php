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

    $isRejectedForEditor = ! $isNew && ! $isReviewer && ! $pendingReview && $latestRejectedReview;
    $statusBadge = $isRejectedForEditor
        ? ['label' => 'Bị từ chối', 'class' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300']
        : (! $isNew ? match($question->status) {
        \Modules\QuestionBank\Enums\QuestionStatus::Published => ['label' => 'Đã xuất bản', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::InReview  => ['label' => 'Chờ duyệt',   'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::Rejected  => ['label' => 'Từ chối',     'class' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::Private   => ['label' => 'Riêng tư',    'class' => 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::Retired   => ['label' => 'Ngừng dùng',  'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'],
        default                                               => ['label' => 'Bản nháp',    'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
    } : null);
    $stemImagePath = old('stem_image_path', $question->stem_image_path);
    $stemImageUrl = filled($stemImagePath)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($stemImagePath)
        : null;
@endphp

<x-layouts.admin :title="$isNew ? 'Tạo câu hỏi mới' : 'Chỉnh sửa câu hỏi'">

    {{-- ── HEADER ── --}}
    <header class="mb-6 flex flex-col gap-4 border-b border-outline-variant pb-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <a href="{{ route('admin.questions.index') }}"
               aria-label="Quay lại danh sách câu hỏi"
               class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
            </a>
            <div class="min-w-0">
                <h1 class="font-headline-md text-headline-md font-bold text-on-surface">
                    {{ $isNew ? 'Tạo câu hỏi mới' : 'Chỉnh sửa câu hỏi' }}
                </h1>
                @if (! $isNew)
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 font-body-sm text-on-surface-variant" aria-label="Thông tin câu hỏi">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge['class'] }}">
                            {{ $statusBadge['label'] }}
                        </span>
                        @unless ($isRejectedForEditor)
                            <span>·</span>
                            <a href="{{ route('admin.questions.versions.index', $question) }}"
                                class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                                title="Xem lịch sử phiên bản">
                                {{ $question->version > 0 ? 'Phiên bản ' . $question->version : 'Chưa có phiên bản (v0)' }}
                                <span class="material-symbols-outlined text-[15px]">history</span>
                            </a>
                        @endunless
                        @if ($canViewAudit)
                            <span>·</span>
                            <a href="{{ route('admin.audit.index', ['subject_type' => 'question', 'subject_id' => $question->id]) }}"
                                class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                                title="Xem nhật ký audit">
                                Nhật ký
                                <span class="material-symbols-outlined text-[15px]">policy</span>
                            </a>
                        @endif
                        <span>·</span>
                        <a href="{{ route('admin.questions.stats', $question) }}"
                            class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                            title="Thống kê chi tiết">
                            Thống kê
                            <span class="material-symbols-outlined text-[15px]">analytics</span>
                        </a>
                        <span>·</span>
                        <span>Cập nhật {{ $question->updated_at?->diffForHumans() }}</span>
                    </div>
                @else
                    <p class="mt-0.5 font-body-sm text-on-surface-variant">Soạn thảo câu hỏi và lưu bản nháp.</p>
                @endif
            </div>
        </div>

        @if (! $isNew && $canDelete && ! $pendingReview)
            <div class="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end lg:pt-0.5">
                <form method="post" action="{{ route('admin.questions.destroy', $question) }}" aria-label="Xóa câu hỏi">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('{{ $isReviewer ? 'Xóa câu hỏi này?' : 'Gửi yêu cầu xóa câu hỏi này để admin duyệt?' }}')"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                        {{ $isReviewer ? 'Xóa' : 'Yêu cầu xóa' }}
                    </button>
                </form>
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
                <a href="{{ route('admin.questions.reviews.show', $pendingReview) }}"
                    class="inline-flex whitespace-nowrap items-center gap-1 rounded-xl bg-amber-800 px-3 py-2 text-sm font-bold text-white hover:bg-amber-900">
                    Xem và duyệt
                </a>
            @endif
        </div>
    @endif

    @if ($isRejectedForEditor)
        <section aria-labelledby="rejection-status-title" class="mb-5 rounded-2xl border border-red-300 bg-red-50 px-4 py-4 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined mt-0.5" aria-hidden="true">cancel</span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <h2 id="rejection-status-title" class="font-semibold">Câu này bị từ chối bởi admin</h2>
                        <span class="rounded-full bg-red-200 px-2 py-0.5 text-xs font-bold text-red-800 dark:bg-red-900/70 dark:text-red-100">
                            Bị từ chối
                        </span>
                    </div>
                    <p class="mt-2 text-sm"><span class="font-semibold">Lý do từ chối:</span> {{ $latestRejectedReview->review_note ?: 'Admin chưa để lại ghi chú.' }}</p>
                    <p class="mt-2 text-xs text-red-700 dark:text-red-200">
                        Từ chối bởi {{ $latestRejectedReview->reviewer?->name ?? 'Admin' }}
                        @if ($latestRejectedReview->reviewed_at)
                            · {{ $latestRejectedReview->reviewed_at->format('d/m/Y H:i') }}
                        @endif
                    </p>
                    <p class="mt-3 text-sm font-medium">Bạn có thể chỉnh sửa câu hỏi bên dưới và lưu để gửi lại duyệt.</p>
                </div>
            </div>
        </section>
    @endif

    @if (! $isNew && ! $isReviewer && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview)
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-sm text-amber-900">
            Câu hỏi đang chờ admin duyệt. Bạn vẫn có thể chỉnh sửa nội dung và bấm <strong>Lưu lại</strong> bất kỳ lúc nào.
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

        <fieldset @disabled(! $canUpdate) class="contents">

            {{-- ── LEFT: Main content ── --}}
            <div class="space-y-5">

                {{-- Đề bài --}}
                <div class="rounded-2xl border border-outline-variant bg-surface p-5">
                    <h2 class="mb-4 font-label-lg font-semibold text-on-surface">Đề bài</h2>
                    <x-admin.rich-editor name="stem" label="Nội dung câu hỏi *"
                        :value="old('stem', $question->stem)" required
                        placeholder="Nhập nội dung câu hỏi hoặc ca lâm sàng..." />
                </div>

                {{-- Đáp án --}}
                <div class="rounded-2xl border border-outline-variant bg-surface p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-label-lg font-semibold text-on-surface">Đáp án</h2>
                        <button type="button" @click="add()"
                                class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-1.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
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
                                <input type="hidden" :name="'options['+index+'][explanation]'" :value="opt.explanation">
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

                {{-- Hành động --}}
                @if ($canUpdate)
                    <div class="rounded-2xl border border-outline-variant bg-surface p-4">
                        <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Thao tác</h2>
                        @php
                            $availableStatuses = $isNew
                                ? [
                                    \Modules\QuestionBank\Enums\QuestionStatus::Draft->value => 'Lưu nháp',
                                    \Modules\QuestionBank\Enums\QuestionStatus::InReview->value => 'Gửi duyệt',
                                    ...($isReviewer ? [
                                        \Modules\QuestionBank\Enums\QuestionStatus::Published->value => 'Xuất bản',
                                        \Modules\QuestionBank\Enums\QuestionStatus::Private->value => 'Riêng tư (exam)',
                                    ] : []),
                                ]
                                : [
                                    $question->status->value => $question->status->label(),
                                    ...collect($workflowStatuses)->mapWithKeys(fn ($s) => [
                                        $s->value => match ($s) {
                                            \Modules\QuestionBank\Enums\QuestionStatus::InReview => 'Gửi duyệt',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Published => 'Xuất bản',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Rejected => 'Từ chối',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Draft => 'Chuyển về nháp',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Private => 'Chuyển sang riêng tư',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Retired => 'Ngừng dùng',
                                        }
                                    ])->all(),
                                ];

                            // Cho phép editor đang ở InReview có thể chọn về Draft
                            if (! $isReviewer && ! $isNew && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview) {
                                $availableStatuses[\Modules\QuestionBank\Enums\QuestionStatus::Draft->value] = 'Chuyển về nháp';
                            }
                            // Editor đang ở Draft hoặc vừa tạo có thể chọn Lưu nháp hoặc Gửi duyệt
                            if (! $isReviewer && ! $isNew && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Draft) {
                                $availableStatuses[\Modules\QuestionBank\Enums\QuestionStatus::Draft->value] = 'Lưu nháp';
                                $availableStatuses[\Modules\QuestionBank\Enums\QuestionStatus::InReview->value] = 'Gửi duyệt';
                            }

                            $defaultSelected = $isNew ? 'draft' : $question->status->value;
                        @endphp

                        <div class="mb-3"
                             x-data="{
                                 selectedStatus: @js($defaultSelected),
                                 currentStatus: @js($isNew ? '' : $question->status->value),
                                 isNew: @js($isNew),
                                 isReviewer: @js($isReviewer),
                                 syncStatus() {
                                     const input = document.getElementById('question_requested_status');
                                     if (input) {
                                         input.value = this.selectedStatus;
                                     }
                                 },
                                 handleSubmit(e) {
                                     this.syncStatus();

                                     // Nếu editor đang ở in_review mà chọn chuyển về draft, dùng form transition về draft
                                     if (! this.isReviewer && ! this.isNew && this.currentStatus === 'in_review' && this.selectedStatus === 'draft') {
                                         e.preventDefault();
                                         document.getElementById('editor-return-draft-form')?.submit();
                                         return;
                                     }

                                     if (this.isReviewer && ! this.isNew && this.selectedStatus === 'rejected') {
                                         const reason = window.prompt('Nhập lý do từ chối câu hỏi:');
                                         if (! reason || ! reason.trim()) {
                                             e.preventDefault();
                                             this.selectedStatus = this.currentStatus;
                                             this.syncStatus();
                                             return;
                                         }
                                         let reasonInput = document.getElementById('question_rejection_reason');
                                         if (! reasonInput) {
                                             reasonInput = document.createElement('input');
                                             reasonInput.type = 'hidden';
                                             reasonInput.name = 'rejection_reason';
                                             reasonInput.id = 'question_rejection_reason';
                                             document.getElementById('admin-question-editor-form').appendChild(reasonInput);
                                         }
                                         reasonInput.value = reason.trim();
                                     }
                                 }
                             }"
                             x-init="syncStatus()">
                            <label class="mb-1.5 block text-xs font-semibold text-on-surface-variant" for="admin_sidebar_status_select">
                                Trạng thái:
                            </label>
                            <select id="admin_sidebar_status_select"
                                    x-model="selectedStatus"
                                    @change="syncStatus()"
                                    aria-label="Chọn trạng thái câu hỏi"
                                    class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm font-semibold text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                @foreach ($availableStatuses as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <button type="submit"
                                    @click="handleSubmit($event)"
                                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 font-label-md font-semibold text-on-primary transition-colors hover:bg-primary/90">
                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                <span>{{ $isNew ? 'Lưu lại' : 'Lưu thay đổi' }}</span>
                            </button>
                        </div>

                        <a href="{{ route('admin.questions.index') }}"
                           class="mt-2 flex w-full items-center justify-center rounded-xl py-2 text-xs font-semibold text-on-surface-variant transition-colors hover:text-on-surface">
                            Hủy bỏ
                        </a>
                    </div>
                @endif
                @if (! $isNew && ! $isReviewer && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview)
                    <form id="editor-return-draft-form" method="post" action="{{ route('admin.questions.transition', $question) }}" class="hidden">
                        @csrf
                        <input type="hidden" name="status" value="draft">
                    </form>
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
                            <label class="flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-outline-variant px-3 transition-colors hover:bg-surface-container-low">
                                <input type="checkbox" name="is_free" value="1"
                                       @checked(old('is_free', $question->is_free))
                                       class="size-4 rounded text-primary focus:ring-primary">
                                <span class="text-sm font-semibold text-on-surface">Miễn phí (không cần Premium)</span>
                            </label>
                        </div>
                        @if ($canPublish)
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-outline-variant px-3 py-2 transition-colors hover:bg-surface-container-low">
                                <input type="checkbox" name="exam_flag" value="1"
                                       @checked(old('exam_flag', $question->exam_flag))
                                       class="size-4 rounded text-primary focus:ring-primary">
                                <span class="text-sm font-semibold text-on-surface">Câu dành cho kho đề thi</span>
                            </label>
                        @endif
                    </div>
                </div>

                {{-- Thông tin --}}
                @if (! $isNew)
                    <div class="rounded-2xl border border-outline-variant bg-surface p-4">
                        <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Thông tin</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Người tạo</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->creator?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Người duyệt</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->reviewer?->name ?? '—' }}</dd>
                            </div>
                            @unless ($isRejectedForEditor)
                                <div class="flex justify-between">
                                    <dt class="text-on-surface-variant">Phiên bản</dt>
                                    <dd class="font-semibold text-on-surface">{{ $question->version > 0 ? $question->version : '0 (Chưa có)' }}</dd>
                                </div>
                            @endunless
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Tạo lúc</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->created_at?->format('d/m/Y H:i') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Cập nhật</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->updated_at?->diffForHumans() }}</dd>
                            </div>
                        </dl>
                        @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected && filled($question->rejection_reason))
                            <div class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                <p class="font-semibold">Lý do từ chối</p>
                                <p class="mt-1">{{ $question->rejection_reason }}</p>
                            </div>
                        @endif
                    </div>
                @endif

            </div>
        </fieldset>
    </form>

</x-layouts.admin>
