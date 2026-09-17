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
use Modules\QuestionBank\Actions\FlagQuestionReviewAction;
use Modules\QuestionBank\Actions\InstructorReviewQuestionAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Subject;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionThreeLayerWorkflowTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private Subject $subject;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->subject = $this->makeSubject(['name' => 'Nội khoa 3 lớp', 'slug' => 'noi-khoa-3-lop']);
        $this->lesson = $this->makeLesson([
            'name' => 'Suy tim 3 lớp',
            'slug' => 'suy-tim-3-lop',
            'subjects' => [$this->subject],
        ]);
    }

    public function test_reviewer_has_flag_but_not_publish_or_edit(): void
    {
        $reviewer = $this->createReviewer();

        $this->assertTrue($reviewer->can(Permission::QuestionFlag->value));
        $this->assertFalse($reviewer->can(Permission::QuestionView->value));
        $this->assertFalse($reviewer->can(Permission::QuestionCreate->value));
        $this->assertFalse($reviewer->can(Permission::QuestionUpdate->value));
        $this->assertFalse($reviewer->can(Permission::QuestionPublish->value));
        $this->assertFalse($reviewer->can(Permission::QuestionReview->value));
    }

    public function test_submit_requires_assigned_instructor_matching_subject(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $instructor = $this->instructorWithSubject();
        $question = $this->makeQuestion(QuestionStatus::Draft, [
            'created_by' => $editor->id,
        ]);

        $this->actingAsStaff($editor)
            ->from(route('admin.questions.edit', $question))
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::InReview->value,
            ])
            ->assertSessionHasErrors('assigned_instructor_id');

        $question->forceFill(['assigned_instructor_id' => $instructor->id])->save();

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::InReview->value,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::InReview, $question->fresh()->status);
    }

    public function test_eligible_instructors_filtered_by_lesson_subjects(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $match = $this->instructorWithSubject($this->subject);
        $otherSubject = $this->makeSubject(['name' => 'Ngoại lọc GV', 'slug' => 'ngoai-loc-gv']);
        $mismatch = $this->instructorWithSubject($otherSubject);

        $this->actingAsStaff($editor)
            ->getJson(route('admin.questions.eligible-instructors', [
                'lesson_ids' => [$this->lesson->id],
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id, 'name' => $match->name])
            ->assertJsonMissing(['id' => $mismatch->id]);

        $this->actingAsStaff($editor)
            ->getJson(route('admin.questions.eligible-instructors'))
            ->assertOk()
            ->assertJsonPath('instructors', []);
    }

    public function test_create_form_omits_inferred_curriculum_heading(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.create'))
            ->assertOk()
            ->assertDontSee('Suy ra từ bài học đã chọn', false)
            ->assertSee('Giảng viên chuyên môn', false);
    }

    public function test_mismatched_subject_instructor_cannot_be_submitted(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $otherSubject = $this->makeSubject(['name' => 'Ngoại', 'slug' => 'ngoai-3-lop']);
        $wrong = $this->instructorWithSubject($otherSubject);
        $question = $this->makeQuestion(QuestionStatus::Draft, [
            'created_by' => $editor->id,
            'assigned_instructor_id' => $wrong->id,
        ]);

        $this->actingAsStaff($editor)
            ->from(route('admin.questions.edit', $question))
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::InReview->value,
            ])
            ->assertSessionHasErrors('assigned_instructor_id');
    }

    public function test_happy_path_instructor_then_two_flags_then_publish(): void
    {
        $instructor = $this->instructorWithSubject();
        $reviewerA = $this->createReviewer();
        $reviewerB = $this->createReviewer();
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeQuestion(QuestionStatus::InReview, [
            'assigned_instructor_id' => $instructor->id,
            'instructor_review_cycle' => 1,
        ]);

        app(InstructorReviewQuestionAction::class)->approve($instructor, $question);
        $this->assertSame(QuestionStatus::InFlagReview, $question->fresh()->status);

        $this->actingAsStaff($reviewerA)
            ->get(route('admin.questions.flags.index'))
            ->assertOk()
            ->assertSee($question->code);

        app(FlagQuestionReviewAction::class)->handle($reviewerA, $question->fresh(), ReviewerFlag::Green, 'OK');
        $this->assertSame(QuestionStatus::InFlagReview, $question->fresh()->status);

        app(FlagQuestionReviewAction::class)->handle($reviewerB, $question->fresh(), ReviewerFlag::Yellow, 'Lưu ý nhỏ');
        $question->refresh();
        $this->assertSame(QuestionStatus::PendingPublish, $question->status);
        $this->assertTrue($question->hasYellowReviewerFlag());

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::Published, $question->fresh()->status);
        $this->assertSame(1, (int) $question->fresh()->version);
    }

    public function test_red_flag_blocks_publish_but_allows_return(): void
    {
        $instructor = $this->instructorWithSubject();
        $reviewerA = $this->createReviewer();
        $reviewerB = $this->createReviewer();
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeQuestion(QuestionStatus::InReview, [
            'assigned_instructor_id' => $instructor->id,
            'instructor_review_cycle' => 1,
        ]);

        app(InstructorReviewQuestionAction::class)->approve($instructor, $question);
        app(FlagQuestionReviewAction::class)->handle($reviewerA, $question->fresh(), ReviewerFlag::Green);
        app(FlagQuestionReviewAction::class)->handle($reviewerB, $question->fresh(), ReviewerFlag::Red, 'Sai kiến thức');

        $this->actingAsStaff($admin)
            ->from(route('admin.questions.edit', $question->fresh()))
            ->post(route('admin.questions.transition', $question->fresh()), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(QuestionStatus::PendingPublish, $question->fresh()->status);

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question->fresh()), [
                'status' => QuestionStatus::Rejected->value,
                'rejection_reason' => 'Có cờ đỏ, trả về biên tập.',
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::Rejected, $question->fresh()->status);
    }

    public function test_reviewer_cannot_see_question_before_instructor_approval(): void
    {
        $reviewer = $this->createReviewer();
        $question = $this->makeQuestion(QuestionStatus::InReview);

        $this->actingAsStaff($reviewer)
            ->get(route('admin.questions.flags.index'))
            ->assertOk()
            ->assertDontSee($question->code);

        $this->actingAsStaff($reviewer)
            ->get(route('admin.questions.edit', $question))
            ->assertForbidden();
    }

    public function test_reviewer_without_question_view_cannot_open_question_workspace(): void
    {
        $reviewer = $this->createReviewer();
        $instructor = $this->instructorWithSubject();
        $question = $this->makeQuestion(QuestionStatus::InReview, [
            'assigned_instructor_id' => $instructor->id,
            'instructor_review_cycle' => 1,
        ]);
        app(InstructorReviewQuestionAction::class)->approve($instructor, $question);

        $this->assertFalse($reviewer->can(Permission::QuestionView->value));

        $this->actingAsStaff($reviewer)
            ->get(route('admin.questions.index'))
            ->assertForbidden();

        $this->actingAsStaff($reviewer)
            ->get(route('admin.questions.edit', $question->fresh()))
            ->assertForbidden();

        $this->actingAsStaff($reviewer)
            ->get(route('admin.questions.flags.index'))
            ->assertOk()
            ->assertSee('Review câu hỏi', false)
            ->assertSee('Bài học', false)
            ->assertSee('Độ khó', false)
            ->assertSee($this->lesson->name, false)
            ->assertSee('Trung bình', false)
            ->assertDontSee('Reviewer 1', false)
            ->assertDontSee('2 reviewer đầu tiên', false);
    }

    public function test_reviewer_flag_confirmation_does_not_reveal_slot_order(): void
    {
        $instructor = $this->instructorWithSubject();
        $reviewerA = $this->createReviewer();
        $reviewerB = $this->createReviewer();
        $question = $this->makeQuestion(QuestionStatus::InReview, [
            'assigned_instructor_id' => $instructor->id,
            'instructor_review_cycle' => 1,
        ]);
        app(InstructorReviewQuestionAction::class)->approve($instructor, $question);

        $this->actingAsStaff($reviewerA)
            ->post(route('admin.questions.flags.store', $question->fresh()), [
                'flag' => ReviewerFlag::Green->value,
            ])
            ->assertRedirect(route('admin.questions.flags.index', ['tab' => 'done']))
            ->assertSessionHas('status', 'Đã ghi nhận cờ của bạn.');

        $this->actingAsStaff($reviewerB)
            ->get(route('admin.questions.flags.show', $question->fresh()))
            ->assertOk()
            ->assertDontSee('Reviewer 1', false)
            ->assertDontSee($reviewerA->name, false);

        $this->actingAsStaff($reviewerB)
            ->post(route('admin.questions.flags.store', $question->fresh()), [
                'flag' => ReviewerFlag::Yellow->value,
            ])
            ->assertRedirect(route('admin.questions.flags.index', ['tab' => 'done']))
            ->assertSessionHas('status', 'Đã ghi nhận cờ của bạn.');
    }

    public function test_reviewer_flag_show_uses_learner_layout_with_hints_and_explanations(): void
    {
        $instructor = $this->instructorWithSubject();
        $reviewer = $this->createReviewer();
        $question = $this->makeQuestion(QuestionStatus::InReview, [
            'assigned_instructor_id' => $instructor->id,
            'instructor_review_cycle' => 1,
            'stem' => '<p>Bệnh nhân đau ngực kiểu mạch vành, vã mồ hôi.</p>',
            'attending_tip' => '<p>STEMI trước cần PCI cấp cứu khi có chỉ định.</p>',
            'key_info' => ['đau ngực kiểu mạch vành'],
        ]);
        $question->hints()->create([
            'content' => 'Đau ngực + vã mồ hôi gợi ý hội chứng vành cấp.',
            'sort_order' => 1,
            'status' => 'active',
        ]);
        $question->options->first()?->forceFill([
            'explanation' => '<p>Đây là ACS vì đau ngực kiểu mạch vành.</p>',
        ])->save();
        $question->options->skip(1)->first()?->forceFill([
            'explanation' => '<p>GERD không kèm vã mồ hôi và đau lan.</p>',
        ])->save();

        app(InstructorReviewQuestionAction::class)->approve($instructor, $question);

        $this->actingAsStaff($reviewer)
            ->get(route('admin.questions.flags.show', $question->fresh()))
            ->assertOk()
            ->assertSee('reviewer-question-preview', false)
            ->assertSee('Gợi ý', false)
            ->assertSee('Kiến thức', false)
            ->assertSee('Xem như học viên', false)
            ->assertSee('Đau ngực + vã mồ hôi gợi ý hội chứng vành cấp.', false)
            ->assertSee('STEMI trước cần PCI cấp cứu khi có chỉ định.', false)
            ->assertSee('Đây là ACS vì đau ngực kiểu mạch vành.', false)
            ->assertSee('GERD không kèm vã mồ hôi và đau lan.', false)
            ->assertSee('Đáp án đúng', false)
            ->assertSee('Vì sao sai', false)
            ->assertSee('data-key-info', false);
    }

    public function test_legacy_two_instructor_slots_still_publishable(): void
    {
        $first = $this->instructorWithSubject();
        $second = $this->instructorWithSubject();
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeQuestion(QuestionStatus::PendingPublish, [
            'instructor_id' => $second->id,
            'instructor_review_cycle' => 1,
            'instructor_1_id' => $first->id,
            'instructor_1_decision' => 'approved',
            'instructor_2_id' => $second->id,
            'instructor_2_decision' => 'approved',
        ]);

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::Published, $question->fresh()->status);
    }

    private function instructorWithSubject(?Subject $subject = null): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);
        $user->instructorSubjects()->sync([($subject ?? $this->subject)->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeQuestion(QuestionStatus $status, array $overrides = []): Question
    {
        $creator = User::factory()->create();
        $creator->assignRole(Role::ContentEditor->value);

        $question = Question::factory()->create(array_merge([
            'stem' => 'Stem workflow 3 lớp',
            'explanation' => null,
            'difficulty' => Difficulty::Medium,
            'status' => $status,
            'created_by' => $creator->id,
            'version' => 0,
        ], $overrides));

        $question->lessons()->sync([$this->lesson->id]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            $question->options()->create([
                'label' => $label,
                'content' => "Option {$label}",
                'is_correct' => $i === 0,
                'order' => $i + 1,
            ]);
        }

        return $question->fresh(['options', 'lessons']);
    }

    /** Reviewer role: portal admin + question.flag, không có question.view / publish / edit. */
    private function createReviewer(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Reviewer->value);
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
