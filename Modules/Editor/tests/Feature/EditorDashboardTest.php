<?php

declare(strict_types=1);

namespace Modules\Editor\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Tests\TestCase;

final class EditorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_content_editor_uses_a_dedicated_portal_and_only_sees_own_questions(): void
    {
        $editor = $this->editor('Biên tập viên chính');
        $other = $this->editor('Biên tập viên khác');
        Question::factory()->for($editor, 'creator')->create(['code' => 'EDITOR-OWN', 'status' => QuestionStatus::Draft]);
        Question::factory()->for($other, 'creator')->create(['code' => 'EDITOR-OTHER']);

        $this->actingAsEditor($editor)
            ->get(route('editor.dashboard'))
            ->assertOk()
            ->assertSee('Bảng điều khiển biên tập')
            ->assertSee('EDITOR-OWN')
            ->assertDontSee('EDITOR-OTHER')
            ->assertSee('Nhịp độ biên tập')
            ->assertSee('Cần tiếp tục biên tập')
            ->assertSee('Cần chỉnh sửa')
            ->assertSee('dashboard-charts.js');

        $this->actingAsEditor($editor)
            ->get(route('editor.questions.create'))
            ->assertOk()
            ->assertSee('Tạo câu hỏi mới');

        $this->actingAsEditor($editor)
            ->getJson(route('editor.questions.eligible-instructors'))
            ->assertOk()
            ->assertJsonStructure(['instructors']);

        $this->actingAsEditor($editor)->get(route('editor.blueprints.index'))->assertOk();
        $this->actingAsEditor($editor)->get(route('editor.curriculum.index'))->assertOk();
        $this->actingAsEditor($editor)->get(route('editor.tags.index'))->assertOk();
        $this->actingAsEditor($editor)->get('/admin/categories?tab=subjects')->assertForbidden();
        $this->actingAsEditor($editor)->get('/admin/cms/pages')->assertForbidden();
        $this->actingAsEditor($editor)->get('/admin/media/items')->assertForbidden();
        $this->actingAsEditor($editor)->get('/admin/cms/faq')->assertForbidden();
        $this->actingAsEditor($editor)->get('/admin/cms/banners')->assertForbidden();
        $this->actingAsEditor($editor)->get('/admin/cms/menus')->assertForbidden();
        $this->assertTrue($editor->can('editor_media.update'));
        $this->assertTrue($editor->can('editor_media.upload'));
        $this->assertFalse($editor->hasDirectPermission('media.update'));

        $this->actingAsEditor($editor)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAsEditor($editor)->get(route('admin.questions.create'))->assertForbidden();
    }

    public function test_non_editor_cannot_access_editor_portal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($admin)->get(route('editor.dashboard'))->assertForbidden();
    }

    public function test_editor_routes_are_controlled_by_editor_permissions(): void
    {
        $editor = $this->editor('Biên tập viên giới hạn');
        $role = $editor->roles()->firstOrFail();
        $role->revokePermissionTo('editor_question.view');

        $this->actingAsEditor($editor)
            ->get(route('editor.questions.index'))
            ->assertForbidden();

        $this->actingAsEditor($editor)
            ->get(route('editor.dashboard'))
            ->assertOk();
    }

    public function test_editor_role_does_not_store_admin_content_permissions(): void
    {
        $editor = $this->editor('Biên tập viên độc lập');

        $this->assertTrue($editor->hasPermissionTo('editor_question.view'));
        $this->assertTrue($editor->hasPermissionTo('editor_page.update'));
        $this->assertTrue($editor->hasPermissionTo('editor_media.upload'));
        $this->assertFalse($editor->hasPermissionTo('question.view'));
        $this->assertFalse($editor->hasPermissionTo('cms.update'));
        $this->assertFalse($editor->hasPermissionTo('media.upload'));
    }

    private function editor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole(Role::ContentEditor->value);
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsEditor(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorSession::KEY => now()->timestamp]);
    }
}
