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

    $statusBadge = ! $isNew ? match($question->status) {
        \Modules\QuestionBank\Enums\QuestionStatus::Published => ['label' => 'Đã xuất bản', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::InReview  => ['label' => 'Chờ duyệt',   'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::Rejected  => ['label' => 'Từ chối',     'class' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::Private   => ['label' => 'Riêng tư',    'class' => 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::Retired   => ['label' => 'Ngừng dùng',  'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'],
        default                                               => ['label' => 'Bản nháp',    'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
    } : null;
    $stemImagePath = old('stem_image_path', $question->stem_image_path);
    $stemImageUrl = filled($stemImagePath)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($stemImagePath)
        : null;
@endphp

<x-layouts.admin :title="$isNew ? 'Tạo câu hỏi mới' : 'Chỉnh sửa câu hỏi'">

    {{-- ── HEADER ── --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.questions.index') }}"
               class="flex size-9 items-center justify-center rounded-xl border border-outline-variant text-on-surface-variant transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h1 class="font-headline-sm font-bold text-on-surface">
                    {{ $isNew ? 'Tạo câu hỏi mới' : 'Chỉnh sửa câu hỏi' }}
                </h1>
                @if (! $isNew)
                    <p class="mt-0.5 flex items-center gap-2 font-body-sm text-on-surface-variant">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold {{ $statusBadge['class'] }}">
                            {{ $statusBadge['label'] }}
                        </span>
                        <span>·</span>
                        <a href="{{ route('admin.questions.versions.index', $question) }}"
                            class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                            title="Xem lịch sử phiên bản">
                            Phiên bản {{ $question->version }}
                            <span class="material-symbols-outlined text-[15px]">history</span>
                        </a>
                        @if ($canViewAudit)
                            <span>·</span>
                            <a href="{{ route('admin.audit.index', ['subject_type' => 'question', 'subject_id' => $question->id]) }}"
                                class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline"
                                title="Xem nhật ký audit">
                                Audit
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
                    </p>
                @else
                    <p class="mt-0.5 font-body-sm text-on-surface-variant">Soạn thảo câu hỏi và lưu bản nháp.</p>
                @endif
            </div>
        </div>

        {{-- Workflow status (edit only) --}}
        @if (! $isNew)
            <div class="flex flex-wrap items-center gap-2">
                @if ($workflowStatuses !== [])
                    <form method="post" action="{{ route('admin.questions.transition', $question) }}"
                          x-data="{
                              current: @js($question->status->value),
                              changeStatus(event) {
                                  const next = event.target.value;
                                  if (next === this.current) return;

                                  if (next === 'rejected') {
                                      const reason = window.prompt('Nhập lý do từ chối câu hỏi:');
                                      if (! reason || ! reason.trim()) {
                                          event.target.value = this.current;
                                          return;
                                      }
                                      event.target.form.elements.rejection_reason.value = reason.trim();
                                  }

                                  event.target.disabled = true;
                                  event.target.form.submit();
                              }
                          }">
                        @csrf
                        <input type="hidden" name="rejection_reason" value="">
                        <label class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-3 py-1.5 text-sm text-on-surface shadow-sm">
                            <span class="font-semibold text-on-surface-variant">Trạng thái:</span>
                            <select name="status" aria-label="Trạng thái câu hỏi" @change="changeStatus($event)"
                                    class="min-w-36 border-0 bg-transparent py-0 pr-8 font-semibold text-on-surface focus:ring-0">
                                <option value="{{ $question->status->value }}">{{ $question->status->label() }}</option>
                                @foreach ($workflowStatuses as $workflowStatus)
                                    <option value="{{ $workflowStatus->value }}">
                                        {{ match ($workflowStatus) {
                                            \Modules\QuestionBank\Enums\QuestionStatus::InReview => 'Gửi duyệt',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Published => 'Xuất bản',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Rejected => 'Từ chối',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Draft => 'Chuyển về nháp',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Private => 'Chuyển sang riêng tư',
                                            \Modules\QuestionBank\Enums\QuestionStatus::Retired => 'Ngừng dùng',
                                        } }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                @endif
                @if ($canDelete && ! $pendingReview)
                    <form method="post" action="{{ route('admin.questions.destroy', $question) }}">
                        @csrf @method('DELETE')
                        <button onclick="return confirm('{{ $isReviewer ? 'Xóa câu hỏi này?' : 'Gửi yêu cầu xóa câu hỏi này để admin duyệt?' }}')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                            {{ $isReviewer ? 'Xóa' : 'Yêu cầu xóa' }}
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

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

    @if (! $isNew && ! $isReviewer && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview)
        <div class="mb-5 rounded-2xl border border-outline-variant bg-surface-container-low px-4 py-3 text-sm text-on-surface-variant">
            Câu hỏi đang chờ admin duyệt. Bạn không thể chỉnh sửa nội dung cho đến khi admin xử lý hoặc bạn chọn <strong>Trả về nháp</strong>.
        </div>
    @endif

    @if (! $isNew && ($canClone ?? false))
        <form id="clone-question-form" method="post" action="{{ route('admin.questions.clone', $question) }}" class="hidden">
            @csrf
        </form>
    @endif

    {{-- ── MAIN FORM ── --}}
    <form method="post"
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
                                <label class="text-sm font-semibold text-on-surface">Hints (theo thứ tự)</label>
                                <button type="button" @click="addHint()"
                                        class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-2.5 py-1 text-xs font-semibold text-on-surface hover:bg-surface-container-low">
                                    <span class="material-symbols-outlined text-[14px]">add</span>Add Hint
                                </button>
                            </div>
                            <p class="mb-3 text-[11px] leading-4 text-on-surface-variant">
                                Hint hiển thị lần lượt — không hiện hint 2 trước hint 1. Không lấy từ Concept.
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
                        <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Lưu câu hỏi</h2>
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 font-label-md font-semibold text-on-primary transition-colors hover:bg-primary/90">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            {{ $isReviewer ? ($isNew ? 'Tạo bản nháp' : 'Lưu thay đổi') : ($isNew ? 'Tạo và gửi duyệt' : 'Lưu và gửi duyệt') }}
                        </button>
                        <a href="{{ route('admin.questions.index') }}"
                           class="mt-2 flex w-full items-center justify-center rounded-xl border border-outline-variant py-2.5 text-sm font-semibold text-on-surface transition-colors hover:bg-surface-container-low">
                            Hủy bỏ
                        </a>
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
                                <span class="text-sm font-semibold text-on-surface">Câu dành cho exam pool</span>
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
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Phiên bản</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->version }}</dd>
                            </div>
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
                    @if (! $isNew && ($canClone ?? false))
                        <div class="rounded-2xl border border-outline-variant bg-surface p-4">
                            <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Nhân bản</h2>
                            <button type="submit" form="clone-question-form" onclick="return confirm('Tạo bản sao mới từ câu hỏi này?')"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-outline-variant py-2.5 text-sm font-semibold text-on-surface transition-colors hover:bg-surface-container-low">
                                <span class="material-symbols-outlined text-[18px]">content_copy</span>
                                Clone câu hỏi
                            </button>
                        </div>
                    @endif
                @endif

            </div>
        </fieldset>
    </form>

</x-layouts.admin>
