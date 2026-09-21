<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\EditorSubmitOutcome;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\InstructorReviewOutcome;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;
use Modules\QuestionBank\Support\QuestionQaCompleteness;
use Tests\TestCase;

final class QuestionQaCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_pipeline_cycle_does_not_require_manual_qa(): void
    {
        $question = Question::factory()->create([
            'status' => QuestionStatus::PendingPublish,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 1,
            'published_version' => null,
            'version' => 0,
        ]);

        $assessment = app(QuestionQaCompleteness::class)->assess($question);

        $this->assertFalse($assessment['required']);
        $this->assertTrue($assessment['complete']);
        $this->assertFalse(app(QuestionQaCompleteness::class)->blocksPublish($question));
    }

    public function test_two_pipeline_cycles_block_when_outcomes_pending(): void
    {
        $instructor = User::factory()->create();
        $reviewer = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::PendingPublish,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 2,
            'published_version' => null,
            'version' => 0,
        ]);

        QuestionWorkflowEvent::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'event_type' => QuestionWorkflowEventType::Submit,
            'outcome' => EditorSubmitOutcome::Pending,
            'occurred_at' => now(),
        ]);

        QuestionInstructorReview::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'instructor_id' => $instructor->id,
            'decision' => InstructorReviewDecision::Approved,
            'reviewed_at' => now(),
            'outcome' => InstructorReviewOutcome::Pending,
        ]);

        QuestionReviewerFlag::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'reviewer_id' => $reviewer->id,
            'flag' => ReviewerFlag::Green,
            'reviewed_at' => now(),
            'outcome' => ReviewFlagOutcome::Pending,
        ]);

        $qa = app(QuestionQaCompleteness::class);
        $this->assertTrue($qa->blocksPublish($question));
        $assessment = $qa->assess($question);
        $this->assertSame(2, $assessment['pipeline_cycles']);
        $this->assertSame(3, $assessment['pending_total']);
    }

    public function test_two_pipeline_cycles_complete_when_all_adjudicated(): void
    {
        $instructor = User::factory()->create();
        $reviewer = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::PendingPublish,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 2,
            'published_version' => null,
            'version' => 0,
        ]);

        QuestionWorkflowEvent::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'event_type' => QuestionWorkflowEventType::Submit,
            'outcome' => EditorSubmitOutcome::NeedsRework,
            'occurred_at' => now()->subDay(),
        ]);
        QuestionWorkflowEvent::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'event_type' => QuestionWorkflowEventType::Submit,
            'outcome' => EditorSubmitOutcome::Confirmed,
            'occurred_at' => now(),
        ]);

        QuestionInstructorReview::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'instructor_id' => $instructor->id,
            'decision' => InstructorReviewDecision::Approved,
            'reviewed_at' => now(),
            'outcome' => InstructorReviewOutcome::Confirmed,
        ]);

        QuestionReviewerFlag::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'reviewer_id' => $reviewer->id,
            'flag' => ReviewerFlag::Green,
            'reviewed_at' => now(),
            'outcome' => ReviewFlagOutcome::Confirmed,
        ]);

        $this->assertFalse(app(QuestionQaCompleteness::class)->blocksPublish($question));
    }
}
