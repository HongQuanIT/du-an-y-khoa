<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;
use Tests\TestCase;

final class QuestionPipelineProgressLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_uses_pipeline_cycle_after_last_publish_not_lifetime(): void
    {
        $question = Question::factory()->create([
            'status' => QuestionStatus::InFlagReview,
            'instructor_review_cycle' => 4,
            'pipeline_reject_count' => 0,
            'published_version' => 1,
            'version' => 1,
        ]);

        QuestionWorkflowEvent::query()->create([
            'question_id' => $question->getKey(),
            'review_cycle' => 1,
            'published_version' => 1,
            'event_type' => QuestionWorkflowEventType::Publish,
            'occurred_at' => now(),
        ]);

        $question->setAttribute('last_published_review_cycle', 1);

        $this->assertSame(3, $question->currentPipelineReviewCycle());
        $this->assertSame('Vòng 3', $question->pipelineProgressLabel());
    }

    public function test_label_keeps_absolute_cycle_when_never_published(): void
    {
        $question = Question::factory()->create([
            'status' => QuestionStatus::InReview,
            'instructor_review_cycle' => 2,
            'pipeline_reject_count' => 1,
            'published_version' => null,
            'version' => 0,
        ]);

        $this->assertSame(2, $question->currentPipelineReviewCycle());
        $this->assertSame('Vòng 2 · 1 lần trả về', $question->pipelineProgressLabel());
    }
}
