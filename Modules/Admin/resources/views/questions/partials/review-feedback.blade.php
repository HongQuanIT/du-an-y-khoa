@php
    /** @var \Modules\QuestionBank\Models\Question $question */
    $instructorDecision = \Modules\QuestionBank\Enums\InstructorReviewDecision::tryFrom((string) $question->instructor_decision);
    $instructorName = $question->instructor?->name ?? $question->assignedInstructor?->name;
    $instructorNote = filled($question->instructor_note)
        ? trim((string) $question->instructor_note)
        : null;
    if ($instructorNote === null
        && $instructorDecision === \Modules\QuestionBank\Enums\InstructorReviewDecision::Rejected
        && filled($question->rejection_reason)
    ) {
        $instructorNote = trim((string) $question->rejection_reason);
    }

    $reviewerRows = [];
    foreach ([1, 2] as $slot) {
        $flagRaw = $question->{"reviewer_{$slot}_flag"};
        $flag = $flagRaw instanceof \Modules\QuestionBank\Enums\ReviewerFlag
            ? $flagRaw
            : \Modules\QuestionBank\Enums\ReviewerFlag::tryFrom((string) $flagRaw);
        $note = filled($question->{"reviewer_{$slot}_note"})
            ? trim((string) $question->{"reviewer_{$slot}_note"})
            : null;
        $name = $question->{"reviewerSlot{$slot}"}?->name;
        if ($flag === null && $note === null && $name === null) {
            continue;
        }
        $reviewerRows[] = [
            'slot' => $slot,
            'flag' => $flag,
            'note' => $note,
            'name' => $name,
        ];
    }

    $isRejected = $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected;
    $isPublisherRejection = $isRejected && $question->isPublisherRejection();
    $publisherReason = $isPublisherRejection && filled($question->rejection_reason)
        ? trim((string) $question->rejection_reason)
        : null;

    $hasInstructorFeedback = $instructorDecision !== null || filled($instructorNote);
    $isLivePublished = in_array($question->status, [
        \Modules\QuestionBank\Enums\QuestionStatus::Published,
        \Modules\QuestionBank\Enums\QuestionStatus::Private,
    ], true);
    $hasFeedback = ! $isLivePublished
        && ($hasInstructorFeedback || $reviewerRows !== [] || filled($publisherReason));
@endphp

