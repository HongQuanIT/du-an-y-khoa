<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ContentEditorAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_content_editor_can_resolves_seeded_permissions(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        foreach ([
            Permission::QuestionView,
            Permission::QuestionCreate,
            Permission::QuestionUpdate,
            Permission::CmsManage,
            Permission::MediaView,
            Permission::TopicView,
            Permission::ExamManage,
            Permission::LibraryView,
        ] as $permission) {
            $this->assertTrue(
                $editor->can($permission->value),
                "content_editor phải can({$permission->value})",
            );
        }

        $this->assertFalse($editor->can(Permission::UserView->value));
        $this->assertFalse($editor->can(Permission::QuestionPublish->value));
    }

    public function test_content_editor_can_open_content_modules(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)->get(route('admin.dashboard'))->assertOk();
        $this->actingAsStaff($editor)->get(route('admin.questions.index'))->assertOk();
        $this->actingAsStaff($editor)->get(route('admin.questions.create'))->assertOk();
        $this->actingAsStaff($editor)->get(route('admin.cms.pages.index'))->assertOk();
        $this->actingAsStaff($editor)->get(route('admin.media.index'))->assertOk();
        $this->actingAsStaff($editor)->get(route('admin.taxonomy.index'))->assertOk();
        $this->actingAsStaff($editor)->get(route('admin.exams.index'))->assertOk();
    }

    public function test_content_editor_is_forbidden_on_admin_only_modules(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAsStaff($editor)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_reseeding_permissions_keeps_content_editor_access(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->assertTrue($editor->can(Permission::QuestionView->value));

        $this->seed(RolePermissionSeeder::class);
        $editor->unsetRelation('roles');
        $editor->unsetRelation('permissions');

        $this->assertTrue($editor->fresh()->can(Permission::QuestionView->value));
        $this->actingAsStaff($editor)->get(route('admin.questions.index'))->assertOk();
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
