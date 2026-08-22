<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;
use Tests\TestCase;

final class AdminTopicManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_content_editor_can_view_and_create_a_flat_topic(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $specialty = $this->topic('Nội khoa', 'noi-khoa');

        $this->actingAsStaff($editor)
            ->get(route('admin.topics.index'))
            ->assertOk()
            ->assertSee('Nội khoa')
            ->assertSee('Quản lý chủ đề');

        $response = $this->actingAsStaff($editor)->post(route('admin.topics.store'), [
            'name' => 'Tim mạch',
            'slug' => '',
            'parent_id' => $specialty->id,
            'order' => 2,
        ]);

        $system = Topic::query()->where('slug', 'tim-mach')->firstOrFail();
        $response->assertRedirect(route('admin.topics.edit', $system));
        $this->assertNull($system->parent_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.topic.create']);
    }

    public function test_topic_form_does_not_show_parent_child_controls(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.topics.create'))
            ->assertOk()
            ->assertDontSee('Chủ đề cha')
            ->assertDontSee('Thêm con');
    }

    public function test_topic_assigned_to_a_question_cannot_be_deleted(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $topic = $this->topic('Nội khoa', 'noi-khoa');
        Question::factory()->create(['topic_id' => $topic->id]);

        $this->actingAsStaff($editor)
            ->delete(route('admin.topics.destroy', $topic))
            ->assertSessionHasErrors('topic');

        $this->assertDatabaseHas('topics', ['id' => $topic->id]);
    }

    public function test_unused_topic_can_be_deleted_and_is_audited(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $topic = $this->topic('Nội khoa', 'noi-khoa');

        $this->actingAsStaff($editor)
            ->delete(route('admin.topics.destroy', $topic))
            ->assertRedirect(route('admin.topics.index'));

        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.topic.delete',
            'auditable_id' => (string) $topic->id,
        ]);
    }

    public function test_student_cannot_access_topic_management(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)->get(route('admin.topics.index'))->assertForbidden();
    }

    private function topic(string $name, string $slug): Topic
    {
        return Topic::query()->create([
            'name' => $name,
            'slug' => $slug,
            'type' => 'specialty',
            'parent_id' => null,
            'order' => 0,
        ]);
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
