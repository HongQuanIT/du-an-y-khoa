<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Modules\QuestionBank\Enums\EditorSubmitOutcome;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;

/**
 * Accountability marking on timeline (cờ / lần gửi).
 *
 * Publish gate: 2 green flags → pending_publish is enough. Pending marks default to «Đúng»;
 * Admin marks «Sai» at monthly review — never blocks publish.
 */
final class QuestionQaCompleteness
{
    /** @deprecated Kept for callers; publish no longer requires manual marking. */
    public const MIN_CYCLES_REQUIRING_MANUAL_QA = 2;

    /**
     * @return array{
     *   required: bool,
     *   complete: bool,
     *   pipeline_cycles: int,
     *   pending_submits: int,
     *   pending_reviews: int,
     *   pending_flags: int,
     *   pending_total: int,
     *   summary: string
     * }
     */
    public function assess(Question $question): array
    {
        $pipelineCycles = $question->currentPipelineReviewCycle();
        $minCycle = $question->lastPublishedReviewCycle() + 1;

        $pendingSubmits = QuestionWorkflowEvent::query()
            ->where('question_id', $question->getKey())
            ->where('event_type', QuestionWorkflowEventType::Submit->value)
            ->where('review_cycle', '>=', $minCycle)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', EditorSubmitOutcome::Pending->value);
            })
            ->count();

        $pendingFlags = QuestionReviewerFlag::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', '>=', $minCycle)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', ReviewFlagOutcome::Pending->value);
            })
            ->count();

        $pendingReviews = 0;
        $pendingTotal = $pendingSubmits + $pendingFlags;
        $parts = [];
        if ($pendingSubmits > 0) {
            $parts[] = $pendingSubmits.' lần gửi (mặc định Đúng)';
        }
        if ($pendingFlags > 0) {
            $parts[] = $pendingFlags.' cờ (mặc định Đúng)';
        }

        return [
            // Never gate publish on marking — 2 green flags suffice.
            'required' => false,
            'complete' => true,
            'pipeline_cycles' => $pipelineCycles,
            'pending_submits' => $pendingSubmits,
            'pending_reviews' => $pendingReviews,
            'pending_flags' => $pendingFlags,
            'pending_total' => $pendingTotal,
            'summary' => $parts === []
                ? ''
                : 'Chưa đánh dấu thủ công (đếm báo cáo): '.implode(', ', $parts).'.',
        ];
    }

    public function isCompleteForPublish(Question $question): bool
    {
        return true;
    }

    public function blocksPublish(Question $question): bool
    {
        return false;
    }

    public function publishBlockedMessage(Question $question): string
    {
        return 'Hai cờ xanh đủ điều kiện xuất bản. Đánh dấu Đúng/Sai trên «Lịch sử duyệt» phục vụ họp giao ban, không chặn XB.';
    }
}
