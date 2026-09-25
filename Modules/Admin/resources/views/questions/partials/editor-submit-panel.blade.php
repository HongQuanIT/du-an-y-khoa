@php
    use Modules\QuestionBank\Enums\QuestionStatus;

    $isLiveWorkingCopy = ! $isNew && in_array($question->status, [
        QuestionStatus::Published,
        QuestionStatus::Private,
    ], true);

    $lessonCount = old('lesson_ids')
        ? count((array) old('lesson_ids'))
        : ($question->relationLoaded('lessons') ? $question->lessons->count() : 0);

    $canSubmitFlow = $canSubmit ?? false;
    $isInReview = ! $isNew && $question->status === QuestionStatus::InReview;
    $isInFlagReview = ! $isNew && $question->status === QuestionStatus::InFlagReview;
    $isDraftLike = $isNew
        || $question->status === QuestionStatus::Draft
        || $isLiveWorkingCopy
        || $isInFlagReview;

    $isStickyResubmit = ! $isNew
        && ! $isRejected
        && $question->isStickyResubmitEligible()
        && $question->status === QuestionStatus::Draft;

    $saveDraftLabel = $isNew || $question->status === QuestionStatus::Draft
        ? 'Lưu nháp'
        : ($isLiveWorkingCopy ? 'Lưu bản làm việc' : 'Lưu nháp');

    $submitLabel = $isStickyResubmit
        ? 'Gửi lại để gắn cờ'
        : ($isInReview ? 'Lưu & gửi duyệt lại' : 'Gửi duyệt');

    $submitTargetStatus = $isStickyResubmit
        ? QuestionStatus::InFlagReview->value
        : QuestionStatus::InReview->value;

    $showSubmitCta = $canSubmitFlow && ($isDraftLike || $isInReview);
@endphp

@if ($canEditContent)
    <div class="rounded-2xl border border-outline-variant bg-surface p-4"
        data-testid="editor-submit-panel"
        @if ($isStickyResubmit) data-sticky-resubmit="1" @endif
        x-data="editorSubmitPanel({
            initialLessonCount: {{ (int) $lessonCount }},
            initialInstructorId: @js(old('assigned_instructor_id', $question->assigned_instructor_id)),
            currentStatus: @js($isNew ? '' : $question->status->value),
            isNew: @js($isNew),
            canSubmit: @js($canSubmitFlow),
            stickyResubmit: @js($isStickyResubmit),
        })">
        <div class="mb-3">
            @if ($isStickyResubmit)
                <h2 class="font-label-md font-semibold text-on-surface">Gửi lại gắn cờ</h2>
                <p class="mt-0.5 text-[11px] leading-4 text-on-surface-variant">
                    Hai reviewer trước sẽ nhận lại câu. Giảng viên không duyệt lại vòng này.
                </p>
            @else
                <h2 class="font-label-md font-semibold text-on-surface">Gửi duyệt chuyên môn</h2>
                <p class="mt-0.5 text-[11px] leading-4 text-on-surface-variant">
                    Chọn giảng viên đúng môn, rồi gửi duyệt. Nội dung chỉ lên QBank sau khi GV + reviewer + Admin hoàn tất.
                </p>
            @endif
        </div>

        @if ($isRejected)
            <p class="mb-3 text-xs leading-5 text-on-surface-variant">
                @if ($question->isDualRedRejection())
                    Câu bị trả về vì hai cờ đỏ. Chuyển về nháp để sửa — cặp reviewer sticky được giữ, gửi lại không qua giảng viên.
                @else
                    Câu đang bị từ chối. Chuyển về nháp để chỉnh sửa trước khi gửi lại.
                @endif
            </p>
            <button type="submit"
                form="editor-return-draft-form"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90">
                <span class="material-symbols-outlined text-[18px]">undo</span>
                Chuyển về nháp để chỉnh sửa
            </button>
        @else
            <ul class="mb-3 space-y-1.5 rounded-xl bg-surface-container-low px-3 py-2.5 text-xs">
                <li class="flex items-center gap-2" :class="lessonCount > 0 ? 'text-emerald-800' : 'text-amber-900'">
                    <span class="material-symbols-outlined text-[16px]"
                        x-text="lessonCount > 0 ? 'check_circle' : 'error'" aria-hidden="true"></span>
                    <span x-text="lessonCount > 0 ? ('Đã chọn ' + lessonCount + ' bài học') : 'Chưa chọn bài học'"></span>
                </li>
                @unless ($isStickyResubmit)
                    <li class="flex items-center gap-2" :class="hasInstructor ? 'text-emerald-800' : 'text-amber-900'">
                        <span class="material-symbols-outlined text-[16px]"
                            x-text="hasInstructor ? 'check_circle' : 'error'" aria-hidden="true"></span>
                        <span x-text="hasInstructor ? 'Đã chọn giảng viên' : 'Chưa chọn giảng viên'"></span>
                    </li>
                @endunless
            </ul>

            @unless ($isStickyResubmit)
                <div class="mb-3">
                    @include('admin::questions.partials.instructor-picker')
                </div>
            @endunless

            <p x-show="submitError" x-cloak class="mb-2 text-xs font-medium text-error" x-text="submitError"></p>

            <div class="grid grid-cols-1 gap-2">
                @if ($showSubmitCta)
                    <button type="submit"
                        data-testid="editor-submit-for-review"
                        @click="prepareSubmit($event, @js($submitTargetStatus))"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 font-label-md font-semibold text-on-primary hover:bg-primary/90">
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        {{ $submitLabel }}
                    </button>
                @endif

                <button type="submit"
                    data-testid="editor-save-draft"
                    @click="prepareSubmit($event, @js(QuestionStatus::Draft->value))"
                    @class([
                        'flex w-full items-center justify-center gap-2 rounded-xl py-2.5 font-label-md font-semibold transition',
                        'border border-outline-variant text-on-surface hover:bg-surface-container-low' => $showSubmitCta,
                        'bg-primary text-on-primary hover:bg-primary/90' => ! $showSubmitCta,
                    ])>
                    <span class="material-symbols-outlined text-[18px]">{{ $isInReview ? 'undo' : 'save' }}</span>
                    {{ $isInReview ? 'Rút về nháp' : $saveDraftLabel }}
                </button>
            </div>

            @if ($isInReview)
                <p class="mt-2 text-[11px] leading-4 text-on-surface-variant">
                    «Lưu & gửi duyệt lại» sẽ reset phiếu giảng viên trên vòng hiện tại.
                </p>
            @endif
        @endif

        <a href="{{ route('admin.questions.index') }}"
           class="mt-2 flex w-full items-center justify-center rounded-xl py-2 text-xs font-semibold text-on-surface-variant transition-colors hover:text-on-surface">
            Hủy bỏ
        </a>
    </div>
