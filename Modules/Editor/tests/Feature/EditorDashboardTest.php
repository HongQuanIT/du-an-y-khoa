<?php

declare(strict_types=1);

namespace Modules\Editor\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Editor\Http\Controllers\MediaController;
use Modules\Media\Models\Media;
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
        Storage::fake('public');
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
            ->assertOk()
            ->assertDontSee('Câu hỏi của tôi')
            ->assertDontSee(route('editor.questions.create'), false);

        $this->actingAsEditor($editor)
            ->get(route('editor.cms.pages.index'))
            ->assertOk()
            ->assertDontSee(route('editor.questions.index'), false);
    }

    public function test_editor_action_permissions_toggle_the_matching_function(): void
    {
        $editor = $this->editor('Biên tập viên thao tác');
        $role = $editor->roles()->firstOrFail();
        $role->revokePermissionTo('editor_question.create');

        $this->actingAsEditor($editor)
            ->get(route('editor.dashboard'))
            ->assertOk()
            ->assertDontSee(route('editor.questions.create'), false);
        $this->actingAsEditor($editor)
            ->get(route('editor.questions.create'))
            ->assertForbidden();

        $role->givePermissionTo('editor_question.create');

        $this->actingAsEditor($editor)
            ->get(route('editor.questions.create'))
            ->assertOk();
    }

    public function test_editor_update_permission_hides_edit_ui_and_blocks_update(): void
    {
        $editor = $this->editor('Biên tập viên chỉ xem');
        $role = $editor->roles()->firstOrFail();
        $role->revokePermissionTo('editor_question.update');
        $editor->forgetCachedPermissions();
        $question = Question::factory()
            ->for($editor, 'creator')
            ->create(['code' => 'NO-EDIT-01', 'status' => QuestionStatus::Draft]);

        $this->actingAsEditor($editor)
            ->get(route('editor.questions.index'))
            ->assertOk()
            ->assertSee('NO-EDIT-01')
            ->assertDontSee('title="Sửa nội dung câu hỏi"', false);

        $this->actingAsEditor($editor)
            ->get(route('editor.questions.edit', $question))
            ->assertOk()
            ->assertSee('Chi tiết câu hỏi')
            ->assertSee('Nội dung câu hỏi')
            ->assertDontSee('id="admin-question-editor-form"', false)
            ->assertDontSee('name="requested_status"', false)
            ->assertDontSee('Nội dung câu hỏi *');

        $this->actingAsEditor($editor)
            ->put(route('editor.questions.update', $question), [])
            ->assertForbidden();
    }

    public function test_editor_action_requires_its_resource_view_permission(): void
    {
        $editor = $this->editor('Biên tập viên không xem media');
        $role = $editor->roles()->firstOrFail();
        $role->revokePermissionTo('editor_media.view');

        $this->assertTrue($editor->can('editor_media.upload'));
        $this->actingAsEditor($editor)
            ->post(route('editor.media.store'))
            ->assertForbidden();
        $this->actingAsEditor($editor)
            ->get(route('editor.cms.pages.index'))
            ->assertOk()
            ->assertDontSee('>Media<', false);
    }

    public function test_editor_cms_menu_uses_the_first_available_view_permission(): void
    {
        $editor = $this->editor('Biên tập viên FAQ');
        $role = $editor->roles()->firstOrFail();
        $role->revokePermissionTo('editor_page.view');

        $this->actingAsEditor($editor)
            ->get(route('editor.cms.pages.index'))
            ->assertForbidden();
        $this->actingAsEditor($editor)
            ->get(route('editor.cms.faq.index'))
            ->assertOk()
            ->assertSee(route('editor.cms.faq.index'), false);
    }

    public function test_editor_media_uses_its_own_controller_views_and_permissions(): void
    {
        $editor = $this->editor('Biên tập viên Media');

        $this->assertSame(
            MediaController::class.'@index',
            app('router')->getRoutes()->getByName('editor.media.index')?->getActionName(),
        );

        $this->actingAsEditor($editor)
            ->get(route('editor.media.index'))
            ->assertOk()
            ->assertSee('Quản lý ảnh, video và tài nguyên nội dung của Editor.')
            ->assertDontSee('media::admin', false);

        $this->actingAsEditor($editor)
            ->postJson(route('editor.media.store'), [
                'file' => UploadedFile::fake()->image('editor.jpg', 320, 240),
                'alt' => 'Ảnh Editor',
            ])
            ->assertCreated()
            ->assertJsonPath('data.alt', 'Ảnh Editor');

        $media = Media::query()->firstOrFail();
        $this->actingAsEditor($editor)
            ->get(route('editor.media.show', $media))
            ->assertOk()
            ->assertSee('Ảnh Editor');

        $editor->roles()->firstOrFail()->revokePermissionTo('editor_media.update');
        $this->actingAsEditor($editor)
            ->put(route('editor.media.update', $media), ['alt' => 'Không được sửa'])
            ->assertForbidden();
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

    public function test_editor_profile_stays_in_editor_portal_and_respects_profile_permissions(): void
    {
        $editor = $this->editor('Hồ sơ Editor');

        $this->actingAsEditor($editor)
            ->get(route('editor.profile.show'))
            ->assertOk()
            ->assertSee('Hồ sơ biên tập')
            ->assertSee('Biên tập viên')
            ->assertSee(route('editor.profile.update'), false);

        $this->actingAsEditor($editor)
            ->get(route('editor.profile.show', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('Đổi mật khẩu')
            ->assertSee(route('editor.profile.password'), false);

        $this->actingAsEditor($editor)
            ->get(route('profile.show'))
            ->assertRedirect(route('editor.profile.show'));

        $this->actingAsEditor($editor)
            ->put(route('editor.profile.update'), ['name' => 'Tên Editor mới'])
            ->assertRedirect(route('editor.profile.show'));
        $this->assertSame('Tên Editor mới', $editor->fresh()->name);

        $editor->roles()->firstOrFail()->revokePermissionTo('editor_profile.password_update');
        $this->actingAsEditor($editor)
            ->put(route('editor.profile.password'), [
                'current_password' => 'password',
                'password' => 'New-password-123!',
                'password_confirmation' => 'New-password-123!',
            ])
            ->assertForbidden();
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
