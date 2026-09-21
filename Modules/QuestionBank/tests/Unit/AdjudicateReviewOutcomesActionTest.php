<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Actions\AdjudicateReviewOutcomesAction;
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
use Tests\TestCase;

final class AdjudicateReviewOutcomesActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_reject_confirms_red_flag_and_marks_instructor_miss(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $instructor = User::factory()->create();
        $reviewer = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::PendingPublish,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 2,
            'content_fingerprint' => 'fp-current',
        ]);

        QuestionInstructorReview::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'instructor_id' => $instructor->id,
            'decision' => InstructorReviewDecision::Approved,
            'note' => null,
            'content_fingerprint' => 'fp-old',
            'reviewed_at' => now()->subHour(),
            'outcome' => InstructorReviewOutcome::Pending,
        ]);

        QuestionReviewerFlag::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'reviewer_id' => $reviewer->id,
            'flag' => ReviewerFlag::Red,
            'note' => 'Sai kiến thức',
            'content_fingerprint' => 'fp-old',
            'reviewed_at' => now(),
            'outcome' => ReviewFlagOutcome::Pending,
        ]);

        app(AdjudicateReviewOutcomesAction::class)->onAdminReject($question, $admin, 'confirmed');

        $this->assertSame(
            ReviewFlagOutcome::Confirmed,
            QuestionReviewerFlag::query()->where('question_id', $question->id)->first()?->outcome,
        );
        $this->assertSame(
            InstructorReviewOutcome::Miss,
            QuestionInstructorReview::query()->where('question_id', $question->id)->first()?->outcome,
        );
    }

    public function test_publish_marks_unchanged_red_as_false_positive(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $reviewer = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::Published,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 3,
            'content_fingerprint' => 'same-fp',
        ]);

        QuestionReviewerFlag::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'reviewer_id' => $reviewer->id,
            'flag' => ReviewerFlag::Red,
            'note' => 'Nghi ngờ',
            'content_fingerprint' => 'same-fp',
            'reviewed_at' => now()->subDays(2),
            'outcome' => ReviewFlagOutcome::Pending,
        ]);

        app(AdjudicateReviewOutcomesAction::class)->onPublish($question, $admin);

        $this->assertSame(
            ReviewFlagOutcome::FalsePositive,
            QuestionReviewerFlag::query()->where('question_id', $question->id)->first()?->outcome,
        );
    }

    public function test_publish_confirms_undisputed_green_flags(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $reviewer = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::Published,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 1,
            'content_fingerprint' => 'fp-ok',
        ]);

        QuestionReviewerFlag::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'reviewer_id' => $reviewer->id,
            'flag' => ReviewerFlag::Green,
            'note' => null,
            'content_fingerprint' => 'fp-ok',
            'reviewed_at' => now()->subHour(),
            'outcome' => ReviewFlagOutcome::Pending,
        ]);

        app(AdjudicateReviewOutcomesAction::class)->onPublish($question, $admin);

        $this->assertSame(
            ReviewFlagOutcome::Confirmed,
            QuestionReviewerFlag::query()->where('question_id', $question->id)->first()?->outcome,
        );
    }

    public function test_manual_marks_instructor_reject_as_over_reject(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $instructor = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::Rejected,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 1,
            'content_fingerprint' => 'fp-ok',
        ]);

        $review = QuestionInstructorReview::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'instructor_id' => $instructor->id,
            'decision' => InstructorReviewDecision::Rejected,
            'note' => 'Từ chối nhầm',
            'content_fingerprint' => 'fp-ok',
            'reviewed_at' => now(),
            'outcome' => InstructorReviewOutcome::Pending,
        ]);

        app(AdjudicateReviewOutcomesAction::class)->manualInstructorOutcome(
            $review,
            $admin,
            InstructorReviewOutcome::OverReject,
            'Admin xác nhận từ chối oan',
        );

        $review->refresh();
        $this->assertSame(InstructorReviewOutcome::OverReject, $review->outcome);
        $this->assertSame('admin', $review->outcome_source);
        $this->assertSame($admin->id, (int) $review->outcome_by);
        $this->assertSame('Admin xác nhận từ chối oan', $review->outcome_note);
    }

    public function test_admin_reject_marks_editor_submit_needs_rework(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $editor = User::factory()->create();
        $reviewer = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::PendingPublish,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 1,
            'content_fingerprint' => 'fp-v1',
            'created_by' => $editor->id,
        ]);

        QuestionWorkflowEvent::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'event_type' => QuestionWorkflowEventType::Submit,
            'actor_id' => $editor->id,
            'actor_role' => 'content_editor',
            'outcome' => EditorSubmitOutcome::Pending,
            'content_fingerprint' => 'fp-v1',
            'occurred_at' => now()->subHour(),
        ]);

        QuestionReviewerFlag::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'reviewer_id' => $reviewer->id,
            'flag' => ReviewerFlag::Red,
            'note' => 'Sai kiến thức',
            'content_fingerprint' => 'fp-v1',
            'reviewed_at' => now(),
            'outcome' => ReviewFlagOutcome::Pending,
        ]);

        app(AdjudicateReviewOutcomesAction::class)->onAdminReject($question, $admin, 'confirmed');

        $this->assertSame(
            EditorSubmitOutcome::NeedsRework,
            QuestionWorkflowEvent::query()->where('question_id', $question->id)->first()?->outcome,
        );
    }

    public function test_publish_confirms_editor_submit_matching_fingerprint(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $editor = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::Published,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 2,
            'content_fingerprint' => 'fp-final',
        ]);

        QuestionWorkflowEvent::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 2,
            'event_type' => QuestionWorkflowEventType::Submit,
            'actor_id' => $editor->id,
            'actor_role' => 'content_editor',
            'outcome' => EditorSubmitOutcome::Pending,
            'content_fingerprint' => 'fp-final',
            'occurred_at' => now()->subHour(),
        ]);

        QuestionWorkflowEvent::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'event_type' => QuestionWorkflowEventType::Submit,
            'actor_id' => $editor->id,
            'actor_role' => 'content_editor',
            'outcome' => EditorSubmitOutcome::Pending,
            'content_fingerprint' => 'fp-old',
            'occurred_at' => now()->subDays(2),
        ]);

        app(AdjudicateReviewOutcomesAction::class)->onPublish($question, $admin);

        $byCycle = QuestionWorkflowEvent::query()
            ->where('question_id', $question->id)
            ->where('event_type', QuestionWorkflowEventType::Submit->value)
            ->get()
            ->keyBy(fn (QuestionWorkflowEvent $e): int => (int) $e->review_cycle);

        $this->assertSame(EditorSubmitOutcome::Confirmed, $byCycle->get(2)?->outcome);
        $this->assertSame(EditorSubmitOutcome::NeedsRework, $byCycle->get(1)?->outcome);
    }

    public function test_manual_editor_outcome(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $editor = User::factory()->create();

        $question = Question::factory()->create([
            'status' => QuestionStatus::InReview,
            'difficulty' => Difficulty::Medium,
            'instructor_review_cycle' => 1,
        ]);

        $event = QuestionWorkflowEvent::query()->create([
            'question_id' => $question->id,
            'review_cycle' => 1,
            'event_type' => QuestionWorkflowEventType::Submit,
            'actor_id' => $editor->id,
            'actor_role' => 'content_editor',
            'outcome' => EditorSubmitOutcome::Pending,
            'occurred_at' => now(),
        ]);

        app(AdjudicateReviewOutcomesAction::class)->manualEditorOutcome(
            $event,
            $admin,
            EditorSubmitOutcome::NeedsRework,
            'Thiếu giải thích đáp án sai',
        );

        $event->refresh();
        $this->assertSame(EditorSubmitOutcome::NeedsRework, $event->outcome);
        $this->assertSame('admin', $event->outcome_source);
        $this->assertSame($admin->id, (int) $event->outcome_by);
        $this->assertSame('Thiếu giải thích đáp án sai', $event->outcome_note);
    }
}
