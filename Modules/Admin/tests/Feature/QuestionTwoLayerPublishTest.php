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
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionTwoLayerPublishTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private MedicalTaxonomyNode $topic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->topic = $this->makeMedicalNode([
            'name' => 'Tim mạch workflow',
            'slug' => 'tim-mach-workflow',
            'node_type' => 'specialty',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_role_cannot_edit_or_create_questions(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->assertFalse($admin->can(Permission::QuestionUpdate->value));
        $this->assertFalse($admin->can(Permission::QuestionCreate->value));
        $this->assertTrue($admin->can(Permission::QuestionPublish->value));
        $this->assertTrue($admin->can(Permission::QuestionDelete->value));

        $this->actingAsStaff($admin)
            ->get(route('admin.questions.create'))
            ->assertForbidden();
    }

    public function test_super_admin_cannot_edit_or_create_question_content(): void
    {
        $superAdmin = $this->staffUser(Role::SuperAdmin);
        $editor = User::factory()->create();
        $editor->assignRole(Role::ContentEditor->value);
        $question = $this->makeQuestion(QuestionStatus::Draft, [
            'created_by' => $editor->id,
        ]);

        $this->assertFalse($superAdmin->can(Permission::QuestionUpdate->value));
        $this->assertFalse($superAdmin->can(Permission::QuestionCreate->value));
        $this->assertTrue($superAdmin->can(Permission::QuestionPublish->value));
        $this->assertTrue($superAdmin->can(Permission::QuestionDelete->value));

        $this->actingAsStaff($superAdmin)
            ->get(route('admin.questions.create'))
            ->assertForbidden();

        $this->actingAsStaff($superAdmin)
            ->get(route('admin.questions.edit', $question))
            ->assertOk()
            ->assertSee('Chi tiết câu hỏi', false)
            ->assertDontSee('Lưu thay đổi', false);

        $this->actingAsStaff($superAdmin)
            ->put(route('admin.questions.update', $question), [
                'stem' => 'Super Admin không được sửa nội dung',
                'difficulty' => Difficulty::Medium->value,
                'medical_taxonomy_node_ids' => [$this->topic->id],
                'is_free' => '0',
                'options' => [
                    ['content' => 'A', 'is_correct' => '1'],
                    ['content' => 'B', 'is_correct' => '0'],
                ],
            ])
            ->assertForbidden();

        $this->actingAsStaff($superAdmin)
            ->from(route('admin.questions.edit', $question))
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(QuestionStatus::Draft, $question->fresh()->status);
    }

    public function test_admin_cannot_publish_before_instructor_approval(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeQuestion(QuestionStatus::InReview);

        $this->actingAsStaff($admin)
            ->from(route('admin.questions.edit', $question))
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(QuestionStatus::InReview, $question->fresh()->status);
    }

    public function test_admin_can_publish_after_instructor_approval_as_second_person(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole(Role::Instructor->value);

        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeQuestion(QuestionStatus::PendingPublish, [
            'instructor_id' => $instructor->id,
            'version' => 0,
        ]);

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect();

        $question->refresh();
        $this->assertSame(QuestionStatus::Published, $question->status);
        $this->assertSame(1, (int) $question->version);
        $this->assertSame($admin->id, (int) $question->publisher_id);
        $this->assertNotSame($question->instructor_id, $question->publisher_id);
        $this->assertDatabaseHas('question_versions', [
            'question_id' => $question->id,
            'version' => 1,
        ]);
        $this->assertDatabaseMissing('question_versions', [
            'question_id' => $question->id,
            'version' => 0,
        ]);
    }

    public function test_same_person_cannot_be_both_instructor_and_publisher(): void
    {
        $user = $this->staffUser(Role::SuperAdmin);
        // SuperAdmin has publish via Gate::before; simulate same person as instructor.
        $question = $this->makeQuestion(QuestionStatus::PendingPublish, [
            'instructor_id' => $user->id,
            'version' => 0,
        ]);

        $this->actingAsStaff($user)
            ->from(route('admin.questions.edit', $question))
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect(route('admin.questions.edit', $question))
            ->assertSessionHasErrors('status');

        $this->assertSame(QuestionStatus::PendingPublish, $question->fresh()->status);
    }

    private function makeQuestion(QuestionStatus $status, array $overrides = []): Question
    {
        $creator = User::factory()->create();
        $creator->assignRole(Role::ContentEditor->value);

        $question = Question::factory()->create(array_merge([
            'stem' => 'Stem workflow publish test',
            'explanation' => null,
            'difficulty' => Difficulty::Medium,
            'status' => $status,
            'created_by' => $creator->id,
            'version' => 0,
        ], $overrides));

        $question->medicalTaxonomyNodes()->sync([$this->topic->id]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            $question->options()->create([
                'label' => $label,
                'content' => "Option {$label}",
                'is_correct' => $i === 0,
                'order' => $i + 1,
            ]);
        }

        return $question->fresh(['options']);
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
