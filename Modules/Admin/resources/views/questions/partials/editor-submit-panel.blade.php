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
            cancelUrl: @js(route(\App\Support\Auth\PortalRoute::content('questions.index'))),
            submitLabel: @js($submitLabel),
            submitTargetStatus: @js($submitTargetStatus),
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
                    <button type="button"
                        data-testid="editor-submit-for-review"
                        @click="requestReview()"
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

        @php
            $canDiscardOverlayDraft = ! $isNew
                && $question->status === QuestionStatus::Draft
                && (int) $question->published_version > 0;
            $canDiscardUnpublishedDraft = ! $isNew
                && $question->status === QuestionStatus::Draft
                && (int) ($question->published_version ?? 0) < 1
                && (int) $question->version === 0;
        @endphp

        @if ($canDiscardOverlayDraft)
            <button type="button"
                data-testid="editor-discard-draft"
                @click="requestDiscard('overlay')"
                class="mt-2 flex w-full items-center justify-center rounded-xl py-2 text-xs font-semibold text-on-surface-variant transition-colors hover:text-on-surface">
                Hủy bỏ
            </button>
        @elseif ($canDiscardUnpublishedDraft)
            <button type="button"
                data-testid="editor-discard-draft"
                @click="requestDiscard('unpublished')"
                class="mt-2 flex w-full items-center justify-center rounded-xl py-2 text-xs font-semibold text-on-surface-variant transition-colors hover:text-on-surface">
                Hủy bỏ
            </button>
        @else
            <button type="button"
                data-testid="editor-cancel"
                @click="requestLeave()"
                class="mt-2 flex w-full items-center justify-center rounded-xl py-2 text-xs font-semibold text-on-surface-variant transition-colors hover:text-on-surface">
                Hủy bỏ
            </button>
        @endif

        <template x-teleport="body">
            <div x-show="confirm" x-cloak
                data-testid="editor-confirm-modal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @keydown.escape.window="confirm = null">
                <div class="absolute inset-0 bg-on-surface/40" @click="confirm = null"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl"
                    role="alertdialog" aria-modal="true" aria-labelledby="editor-confirm-title">
                    <h3 id="editor-confirm-title" class="text-base font-semibold text-on-surface" x-text="confirm?.title"></h3>
                    <p class="mt-2 text-sm text-on-surface-variant" x-text="confirm?.body"></p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="confirm = null"
                            class="h-10 rounded-lg px-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
                        <button type="button"
                            data-testid="editor-confirm-accept"
                            @click="acceptConfirm()"
                            class="h-10 rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90"
                            :class="confirm?.danger ? 'bg-error' : 'bg-primary'"
                            x-text="confirm?.label"></button>
                    </div>
                </div>
            </div>
        </template>
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
            cancelUrl: config.cancelUrl || '',
            submitLabel: config.submitLabel || 'Gửi duyệt',
            submitTargetStatus: config.submitTargetStatus || 'in_review',
            submitError: '',
            confirm: null,

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
                    }
                }
            },

            requestReview() {
                this.submitError = '';
                const nextStatus = this.submitTargetStatus;
                if (this.lessonCount < 1) {
                    this.submitError = 'Hãy chọn ít nhất một bài học trước khi gửi duyệt.';
                    return;
                }
                if (! this.stickyResubmit && nextStatus === 'in_review' && ! this.hasInstructor) {
                    this.submitError = 'Hãy chọn giảng viên chuyên môn trước khi gửi duyệt.';
                    return;
                }

                if (nextStatus === 'in_flag_review') {
                    this.confirm = {
                        action: 'submit',
                        nextStatus,
                        title: 'Gửi lại để gắn cờ?',
                        body: 'Hai reviewer trước sẽ nhận lại câu. Giảng viên không duyệt lại vòng này.',
                        label: this.submitLabel,
                        danger: false,
                    };
                    return;
                }

                if (this.currentStatus === 'in_review') {
                    this.confirm = {
                        action: 'submit',
                        nextStatus,
                        title: 'Gửi duyệt lại?',
                        body: 'Phiếu giảng viên trên vòng hiện tại sẽ được reset.',
                        label: this.submitLabel,
                        danger: false,
                    };
                    return;
                }

                this.confirm = {
                    action: 'submit',
                    nextStatus,
                    title: 'Gửi duyệt?',
                    body: 'Gửi câu này cho giảng viên duyệt? Trong lúc chờ duyệt sẽ không sửa được nội dung.',
                    label: this.submitLabel,
                    danger: false,
                };
            },

            requestDiscard(kind) {
                this.confirm = kind === 'unpublished'
                    ? {
                        action: 'discard',
                        title: 'Hủy bản nháp?',
                        body: 'Câu hỏi chưa từng xuất bản sẽ bị xóa.',
                        label: 'Xóa bản nháp',
                        danger: true,
                    }
                    : {
                        action: 'discard',
                        title: 'Hủy bản nháp?',
                        body: 'Nội dung đang soạn sẽ bị bỏ, câu hỏi trở lại phiên bản đang xuất bản.',
                        label: 'Hủy bản nháp',
                        danger: true,
                    };
            },

            requestLeave() {
                this.confirm = {
                    action: 'leave',
                    title: 'Rời trang soạn thảo?',
                    body: 'Thay đổi chưa lưu sẽ không được giữ.',
                    label: 'Rời trang',
                    danger: false,
                };
            },

            acceptConfirm() {
                const pending = this.confirm;
                if (! pending) {
                    return;
                }
                this.confirm = null;

                if (pending.action === 'submit') {
                    const statusInput = document.getElementById('question_requested_status');
                    if (statusInput) {
                        statusInput.value = pending.nextStatus;
                    }
                    this.$root.closest('form')?.requestSubmit();
                    return;
                }

                if (pending.action === 'discard') {
                    document.getElementById('editor-discard-draft-form')?.requestSubmit();
                    return;
                }

                if (pending.action === 'leave' && this.cancelUrl) {
                    window.location.assign(this.cancelUrl);
                }
            },
        }));
    });
</script>