@endif

<script>
    document.addEventListener('alpine:init', () => {
        if (window.__editorSubmitPanelRegistered) return;
        window.__editorSubmitPanelRegistered = true;

        Alpine.data('editorSubmitPanel', (config) => ({
            lessonCount: Number(config.initialLessonCount || 0),
            hasInstructor: Boolean(config.initialInstructorId),
            currentStatus: config.currentStatus || '',
            isNew: Boolean(config.isNew),
            canSubmit: Boolean(config.canSubmit),
            stickyResubmit: Boolean(config.stickyResubmit),
            submitError: '',

            init() {
                const form = this.$root.closest('form');
                form?.addEventListener('question-lessons-changed', (event) => {
                    const ids = event.detail?.lessonIds;
                    this.lessonCount = Array.isArray(ids) ? ids.length : this.lessonCount;
                });
                form?.addEventListener('instructor-assignment-changed', (event) => {
                    this.hasInstructor = Boolean(event.detail?.selectedId);
                    if (typeof event.detail?.lessonCount === 'number') {
                        this.lessonCount = event.detail.lessonCount;
                    }
                });
                this.$watch('hasInstructor', () => { this.submitError = ''; });
                this.$watch('lessonCount', () => { this.submitError = ''; });
            },

            prepareSubmit(event, nextStatus) {
                this.submitError = '';
                const statusInput = document.getElementById('question_requested_status');
                if (statusInput) {
                    statusInput.value = nextStatus;
                }

                if (! this.isNew && this.currentStatus === 'in_review' && nextStatus === 'draft') {
                    event.preventDefault();
                    document.getElementById('editor-return-draft-form')?.submit();
                    return;
                }

                if (nextStatus === 'in_review' || nextStatus === 'in_flag_review') {
                    if (this.lessonCount < 1) {
                        event.preventDefault();
                        this.submitError = 'Hãy chọn ít nhất một bài học trước khi gửi duyệt.';
                        return;
                    }
                    if (! this.stickyResubmit && nextStatus === 'in_review' && ! this.hasInstructor) {
                        event.preventDefault();
                        this.submitError = 'Hãy chọn giảng viên chuyên môn trước khi gửi duyệt.';
                        return;
                    }
                }
            },
        }));
    });
</script>
