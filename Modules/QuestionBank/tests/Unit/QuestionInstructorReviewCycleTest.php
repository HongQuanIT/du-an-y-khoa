<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Actions\InstructorReviewQuestionAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionInstructorReviewCycle;
use Tests\TestCase;

final class QuestionInstructorReviewCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_assigned_instructor_approval_moves_to_flag_review(): void
    {
        $instructor = $this->instructor();
        $question = $this->inReviewQuestion(assigned: $instructor);
        $action = app(InstructorReviewQuestionAction::class);

        $approved = $action->approve($instructor, $question);
        $this->assertSame(QuestionStatus::InFlagReview, $approved->status);
        $this->assertSame('approved', $approved->instructor_decision);
        $this->assertTrue(app(QuestionInstructorReviewCycle::class)->instructorApproved($approved));
    }

    public function test_one_reject_fails_the_question_immediately(): void
    {
        $instructor = $this->instructor();
        $question = $this->inReviewQuestion(assigned: $instructor);
        $rejected = app(InstructorReviewQuestionAction::class)
            ->reject($instructor, $question, 'Sai kiến thức.');

        $this->assertSame(QuestionStatus::Rejected, $rejected->status);
        $this->assertSame('rejected', $rejected->instructor_decision);
        $this->assertSame('Sai kiến thức.', $rejected->rejection_reason);
        $this->assertTrue($rejected->isInstructorRejection());
        $this->assertFalse($rejected->isPublisherRejection());
        $this->assertSame('Giảng viên từ chối', $rejected->editorialSubmissionLabel());
    }

    public function test_unassigned_instructor_cannot_approve(): void
    {
        $assigned = $this->instructor();
        $other = $this->instructor();
        $question = $this->inReviewQuestion(assigned: $assigned);

        $this->expectException(ValidationException::class);
        app(InstructorReviewQuestionAction::class)->approve($other, $question);
    }

    public function test_creator_cannot_approve_own_question(): void
    {
        $creator = $this->instructor();
        $question = $this->inReviewQuestion($creator, $creator);

        $this->expectException(ValidationException::class);
        app(InstructorReviewQuestionAction::class)->approve($creator, $question);
    }

    public function test_same_instructor_cannot_vote_twice(): void
    {
        $instructor = $this->instructor();
        $question = $this->inReviewQuestion(assigned: $instructor);
        $action = app(InstructorReviewQuestionAction::class);
        $action->approve($instructor, $question);

        $this->expectException(ValidationException::class);
        $action->approve($instructor, $question->fresh());
    }

    public function test_start_or_reset_clears_decision_and_increments_cycle(): void
    {
        $instructor = $this->instructor();
        $question = $this->inReviewQuestion(assigned: $instructor);
        $cycle = app(QuestionInstructorReviewCycle::class);
        app(InstructorReviewQuestionAction::class)->approve($instructor, $question);

        $cycle->startOrReset($question->fresh());
        $fresh = $question->fresh();

        $this->assertSame(1, (int) $fresh->instructor_review_cycle);
        $this->assertNull($fresh->instructor_decision);
        $this->assertSame($instructor->id, (int) $fresh->assigned_instructor_id);
        $this->assertSame(0, $cycle->approvedCountFromSlots($fresh));
    }

    private function instructor(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);

        return $user;
    }

    private function inReviewQuestion(?User $creator = null, ?User $assigned = null): Question
    {
        $creator ??= User::factory()->create();

        return Question::factory()->create([
            'status' => QuestionStatus::InReview,
            'created_by' => $creator->id,
            'assigned_instructor_id' => $assigned?->id,
            'version' => 0,
            'instructor_review_cycle' => 0,
        ]);
    }
}
