<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Admin\Models\AuditLog;
use Modules\Auth\Enums\AuthenticationMethod;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Notifications\ResetPasswordNotification;
use Modules\Auth\Services\TotpService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class AdminPhase1ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_list_and_view_users(): void
    {
        $admin = $this->staffUser(Role::SuperAdmin);
        $student = User::factory()->create([
            'email' => 'learner@example.com',
            'avatar_path' => 'avatars/learner.webp',
        ]);
        $student->assignRole(Role::Student->value);
        $student->learnerProfile()->create([
            'registration_method' => 'google',
            'onboarding_completed_at' => now(),
            'marketing_consent_at' => now(),
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'medical-students-2026',
            'utm_content' => 'registration-banner',
            'referrer_url' => 'https://example.com/referrer',
            'landing_page' => 'https://example.com/register',
        ]);
        $student->forceFill([
            'last_login_method' => AuthenticationMethod::Google,
            'last_login_at' => now(),
        ])->save();
        $student->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'admin-view-google-123',
            'provider_email' => $student->email,
            'last_login_at' => now(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Đăng nhập gần nhất')
            ->assertSee('Google')
            ->assertSee('Ảnh đại diện của '.$student->name)
            ->assertSee('/storage/avatars/learner.webp', false)
            ->assertSee('learner@example.com');

        $this->actingAsStaff($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('Chi tiết học viên')
            ->assertSee('Điều hướng chi tiết học viên')
            ->assertSee('Gửi email')
            ->assertSee($student->name)
            ->assertSee('Phương thức đăng nhập gần nhất')
            ->assertSee('Phương thức đăng nhập')
            ->assertSee('Họ và tên')
            ->assertSee('Email đăng nhập')
            ->assertSee('Ảnh đại diện của '.$student->name)
            ->assertSee('Email/password')
            ->assertSee('Đã thiết lập')
            ->assertSee('Google')
            ->assertSee('Đã liên kết')
            ->assertSee('Facebook')
            ->assertSee('Chưa liên kết')
            ->assertSee('Hoàn tất hồ sơ lúc')
            ->assertDontSee('Thông tin nguồn đăng ký')
            ->assertSee('learner@example.com');
    }

    public function test_admin_can_filter_users_by_multiple_status_and_role(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $activeStudent = User::factory()->create(['email' => 'active-student@example.com']);
        $activeStudent->assignRole(Role::Student->value);
        $suspendedStudent = User::factory()->create([
            'email' => 'suspended-student@example.com',
            'status' => UserStatus::Suspended,
        ]);
        $suspendedStudent->assignRole(Role::Student->value);
        $editor = User::factory()->create(['email' => 'editor-user@example.com']);
        $editor->assignRole(Role::ContentEditor->value);

        $this->actingAsStaff($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('portal-filter-trigger', false)
            ->assertSee('role-filter-trigger', false)
            ->assertSee('status-filter-trigger', false)
            ->assertSee('active-student@example.com')
            ->assertSee('suspended-student@example.com')
            ->assertSee('editor-user@example.com');

        $this->actingAsStaff($admin)
            ->get(route('admin.users.index', [
                'status' => [UserStatus::Active->value, UserStatus::Suspended->value],
                'role' => [Role::Student->value],
            ]))
            ->assertOk()
            ->assertSee('active-student@example.com')
            ->assertSee('suspended-student@example.com')
            ->assertDontSee('editor-user@example.com');
    }

    public function test_admin_can_change_student_role_and_status(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->patch(route('admin.users.role', $student), [
                'portal' => PortalGroup::Admin->value,
                'role' => Role::ContentEditor->value,
            ])
            ->assertRedirect();

        $this->assertTrue($student->fresh()->hasRole(Role::ContentEditor->value));

        $this->actingAsStaff($admin)
            ->patch(route('admin.users.status', $student), [
                'status' => UserStatus::Suspended->value,
                'reason' => 'Vi phạm',
            ])
            ->assertRedirect();

        $this->assertSame(UserStatus::Suspended, $student->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.status_change',
            'auditable_id' => $student->id,
        ]);
    }

    public function test_admin_can_soft_delete_a_manageable_user(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->delete(route('admin.users.destroy', $student))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.delete',
            'actor_id' => $admin->id,
            'auditable_id' => (string) $student->id,
        ]);
    }

    public function test_admin_cannot_assign_super_admin_role(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->patch(route('admin.users.role', $student), [
                'portal' => PortalGroup::Admin->value,
                'role' => Role::SuperAdmin->value,
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_content_editor_cannot_access_users(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_sync_role_permissions(): void
    {
        $super = $this->staffUser(Role::SuperAdmin);
        $role = \Spatie\Permission\Models\Role::findByName(Role::ContentEditor->value, 'web');
        $permission = Permission::findByName('cms.update', 'web');

        $this->actingAsStaff($super)
            ->put(route('admin.roles.permissions', $role), [
                'permissions' => [$permission->id],
            ])
            ->assertRedirect();

        $this->assertTrue($role->fresh()->hasPermissionTo('cms.update'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.role.permission_change',
        ]);
    }

    public function test_super_admin_matrix_lists_learner_learning_tools_read_only(): void
    {
        $super = $this->staffUser(Role::SuperAdmin);
        $role = \Spatie\Permission\Models\Role::findByName(Role::SuperAdmin->value, 'web');

        $this->actingAsStaff($super)
            ->get(route('admin.roles.show', $role))
            ->assertOk()
            ->assertSee('Super Admin luôn có toàn bộ quyền')
            ->assertSee('Công cụ học tập')
            ->assertSee('learning_tool.note', false)
            ->assertSee('learning_tool.flag', false)
            ->assertSee('learning_tool.highlight', false)
            ->assertSee('learning_tool.research', false)
            ->assertDontSee('Lưu ma trận quyền', false);
    }

    public function test_super_admin_can_create_custom_role_with_existing_permissions(): void
    {
        $super = $this->staffUser(Role::SuperAdmin);
        $permissions = Permission::query()
            ->whereIn('name', ['question.update', 'cms.update'])
            ->pluck('id')
            ->all();

        $this->actingAsStaff($super)
            ->post(route('admin.roles.store'), [
                'portal' => PortalGroup::Admin->value,
                'name' => 'medical_reviewer',
                'display_name' => 'Người duyệt nội dung y khoa',
                'permissions' => $permissions,
            ])
            ->assertRedirect();

        $role = \Spatie\Permission\Models\Role::findByName('medical_reviewer', 'web');
        $this->assertTrue($role->hasPermissionTo('question.update'));
        $this->assertTrue($role->hasPermissionTo('cms.update'));
        $this->assertSame(PortalGroup::Admin->value, $role->portal);
        $this->assertSame('Người duyệt nội dung y khoa', $role->display_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.role.created']);

        $this->actingAsStaff($super)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Người duyệt nội dung y khoa');
    }

    public function test_admin_can_create_custom_role(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->post(route('admin.roles.store'), [
                'portal' => PortalGroup::Admin->value,
                'name' => 'admin_assistant',
                'display_name' => 'Trợ lý quản trị',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'admin_assistant', 'guard_name' => 'web']);
    }

    public function test_custom_role_name_is_normalized_before_validation(): void
    {
        $super = $this->staffUser(Role::SuperAdmin);

        $this->actingAsStaff($super)
            ->post(route('admin.roles.store'), [
                'portal' => PortalGroup::Admin->value,
                'name' => 'Người nhập liệu',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'nguoi_nhap_lieu',
            'guard_name' => 'web',
            'portal' => PortalGroup::Admin->value,
            'display_name' => 'Người nhập liệu',
        ]);
    }

    public function test_super_admin_cannot_create_role_with_permission_from_another_portal(): void
    {
        $super = $this->staffUser(Role::SuperAdmin);
        $adminPermission = Permission::findByName('cms.update', 'web');

        $this->actingAsStaff($super)
            ->from(route('admin.roles.create'))
            ->post(route('admin.roles.store'), [
                'portal' => PortalGroup::Learner->value,
                'name' => 'learner_reviewer',
                'permissions' => [$adminPermission->id],
            ])
            ->assertRedirect(route('admin.roles.create'))
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('roles', ['name' => 'learner_reviewer']);
    }

    public function test_admin_can_sync_role_permissions(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $role = \Spatie\Permission\Models\Role::findByName(Role::ContentEditor->value, 'web');
        $permission = Permission::findByName('question.view_any', 'web');

        $this->actingAsStaff($admin)
            ->put(route('admin.roles.permissions', $role), [
                'permissions' => [$permission->id],
            ])
            ->assertRedirect();

        $this->assertTrue($role->fresh()->hasPermissionTo('question.view_any'));
    }

    public function test_admin_cannot_sync_super_admin_or_admin_role_permissions(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $superAdminRole = \Spatie\Permission\Models\Role::findByName(Role::SuperAdmin->value, 'web');
        $adminRole = \Spatie\Permission\Models\Role::findByName(Role::Admin->value, 'web');
        $permission = Permission::findByName('question.view_any', 'web');

        $this->actingAsStaff($admin)
            ->put(route('admin.roles.permissions', $superAdminRole), [
                'permissions' => [$permission->id],
            ])
            ->assertForbidden();

        $this->actingAsStaff($admin)
            ->put(route('admin.roles.permissions', $adminRole), [
                'permissions' => [$permission->id],
            ])
            ->assertForbidden();
    }

    public function test_audit_index_and_show(): void
    {
        $super = $this->staffUser(Role::SuperAdmin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($super)
            ->patch(route('admin.users.status', $student), [
                'status' => UserStatus::Banned->value,
            ]);

        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);

        $this->actingAsStaff($super)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('admin.user.status_change');

        $this->actingAsStaff($super)
            ->get(route('admin.audit.show', $log))
            ->assertOk()
            ->assertSee('admin.user.status_change');
    }

    public function test_password_reset_notification_is_sent(): void
    {
        Notification::fake();

        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->post(route('admin.users.reset-password', $student))
            ->assertRedirect();

        Notification::assertSentTo($student, ResetPasswordNotification::class);
    }

    public function test_admin_can_filter_users_by_2fa_status(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $userWith2fa = User::factory()->create(['email' => 'has2fa@example.com']);
        $userWith2fa->assignRole(Role::Student->value);
        $this->enrollTwoFactor($userWith2fa);

        $userWithout2fa = User::factory()->create(['email' => 'no2fa@example.com']);
        $userWithout2fa->assignRole(Role::Student->value);

        // Lọc đã bật 2FA
        $this->actingAsStaff($admin)
            ->get(route('admin.users.index', ['two_factor' => 'enabled']))
            ->assertOk()
            ->assertSee('has2fa@example.com')
            ->assertDontSee('no2fa@example.com');

        // Lọc chưa bật 2FA
        $this->actingAsStaff($admin)
            ->get(route('admin.users.index', ['two_factor' => 'disabled']))
            ->assertOk()
            ->assertSee('no2fa@example.com')
            ->assertDontSee('has2fa@example.com');
    }

    public function test_admin_can_reset_user_2fa_from_detail_view(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create(['email' => 'student-reset2fa@example.com']);
        $student->assignRole(Role::Student->value);
        $this->enrollTwoFactor($student);

        $this->assertTrue($student->fresh()->hasTwoFactorEnabled());

        // Xem trang chi tiết
        $this->actingAsStaff($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('Đặt lại / Tắt 2FA');

        // Thực hiện reset 2FA
        $this->actingAsStaff($admin)
            ->post(route('admin.users.reset-2fa', $student))
            ->assertRedirect();

        $this->assertFalse($student->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.two_factor_reset',
            'actor_id' => $admin->id,
            'auditable_id' => (string) $student->id,
        ]);
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
