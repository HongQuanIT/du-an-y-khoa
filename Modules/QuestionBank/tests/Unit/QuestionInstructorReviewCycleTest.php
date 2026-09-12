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

    public function test_first_approval_stays_in_review_second_moves_to_pending_publish(): void
    {
        $question = $this->inReviewQuestion();
        $action = app(InstructorReviewQuestionAction::class);

        $first = $action->approve($this->instructor(), $question);
        $this->assertSame(QuestionStatus::InReview, $first->status);
        $this->assertSame(1, app(QuestionInstructorReviewCycle::class)->approvedCountFromSlots($first));

        $second = $action->approve($this->instructor(), $first);
        $this->assertSame(QuestionStatus::PendingPublish, $second->status);
        $this->assertSame(2, app(QuestionInstructorReviewCycle::class)->approvedCountFromSlots($second));
        $this->assertTrue(app(QuestionInstructorReviewCycle::class)->hasRequiredApprovals($second));
    }

    public function test_one_reject_fails_the_question_immediately(): void
    {
        $question = $this->inReviewQuestion();
        $action = app(InstructorReviewQuestionAction::class);
        $action->approve($this->instructor(), $question);

        $rejected = $action->reject($this->instructor(), $question->fresh(), 'Sai kiến thức.');

        $this->assertSame(QuestionStatus::Rejected, $rejected->status);
        $this->assertSame('rejected', $rejected->instructor_2_decision);
        $this->assertSame('Sai kiến thức.', $rejected->rejection_reason);
    }

    public function test_creator_cannot_approve_own_question(): void
    {
        $creator = $this->instructor();
        $question = $this->inReviewQuestion($creator);

        $this->expectException(ValidationException::class);
        app(InstructorReviewQuestionAction::class)->approve($creator, $question);
    }

    public function test_same_instructor_cannot_vote_twice(): void
    {
        $question = $this->inReviewQuestion();
        $instructor = $this->instructor();
        $action = app(InstructorReviewQuestionAction::class);
        $action->approve($instructor, $question);

        $this->expectException(ValidationException::class);
        $action->approve($instructor, $question->fresh());
    }

    public function test_start_or_reset_clears_slots_and_increments_cycle(): void
    {
        $question = $this->inReviewQuestion();
        $cycle = app(QuestionInstructorReviewCycle::class);
        $action = app(InstructorReviewQuestionAction::class);
        $action->approve($this->instructor(), $question);

        $cycle->startOrReset($question->fresh());
        $fresh = $question->fresh();

        $this->assertSame(1, (int) $fresh->instructor_review_cycle);
        $this->assertNull($fresh->instructor_1_id);
        $this->assertNull($fresh->instructor_1_decision);
        $this->assertSame(0, $cycle->approvedCountFromSlots($fresh));
    }

    private function instructor(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);

        return $user;
    }

    private function inReviewQuestion(?User $creator = null): Question
    {
        $creator ??= User::factory()->create();

        return Question::factory()->create([
            'status' => QuestionStatus::InReview,
            'created_by' => $creator->id,
            'version' => 0,
            'instructor_review_cycle' => 0,
        ]);
    }
}
