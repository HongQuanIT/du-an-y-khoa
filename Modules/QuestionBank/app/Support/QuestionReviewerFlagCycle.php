<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFlagChangeEvent;
use Modules\QuestionBank\Models\QuestionReviewerFlag;

/**
 * Open queue (or sticky pair after dual-red): first two distinct reviewers fill flag slots.
 * Transitions only after both slots are filled: 2 green / 2 red / conflict.
 */
final class QuestionReviewerFlagCycle
{
    public const REQUIRED_FLAGS = 2;

    public function clearFlags(Question $question): void
    {
        $question->forceFill([
            'reviewer_1_id' => null,
            'reviewer_1_flag' => null,
            'reviewer_1_note' => null,
            'reviewer_2_id' => null,
            'reviewer_2_flag' => null,
            'reviewer_2_note' => null,
        ])->save();
    }

    public function clearStickyPair(Question $question): void
    {
        $question->forceFill([
            'sticky_reviewer_1_id' => null,
            'sticky_reviewer_2_id' => null,
        ])->save();
    }

    public function setStickyPair(Question $question): void
    {
        $question->forceFill([
            'sticky_reviewer_1_id' => $question->reviewer_1_id,
            'sticky_reviewer_2_id' => $question->reviewer_2_id,
        ])->save();
    }

    public function recordFlag(Question $question, User $reviewer, ReviewerFlag $flag, ?string $note = null): int
    {
        $this->assertCanFlag($question, $reviewer);

        QuestionReviewerFlag::query()->create([
            'question_id' => $question->getKey(),
            'review_cycle' => (int) $question->instructor_review_cycle,
            'reviewer_id' => $reviewer->getKey(),
            'flag' => $flag,
            'note' => $note,
            'content_fingerprint' => $question->content_fingerprint,
            'reviewed_at' => now(),
        ]);

        $this->fillNextSlot($question, $reviewer, $flag, $note);

        return $this->flagCount($question->fresh());
    }

    /**
     * Update own flag while in flag_conflict. Always writes an audit change event.
     */
    public function changeFlagInConflict(
        Question $question,
        User $reviewer,
        ReviewerFlag $toFlag,
        ?string $note,
        bool $responsibilityAcked,
    ): void {
        $fromFlag = $this->actorFlag($question, $reviewer);
        if ($fromFlag === null) {
            throw ValidationException::withMessages([
                'flag' => 'Bạn chưa gắn cờ cho câu này.',
            ]);
        }

        $reaffirmed = $fromFlag === $toFlag;

        if (! $reaffirmed && ! $responsibilityAcked) {
            throw ValidationException::withMessages([
                'responsibility_acked' => 'Bạn phải xác nhận đã rà soát kỹ và chịu trách nhiệm trước khi đổi cờ.',
            ]);
        }

        if ($toFlag->requiresNote() && ($note === null || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'Cờ đỏ bắt buộc phải ghi chú lý do.',
            ]);
        }

        $this->writeSlotFlag($question, $reviewer, $toFlag, $note);

        QuestionReviewerFlag::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', (int) $question->instructor_review_cycle)
            ->where('reviewer_id', $reviewer->getKey())
            ->update([
                'flag' => $toFlag->value,
                'note' => $note,
                'reviewed_at' => now(),
                'reaffirmed_at' => $reaffirmed ? now() : null,
                'updated_at' => now(),
            ]);

