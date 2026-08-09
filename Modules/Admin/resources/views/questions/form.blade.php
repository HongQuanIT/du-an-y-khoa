@php
    $isNew = ! $question->exists;
    $existingOptions = $question->relationLoaded('options')
        ? $question->options
        : collect();
    if ($existingOptions->isEmpty()) {
        $optionRows = [
            ['id' => null, 'content' => '', 'is_correct' => true, 'explanation' => ''],
            ['id' => null, 'content' => '', 'is_correct' => false, 'explanation' => ''],
            ['id' => null, 'content' => '', 'is_correct' => false, 'explanation' => ''],
            ['id' => null, 'content' => '', 'is_correct' => false, 'explanation' => ''],
        ];
    } else {
        $optionRows = $existingOptions->map(fn ($o) => [
            'id' => $o->id,
            'content' => $o->content,
            'is_correct' => (bool) $o->is_correct,
            'explanation' => $o->explanation,
        ])->values()->all();
    }
    $correctIndex = collect($optionRows)->search(fn ($row) => $row['is_correct'] === true);
    if ($correctIndex === false) {
        $correctIndex = 0;
    }
@endphp

<x-layouts.admin :title="$isNew ? 'Tạo câu hỏi' : 'Sửa câu hỏi'">
    <x-admin.page-header :title="$isNew ? 'Tạo câu hỏi' : 'Sửa câu hỏi'"
        :description="$isNew ? 'Tạo bản nháp mới.' : 'Trạng thái: '.$question->status->label().' · v'.$question->version">
        <x-slot:actions>
            <a href="{{ route('admin.questions.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @if (! $isNew)
        <div class="mb-6 flex flex-wrap gap-2 rounded-xl border border-outline-variant bg-surface p-4">
            <span class="me-2 self-center font-label-md text-on-surface-variant">Workflow:</span>

            @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Draft && $canUpdate)
                <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                    @csrf
                    <input type="hidden" name="status" value="in_review">
                    <button class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-md hover:bg-surface-container-low">Gửi duyệt</button>
                </form>
            @endif

            @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::InReview && $canUpdate)
                <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                    @csrf
                    <input type="hidden" name="status" value="draft">
                    <button class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-md hover:bg-surface-container-low">Trả về nháp</button>
                </form>
            @endif

            @if (in_array($question->status, [\Modules\QuestionBank\Enums\QuestionStatus::Draft, \Modules\QuestionBank\Enums\QuestionStatus::InReview], true) && $canPublish)
                <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                    @csrf
                    <input type="hidden" name="status" value="published">
                    <button class="rounded-lg bg-primary px-3 py-1.5 font-label-md text-on-primary"
                        onclick="return confirm('Xuất bản câu hỏi này?')">Xuất bản</button>
                </form>
            @endif

            @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Published && $canPublish)
                <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                    @csrf
                    <input type="hidden" name="status" value="retired">
                    <button class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-md hover:bg-surface-container-low"
                        onclick="return confirm('Ngừng dùng câu hỏi này?')">Retire</button>
                </form>
            @endif

            @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Retired && $canUpdate)
                <form method="post" action="{{ route('admin.questions.transition', $question) }}">
                    @csrf
                    <input type="hidden" name="status" value="draft">
                    <button class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-md hover:bg-surface-container-low">Mở lại nháp</button>
                </form>
            @endif
        </div>
    @endif

    <form method="post"
        action="{{ $isNew ? route('admin.questions.store') : route('admin.questions.update', $question) }}"
        class="space-y-6"
        x-data='{
            options: @json($optionRows),
            correct: {{ (int) $correctIndex }},
            add() {
                this.options.push({ id: null, content: "", is_correct: false, explanation: "" });
            },
            remove(i) {
                if (this.options.length <= 2) return;
                this.options.splice(i, 1);
                if (this.correct >= this.options.length) this.correct = 0;
            }
        }'>
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <fieldset @disabled(! $canUpdate) class="space-y-6">
            <section class="rounded-xl border border-outline-variant bg-surface p-5 space-y-4">
                <h3 class="font-headline-sm text-on-surface">Nội dung</h3>
<div>
                    <x-admin.rich-editor name="stem" label="Câu hỏi" :value="old('stem', $question->stem)" required
                        placeholder="Nhập đề bài / vignette. Có thể chèn ảnh, in đậm, danh sách…" />
                </div>
                <div>
                    <x-admin.rich-editor name="explanation" label="Giải thích chung"
                        :value="old('explanation', $question->explanation)"
                        placeholder="Giải thích sau khi trả lời…" />
                </div>
                <div>
                    <x-admin.rich-editor name="attending_tip" label="Gợi ý (Attending tip)"
                        :value="old('attending_tip', $question->attending_tip)"
                        placeholder="Gợi ý ngắn trước khi xem đáp án…" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block font-label-sm text-on-surface-variant" for="topic_id">Chủ đề *</label>
                        <select id="topic_id" name="topic_id" required class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                            <option value="">— Chọn —</option>
                            @foreach ($topics as $topic)
                                <option value="{{ $topic->id }}" @selected((string) old('topic_id', $question->topic_id) === (string) $topic->id)>{{ $topic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block font-label-sm text-on-surface-variant" for="difficulty">Độ khó *</label>
                        <select id="difficulty" name="difficulty" required class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                            @foreach ($difficulties as $difficulty)
                                <option value="{{ $difficulty->value }}" @selected(old('difficulty', $question->difficulty?->value) === $difficulty->value)>{{ $difficulty->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 font-label-md text-on-surface">
                            <input type="checkbox" name="is_free" value="1" @checked(old('is_free', $question->is_free)) class="rounded text-primary focus:ring-primary">
                            Câu miễn phí (Free)
                        </label>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-headline-sm text-on-surface">Đáp án (chọn 1 đúng)</h3>
                    <button type="button" @click="add()" class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-md hover:bg-surface-container-low">Thêm đáp án</button>
                </div>

                <template x-for="(opt, index) in options" :key="index">
                    <div class="rounded-lg border border-outline-variant/80 p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 font-label-md text-on-surface">
                                <input type="radio" name="correct_option" :value="index" x-model.number="correct" class="text-primary focus:ring-primary">
                                <span x-text="'Đáp án ' + String.fromCharCode(65 + index) + ' — đúng'"></span>
                            </label>
                            <button type="button" @click="remove(index)" class="font-label-sm text-on-surface-variant hover:text-error" x-show="options.length > 2">Xóa</button>
                        </div>
                        <input type="hidden" :name="'options['+index+'][id]'" :value="opt.id || ''">
                        <textarea :name="'options['+index+'][content]'" x-model="opt.content" rows="2" required
                            class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary"
                            placeholder="Nội dung đáp án"></textarea>
                        <input type="text" :name="'options['+index+'][explanation]'" x-model="opt.explanation"
                            class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary"
                            placeholder="Giải thích đáp án (tuỳ chọn)">
                        <input type="hidden" :name="'options['+index+'][is_correct]'" :value="correct === index ? 1 : 0">
                    </div>
                </template>
            </section>
        </fieldset>

        @if ($canUpdate)
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-on-primary">
                {{ $isNew ? 'Tạo nháp' : 'Lưu thay đổi' }}
            </button>
        @endif
    </form>
</x-layouts.admin>
