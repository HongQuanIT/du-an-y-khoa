<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role as RoleEnum;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Support\PermissionCatalog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class EditorRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_data_entry_users_are_moved_to_the_single_editor_role(): void
    {
        $oldRole = Role::create([
            'name' => 'nguoi_nhap_lieu',
            'guard_name' => 'web',
            'display_name' => 'Người nhập liệu',
            'portal' => PortalGroup::Admin->value,
        ]);
        $user = User::factory()->create();
        $user->assignRole($oldRole);

        $this->migration()->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDatabaseMissing('roles', [
            'name' => 'nguoi_nhap_lieu',
            'guard_name' => 'web',
        ]);
        $this->assertTrue($user->fresh()->hasRole(RoleEnum::ContentEditor->value));

        $editorRole = Role::findByName(RoleEnum::ContentEditor->value, 'web');
        $this->assertSame(PortalGroup::Editor->value, $editorRole->portal);
        $this->assertSame('Biên tập viên nội dung', $editorRole->display_name);
    }

    public function test_migration_is_idempotent_and_role_catalog_has_only_content_editor_in_editor_portal(): void
    {
        $this->migration()->up();
        $this->migration()->up();
        $this->seed(RolePermissionSeeder::class);

        $roles = Role::query()->where('guard_name', 'web')->get();
        $groups = PermissionCatalog::rolesGroupedByPortal($roles);
        $editorRoleNames = collect($groups[PortalGroup::Editor->value]['roles'])->pluck('name')->all();
        $adminRoleNames = collect($groups[PortalGroup::Admin->value]['roles'])->pluck('name')->all();

        $this->assertSame([RoleEnum::ContentEditor->value], $editorRoleNames);
        $this->assertNotContains('nguoi_nhap_lieu', $adminRoleNames);
        $this->assertDatabaseMissing('roles', ['name' => 'nguoi_nhap_lieu']);
        $this->assertSame(1, Role::query()
            ->where('guard_name', 'web')
            ->where('name', RoleEnum::ContentEditor->value)
            ->count());
    }

    public function test_content_editor_keeps_editor_capabilities_without_publish_or_admin_permissions(): void
    {
        $role = Role::findByName(RoleEnum::ContentEditor->value, 'web');

        $this->assertTrue($role->hasPermissionTo('editor_question.create'));
        $this->assertTrue($role->hasPermissionTo('editor_question.submit'));
        $this->assertTrue($role->hasPermissionTo('editor_page.update'));
        $this->assertTrue($role->hasPermissionTo('editor_media.upload'));
        $this->assertFalse($role->hasPermissionTo('question.create'));
        $this->assertFalse($role->hasPermissionTo('cms.update'));
        $this->assertFalse($role->hasPermissionTo('media.upload'));
        $this->assertFalse($role->hasPermissionTo('question.publish'));
        $this->assertFalse($role->hasPermissionTo('question.review'));
        $this->assertFalse($role->hasPermissionTo('question.adjudicate'));
        $this->assertFalse($role->hasPermissionTo('user.role_assign'));
        $this->assertFalse($role->hasPermissionTo('audit_log.view'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_23_000002_remove_data_entry_role_from_admin.php');
    }
}
