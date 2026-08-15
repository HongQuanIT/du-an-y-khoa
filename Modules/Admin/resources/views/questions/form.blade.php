@php
    $isNew = ! $question->exists;
    $existingOptions = $question->relationLoaded('options')
        ? $question->options
        : collect();
    if ($existingOptions->isEmpty()) {
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

    $statusBadge = ! $isNew ? match($question->status) {
        \Modules\QuestionBank\Enums\QuestionStatus::Published => ['label' => 'Đã xuất bản', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'],
        \Modules\QuestionBank\Enums\QuestionStatus::InReview  => ['label' => 'Chờ duyệt',   'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
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
                        <span>Phiên bản {{ $question->version }}</span>
                        <span>·</span>
                        <span>Cập nhật {{ $question->updated_at?->diffForHumans() }}</span>
                    </p>
                @else
                    <p class="mt-0.5 font-body-sm text-on-surface-variant">Soạn thảo câu hỏi và lưu bản nháp.</p>
                @endif
            </div>
        </div>

        {{-- Workflow buttons (edit only) --}}
        @if (! $isNew)
            <div class="flex flex-wrap items-center gap-2">
                @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Draft && $canUpdate)
                    <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                        @csrf <input type="hidden" name="status" value="in_review">
                        <button class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300">
                            <span class="material-symbols-outlined text-[16px]">send</span>Gửi duyệt
                        </button>
                    </form>
                @endif
                @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview && $canUpdate)
                    <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                        @csrf <input type="hidden" name="status" value="draft">
                        <button class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3 py-1.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                            <span class="material-symbols-outlined text-[16px]">undo</span>Trả về nháp
                        </button>
                    </form>
                @endif
                @if (in_array($question->status, [\Modules\QuestionBank\Enums\QuestionStatus::Draft, \Modules\QuestionBank\Enums\QuestionStatus::InReview], true) && $canPublish)
                    <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                        @csrf <input type="hidden" name="status" value="published">
                        <button onclick="return confirm('Xuất bản câu hỏi này?')"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3 py-1.5 text-sm font-semibold text-on-primary hover:bg-primary/90">
                            <span class="material-symbols-outlined text-[16px]">publish</span>Xuất bản
                        </button>
                    </form>
                @endif
                @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Published && $canPublish)
                    <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                        @csrf <input type="hidden" name="status" value="retired">
                        <button onclick="return confirm('Ngừng sử dụng câu hỏi này?')"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300">
                            <span class="material-symbols-outlined text-[16px]">block</span>Ngừng dùng
                        </button>
                    </form>
                @endif
                @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Retired && $canUpdate)
                    <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                        @csrf <input type="hidden" name="status" value="draft">
                        <button class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3 py-1.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                            <span class="material-symbols-outlined text-[16px]">restore</span>Khôi phục
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <x-admin.flash />

    {{-- ── MAIN FORM ── --}}
    <form method="post"
          action="{{ $isNew ? route('admin.questions.store') : route('admin.questions.update', $question) }}"
          class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px] lg:items-start"
          x-data='{
              options: @json($optionRows),
              correct: {{ (int) $correctIndex }},
              add() { this.options.push({ id: null, content: "", is_correct: false, explanation: "" }); },
              remove(i) {
                  if (this.options.length <= 2) return;
                  this.options.splice(i, 1);
                  if (this.correct >= this.options.length) this.correct = 0;
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

                {{-- Giải thích --}}
                <div class="rounded-2xl border border-outline-variant bg-surface p-5">
                    <h2 class="mb-4 font-label-lg font-semibold text-on-surface">Giải thích & Gợi ý</h2>
                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="key_info">
                                Ý chính cần gạch chân
                            </label>
                            <textarea id="key_info" name="key_info" rows="4"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                placeholder="Mỗi dòng 1 ý chính hoặc cụm từ cần gạch chân trong câu hỏi...">{{ old('key_info', implode("\n", (array) $question->key_info)) }}</textarea>
                            <p class="text-[11px] leading-4 text-on-surface-variant">
                                Những dòng này sẽ được dùng để gạch chân trong chế độ học tập của học viên.
                            </p>
                        </div>
                        <x-admin.rich-editor name="explanation" label="Giải thích chi tiết (hiển thị sau khi trả lời)"
                            :value="old('explanation', $question->explanation)"
                            placeholder="Lý thuyết, cơ chế, giải thích từng đáp án..." />
                        <x-admin.rich-editor name="attending_tip" label="Gợi ý lâm sàng (hiển thị trước khi chọn)"
                            :value="old('attending_tip', $question->attending_tip)"
                            placeholder="Gợi ý ngắn giúp người học suy luận trước khi chọn..." />
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

                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest transition-colors"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="isDragging = false; handleDrop($event)"
                         @paste.window="handlePaste($event)"
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
                            {{ $isNew ? 'Tạo bản nháp' : 'Lưu thay đổi' }}
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
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="topic_id">Chủ đề *</label>
                            <select id="topic_id" name="topic_id" required
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                <option value="">— Chọn chủ đề —</option>
                                @foreach ($topics as $topic)
                                    <option value="{{ $topic->id }}" @selected((string) old('topic_id', $question->topic_id) === (string) $topic->id)>{{ $topic->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="difficulty">Độ khó *</label>
                            <select id="difficulty" name="difficulty" required
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                @foreach ($difficulties as $d)
                                    <option value="{{ $d->value }}" @selected(old('difficulty', $question->difficulty?->value) === $d->value)>{{ $d->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-outline-variant px-3 py-2.5 transition-colors hover:bg-surface-container-low">
                            <input type="checkbox" name="is_free" value="1"
                                   @checked(old('is_free', $question->is_free))
                                   class="size-4 rounded text-primary focus:ring-primary">
                            <span class="text-sm font-semibold text-on-surface">Câu miễn phí (Free)</span>
                        </label>
                    </div>
                </div>

                {{-- Thông tin --}}
                @if (! $isNew)
                    <div class="rounded-2xl border border-outline-variant bg-surface p-4">
                        <h2 class="mb-3 font-label-md font-semibold text-on-surface-variant">Thông tin</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Phiên bản</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->version }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Tạo lúc</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->created_at?->format('d/m/Y') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Cập nhật</dt>
                                <dd class="font-semibold text-on-surface">{{ $question->updated_at?->diffForHumans() }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

            </div>
        </fieldset>
    </form>

</x-layouts.admin>