@if ($hasFeedback)
    @php
        $panelClass = match (true) {
            $isRejected => 'border-red-300 bg-red-50 text-red-950',
            $question->hasRedReviewerFlag() => 'border-rose-300 bg-rose-50 text-rose-950',
            $question->hasYellowReviewerFlag() => 'border-amber-300 bg-amber-50 text-amber-950',
            default => 'border-outline-variant bg-surface text-on-surface',
        };
    @endphp
    <section aria-labelledby="review-feedback-title"
        class="mb-5 rounded-2xl border px-4 py-4 {{ $panelClass }}"
        data-testid="question-review-feedback">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined mt-0.5 shrink-0" aria-hidden="true">rate_review</span>
            <div class="min-w-0 flex-1 space-y-4">
                <div>
                    <h2 id="review-feedback-title" class="font-semibold">Phản hồi duyệt</h2>
                    <p class="mt-0.5 text-sm opacity-80">
                        Ghi chú reviewer và lý do giảng viên — dùng khi sửa hoặc quyết định xuất bản.
                    </p>
                </div>

                @if ($hasInstructorFeedback)
                    <article class="rounded-xl border border-current/15 bg-white/70 px-3 py-3"
                        data-testid="instructor-review-feedback">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold">
                                Giảng viên{{ $instructorName ? ' '.$instructorName : '' }}{{ $instructorDecision === \Modules\QuestionBank\Enums\InstructorReviewDecision::Rejected ? ' đã từ chối' : ($instructorDecision === \Modules\QuestionBank\Enums\InstructorReviewDecision::Approved ? ' đã duyệt chuyên môn' : '') }}
                            </p>
                            @if ($instructorDecision === \Modules\QuestionBank\Enums\InstructorReviewDecision::Rejected)
                                <span class="rounded-full bg-red-200 px-2 py-0.5 text-xs font-bold text-red-800">Từ chối</span>
                            @elseif ($instructorDecision === \Modules\QuestionBank\Enums\InstructorReviewDecision::Approved)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">Đã duyệt chuyên môn</span>
                            @endif
                        </div>
                        @php
                            $instructorNoteLabel = $instructorDecision === \Modules\QuestionBank\Enums\InstructorReviewDecision::Rejected
                                ? 'Lý do từ chối:'
                                : 'Ghi chú:';
                            $instructorNoteTone = match ($instructorDecision) {
                                \Modules\QuestionBank\Enums\InstructorReviewDecision::Rejected => 'border-red-200 bg-red-50 text-red-800',
                                \Modules\QuestionBank\Enums\InstructorReviewDecision::Approved => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                default => 'border-outline-variant bg-surface-container-low text-on-surface',
                            };
                        @endphp
                        @if (filled($instructorNote))
                            <p class="mt-2 rounded-lg border px-2.5 py-2 text-sm leading-6 {{ $instructorNoteTone }}">
                                <span class="font-semibold">{{ $instructorNoteLabel }}</span>
                                {{ $instructorNote }}
                            </p>
                        @else
                            <p class="mt-2 text-sm leading-6 text-on-surface">
                                <span class="font-semibold">{{ $instructorNoteLabel }}</span>
                                Không có ghi chú kèm theo.
                            </p>
                        @endif
                        @if ($question->instructor_reviewed_at)
                            <p class="mt-1 text-xs opacity-70">{{ $question->instructor_reviewed_at->format('d/m/Y H:i') }}</p>
                        @endif
                    </article>
                @endif

                @foreach ($reviewerRows as $row)
                    @php
                        $flagLabel = $row['flag']?->label() ?? 'Chưa gắn cờ';
                        $flagTone = match ($row['flag']) {
                            \Modules\QuestionBank\Enums\ReviewerFlag::Green => 'bg-emerald-100 text-emerald-800',
                            \Modules\QuestionBank\Enums\ReviewerFlag::Yellow => 'bg-amber-100 text-amber-800',
                            \Modules\QuestionBank\Enums\ReviewerFlag::Red => 'bg-red-200 text-red-800',
                            default => 'bg-slate-100 text-slate-700',
                        };
                        $noteTone = match ($row['flag']) {
                            \Modules\QuestionBank\Enums\ReviewerFlag::Green => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            \Modules\QuestionBank\Enums\ReviewerFlag::Yellow => 'border-amber-200 bg-amber-50 text-amber-800',
                            \Modules\QuestionBank\Enums\ReviewerFlag::Red => 'border-red-200 bg-red-50 text-red-800',
                            default => 'border-outline-variant bg-surface-container-low text-on-surface',
                        };
                    @endphp
                    <article class="rounded-xl border border-current/15 bg-white/70 px-3 py-3"
                        data-testid="reviewer-flag-feedback-{{ $row['slot'] }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold">
                                Reviewer {{ $row['slot'] }}{{ $row['name'] ? ' · '.$row['name'] : '' }}
                            </p>
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $flagTone }}">{{ $flagLabel }}</span>
                        </div>
                        @if (filled($row['note']))
                            <p class="mt-2 rounded-lg border px-2.5 py-2 text-sm leading-6 {{ $noteTone }}">
                                <span class="font-semibold">Ghi chú:</span>
                                {{ $row['note'] }}
                            </p>
                        @else
                            <p class="mt-2 text-sm leading-6 text-on-surface">
                                <span class="font-semibold">Ghi chú:</span>
                                Không có ghi chú kèm theo.
                            </p>
                        @endif
                    </article>
                @endforeach

                @if (filled($publisherReason))
                    <article class="rounded-xl border border-current/15 bg-white/70 px-3 py-3"
                        data-testid="publisher-rejection-feedback">
                        <p class="text-sm font-semibold">
                            {{ $question->publisher?->name ?: 'Admin' }} đã trả về
                        </p>
                        <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-2.5 py-2 text-sm leading-6 text-red-800">
                            <span class="font-semibold">Lý do:</span>
                            {{ $publisherReason }}
                        </p>
                    </article>
                @endif

                @if ($isRejected && ($canEditContent ?? false))
                    <p class="text-sm">Chuyển về nháp để chỉnh sửa.</p>
                @endif
            </div>
        </div>
    </section>
@endif
