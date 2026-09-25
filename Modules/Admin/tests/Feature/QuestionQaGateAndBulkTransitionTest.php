<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\EditorSubmitOutcome;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\InstructorReviewOutcome;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionInstructorReview;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
use Modules\QuestionBank\Models\QuestionWorkflowEvent;
use Modules\QuestionBank\Models\Subject;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionQaGateAndBulkTransitionTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private Subject $subject;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->subject = $this->makeSubject(['name' => 'Nội QA bulk', 'slug' => 'noi-qa-bulk']);
        $this->lesson = $this->makeLesson([
            'name' => 'Suy tim QA bulk',
            'slug' => 'suy-tim-qa-bulk',
            'subjects' => [$this->subject],
        ]);
    }

    public function test_publish_allowed_when_pipeline_has_two_cycles_even_if_marks_pending(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->pendingPublishReady(cycle: 2, withPendingQa: true);

        $this->actingAsStaff($admin)
            ->from(route('admin.questions.edit', $question))
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::Published, $question->fresh()->status);
    }

    public function test_publish_allowed_when_pipeline_has_two_cycles_and_qa_complete(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->pendingPublishReady(cycle: 2, withPendingQa: false);

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::Published, $question->fresh()->status);
    }

    public function test_bulk_publish_only_one_cycle_two_greens(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $ready = $this->pendingPublishReady(cycle: 1, withPendingQa: false);
        $multiCycle = $this->pendingPublishReady(cycle: 2, withPendingQa: false);

        $this->actingAsStaff($admin)
            ->postJson(route('admin.questions.bulk-transition'), [
                'ids' => [$ready->id, $multiCycle->id],
            ])
            ->assertOk()
            ->assertJsonPath('published', 1);

        $this->assertSame(QuestionStatus::Published, $ready->fresh()->status);
        $this->assertSame(QuestionStatus::PendingPublish, $multiCycle->fresh()->status);
    }

    public function test_bulk_publish_skips_red_flag(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->pendingPublishReady(cycle: 1, withPendingQa: false);
        $question->forceFill([
            'reviewer_2_flag' => ReviewerFlag::Red->value,
        ])->save();

        $this->actingAsStaff($admin)
            ->postJson(route('admin.questions.bulk-transition'), [
                'ids' => [$question->id],
            ])
            ->assertOk()
            ->assertJsonPath('published', 0);

        $this->assertSame(QuestionStatus::PendingPublish, $question->fresh()->status);
    }

    public function test_editor_cannot_bulk_transition(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $question = $this->pendingPublishReady(cycle: 1, withPendingQa: false);

        $this->actingAsStaff($editor)
            ->postJson(route('admin.questions.bulk-transition'), [
                'ids' => [$question->id],
            ])
            ->assertForbidden();
    }

    public function test_list_shows_bulk_publish_not_bulk_reject(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee('bulkPublish()', false)
            ->assertDontSee('bulkReject()', false)
            ->assertDontSee('Từ chối hàng loạt', false);
    }

    private function pendingPublishReady(int $cycle, bool $withPendingQa): Question
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $instructor = $this->instructorWithSubject();
        $reviewerA = $this->createReviewer();
        $reviewerB = $this->createReviewer();

        $question = Question::factory()->create([
            'stem' => 'Stem QA gate '.$cycle,
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::PendingPublish,
            'created_by' => $editor->id,
            'assigned_instructor_id' => $instructor->id,
            'instructor_id' => $instructor->id,
            'instructor_decision' => 'approved',
            'instructor_review_cycle' => $cycle,
            'reviewer_1_id' => $reviewerA->id,
            'reviewer_1_flag' => ReviewerFlag::Green->value,
            'reviewer_2_id' => $reviewerB->id,
            'reviewer_2_flag' => ReviewerFlag::Green->value,
            'version' => 0,
            'published_version' => null,
            'content_fingerprint' => 'fp-gate-'.$cycle,
        ]);
        $question->lessons()->sync([$this->lesson->id]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            $question->options()->create([
                'label' => $label,
                'content' => $label === 'A' ? 'Correct' : 'Wrong '.$label,
                'is_correct' => $label === 'A',
                'order' => $i + 1,
            ]);
        }

        for ($c = 1; $c <= $cycle; $c++) {
            QuestionWorkflowEvent::query()->create([
                'question_id' => $question->id,
                'review_cycle' => $c,
                'event_type' => QuestionWorkflowEventType::Submit,
                'actor_id' => $editor->id,
                'actor_role' => 'content_editor',
                'outcome' => $withPendingQa
                    ? EditorSubmitOutcome::Pending
                    : ($c < $cycle ? EditorSubmitOutcome::NeedsRework : EditorSubmitOutcome::Confirmed),
                'content_fingerprint' => 'fp-gate-'.$cycle,
                'occurred_at' => now()->subMinutes($cycle - $c + 1),
            ]);
        }

        QuestionInstructorReview::query()->create([
            'question_id' => $question->id,
            'review_cycle' => $cycle,
            'instructor_id' => $instructor->id,
            'decision' => InstructorReviewDecision::Approved,
            'content_fingerprint' => 'fp-gate-'.$cycle,
            'reviewed_at' => now(),
            'outcome' => $withPendingQa
                ? InstructorReviewOutcome::Pending
                : InstructorReviewOutcome::Confirmed,
        ]);

        foreach ([$reviewerA, $reviewerB] as $reviewer) {
            QuestionReviewerFlag::query()->create([
                'question_id' => $question->id,
                'review_cycle' => $cycle,
                'reviewer_id' => $reviewer->id,
                'flag' => ReviewerFlag::Green,
                'content_fingerprint' => 'fp-gate-'.$cycle,
                'reviewed_at' => now(),
                'outcome' => $withPendingQa
                    ? ReviewFlagOutcome::Pending
                    : ReviewFlagOutcome::Confirmed,
            ]);
        }

        return $question->fresh();
    }

    private function instructorWithSubject(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);
        $user->instructorSubjects()->sync([$this->subject->id]);

        return $user;
    }

    private function createReviewer(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Reviewer->value);
        $user->givePermissionTo('question_flag.view');
        $user->givePermissionTo(Permission::QuestionFlag->value);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $user->forgetCachedPermissions();

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }
}