        QuestionFlagChangeEvent::query()->create([
            'question_id' => $question->getKey(),
            'review_cycle' => (int) $question->instructor_review_cycle,
            'reviewer_id' => $reviewer->getKey(),
            'from_flag' => $fromFlag->value,
            'to_flag' => $toFlag->value,
            'note' => $note,
            'reaffirmed' => $reaffirmed,
            'responsibility_acked' => ! $reaffirmed && $responsibilityAcked,
            'ack_text_version' => ! $reaffirmed
                ? QuestionFlagChangeEvent::ACK_TEXT_VERSION
                : null,
        ]);
    }

    public function flagCount(Question $question): int
    {
        $count = 0;
        foreach ([1, 2] as $slot) {
            if ($question->{"reviewer_{$slot}_flag"} !== null) {
                $count++;
            }
        }

        return $count;
    }

    public function hasRequiredFlags(Question $question): bool
    {
        return $this->flagCount($question) >= self::REQUIRED_FLAGS;
    }

    public function hasRedFlag(Question $question): bool
    {
        return $this->slotFlag($question, 1) === ReviewerFlag::Red
            || $this->slotFlag($question, 2) === ReviewerFlag::Red;
    }

    public function bothGreen(Question $question): bool
    {
        return $this->hasRequiredFlags($question)
            && $this->slotFlag($question, 1) === ReviewerFlag::Green
            && $this->slotFlag($question, 2) === ReviewerFlag::Green;
    }

    public function bothRed(Question $question): bool
    {
        return $this->hasRequiredFlags($question)
            && $this->slotFlag($question, 1) === ReviewerFlag::Red
            && $this->slotFlag($question, 2) === ReviewerFlag::Red;
    }

    public function isConflictPair(Question $question): bool
    {
        return $this->hasRequiredFlags($question)
            && ! $this->bothGreen($question)
            && ! $this->bothRed($question);
    }

    /**
     * Ready for Admin publish decision: exactly 2 greens (no red).
     */
    public function readyForAdmin(Question $question): bool
    {
        return $this->bothGreen($question);
    }

    /**
     * @return list<int>
     */
    public function reviewerIds(Question $question): array
    {
        $ids = [];
        foreach ([1, 2] as $slot) {
            $id = $question->{"reviewer_{$slot}_id"};
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public function stickyReviewerIds(Question $question): array
    {
        $ids = [];
        foreach ([1, 2] as $slot) {
            $id = $question->{"sticky_reviewer_{$slot}_id"};
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function actorHasFlagged(Question $question, User $actor): bool
    {
        return $this->actorFlag($question, $actor) !== null;
    }

    public function actorFlag(Question $question, User $actor): ?ReviewerFlag
    {
        $actorId = (int) $actor->getKey();

        foreach ([1, 2] as $slot) {
            if ((int) $question->{"reviewer_{$slot}_id"} !== $actorId) {
                continue;
            }

            return $this->slotFlag($question, $slot);
        }

        return null;
    }

    public function actorNote(Question $question, User $actor): ?string
    {
        $actorId = (int) $actor->getKey();

        foreach ([1, 2] as $slot) {
            if ((int) $question->{"reviewer_{$slot}_id"} !== $actorId) {
                continue;
            }

            $note = $question->{"reviewer_{$slot}_note"};

            return filled($note) ? (string) $note : null;
        }

        return null;
    }

    public function actorIsStickyReviewer(Question $question, User $actor): bool
    {
        $actorId = (int) $actor->getKey();

        return (int) $question->sticky_reviewer_1_id === $actorId
            || (int) $question->sticky_reviewer_2_id === $actorId;
    }

    private function slotFlag(Question $question, int $slot): ?ReviewerFlag
    {
        $raw = $question->{"reviewer_{$slot}_flag"};
        if ($raw instanceof ReviewerFlag) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            return ReviewerFlag::tryFrom($raw);
        }

        return null;
    }

    private function assertCanFlag(Question $question, User $reviewer): void
    {
        if ((int) $question->created_by === (int) $reviewer->getKey()) {
            throw ValidationException::withMessages([
                'flag' => 'Người soạn không được tự gắn cờ câu hỏi của mình.',
            ]);
        }

        if ((int) $question->assigned_instructor_id === (int) $reviewer->getKey()) {
            throw ValidationException::withMessages([
                'flag' => 'Giảng viên đã duyệt chuyên môn không gắn cờ reviewer.',
            ]);
        }

        if ($question->hasStickyReviewers() && ! $this->actorIsStickyReviewer($question, $reviewer)) {
            throw ValidationException::withMessages([
                'flag' => 'Chỉ hai reviewer đã gắn cờ đỏ vòng trước được review lại câu này.',
            ]);
        }

        if ($this->actorHasFlagged($question, $reviewer)) {
            throw ValidationException::withMessages([
                'flag' => 'Bạn đã gắn cờ cho vòng này.',
            ]);
        }

        $exists = QuestionReviewerFlag::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', (int) $question->instructor_review_cycle)
            ->where('reviewer_id', $reviewer->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'flag' => 'Bạn đã gắn cờ cho vòng này.',
            ]);
        }
    }

    private function fillNextSlot(
        Question $question,
        User $reviewer,
        ReviewerFlag $flag,
        ?string $note,
    ): void {
        if ($question->reviewer_1_id === null) {
            $question->forceFill([
                'reviewer_1_id' => $reviewer->getKey(),
                'reviewer_1_flag' => $flag->value,
                'reviewer_1_note' => $note,
            ])->save();

            return;
        }

        if ($question->reviewer_2_id === null) {
            $question->forceFill([
                'reviewer_2_id' => $reviewer->getKey(),
                'reviewer_2_flag' => $flag->value,
                'reviewer_2_note' => $note,
            ])->save();

            return;
        }

        throw ValidationException::withMessages([
            'flag' => 'Đã đủ 2 cờ reviewer cho vòng này.',
        ]);
    }

    private function writeSlotFlag(
        Question $question,
        User $reviewer,
        ReviewerFlag $flag,
        ?string $note,
    ): void {
        $actorId = (int) $reviewer->getKey();

        if ((int) $question->reviewer_1_id === $actorId) {
            $question->forceFill([
                'reviewer_1_flag' => $flag->value,
                'reviewer_1_note' => $note,
            ])->save();

            return;
        }

        if ((int) $question->reviewer_2_id === $actorId) {
            $question->forceFill([
                'reviewer_2_flag' => $flag->value,
                'reviewer_2_note' => $note,
            ])->save();

            return;
        }

        throw ValidationException::withMessages([
            'flag' => 'Bạn không phải reviewer của câu này.',
        ]);
    }
}
