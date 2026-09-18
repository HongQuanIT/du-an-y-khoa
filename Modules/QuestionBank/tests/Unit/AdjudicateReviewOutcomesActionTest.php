<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Actions\AdjudicateReviewOutcomesAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\InstructorReviewOutcome;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
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
}
