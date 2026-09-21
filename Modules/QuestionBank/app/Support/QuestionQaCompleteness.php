<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Modules\QuestionBank\Enums\EditorSubmitOutcome;
use Modules\QuestionBank\Enums\InstructorReviewOutcome;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;

/**
 * Publish QA gate: pipeline ≥2 rounds requires all current-pipeline outcomes adjudicated.
 */
final class QuestionQaCompleteness
{
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
        $required = $pipelineCycles >= self::MIN_CYCLES_REQUIRING_MANUAL_QA;

        if (! $required) {
            return [
                'required' => false,
                'complete' => true,
                'pipeline_cycles' => $pipelineCycles,
                'pending_submits' => 0,
                'pending_reviews' => 0,
                'pending_flags' => 0,
                'pending_total' => 0,
                'summary' => '',
            ];
        }

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

        $pendingReviews = QuestionInstructorReview::query()
            ->where('question_id', $question->getKey())
            ->where('review_cycle', '>=', $minCycle)
            ->where(function ($query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', InstructorReviewOutcome::Pending->value);
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

        $pendingTotal = $pendingSubmits + $pendingReviews + $pendingFlags;
        $parts = [];
        if ($pendingSubmits > 0) {
            $parts[] = $pendingSubmits.' lần gửi duyệt';
        }
        if ($pendingReviews > 0) {
            $parts[] = $pendingReviews.' phiếu GV';
        }
        if ($pendingFlags > 0) {
            $parts[] = $pendingFlags.' cờ reviewer';
        }

        return [
            'required' => true,
            'complete' => $pendingTotal === 0,
            'pipeline_cycles' => $pipelineCycles,
            'pending_submits' => $pendingSubmits,
            'pending_reviews' => $pendingReviews,
            'pending_flags' => $pendingFlags,
            'pending_total' => $pendingTotal,
            'summary' => $parts === []
                ? ''
                : 'Còn chưa đánh giá QA: '.implode(', ', $parts).'.',
        ];
    }

    public function isCompleteForPublish(Question $question): bool
    {
        return $this->assess($question)['complete'];
    }

    public function blocksPublish(Question $question): bool
    {
        $assessment = $this->assess($question);

        return $assessment['required'] && ! $assessment['complete'];
    }

    public function publishBlockedMessage(Question $question): string
    {
        $assessment = $this->assess($question);
        $base = 'Pipeline ≥'.$assessment['pipeline_cycles'].' vòng — hãy đánh giá QA trên «Lịch sử duyệt» trước khi xuất bản.';

        return filled($assessment['summary'])
            ? $base.' '.$assessment['summary']
            : $base;
    }
}
