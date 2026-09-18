<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewerFlag;

/**
 * Open queue: first two distinct reviewers fill flag slots.
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
        return $question->reviewer_1_flag === ReviewerFlag::Red->value
            || $question->reviewer_2_flag === ReviewerFlag::Red->value
            || $question->reviewer_1_flag === ReviewerFlag::Red
            || $question->reviewer_2_flag === ReviewerFlag::Red;
    }

    /**
     * Ready for Admin decision: 2 greens, or ≥1 red (fail-fast — Admin must reject).
     */
    public function readyForAdmin(Question $question): bool
    {
        if ($this->hasRedFlag($question)) {
            return true;
        }

        return $this->hasRequiredFlags($question);
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

            $raw = $question->{"reviewer_{$slot}_flag"};
            if ($raw instanceof ReviewerFlag) {
                return $raw;
            }

            if (is_string($raw) && $raw !== '') {
                return ReviewerFlag::from($raw);
            }
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
}
