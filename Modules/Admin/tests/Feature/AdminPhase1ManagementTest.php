<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
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
        $student = User::factory()->create(['email' => 'learner@example.com']);
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('learner@example.com');

        $this->actingAsStaff($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee($student->name);
    }

    public function test_admin_can_change_student_role_and_status(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->patch(route('admin.users.role', $student), ['role' => Role::ContentEditor->value])
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

    public function test_admin_cannot_assign_super_admin_role(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAsStaff($admin)
            ->patch(route('admin.users.role', $student), ['role' => Role::SuperAdmin->value])
            ->assertForbidden();
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
        $permission = \Spatie\Permission\Models\Permission::findByName('cms.manage', 'web');

        $this->actingAsStaff($super)
            ->put(route('admin.roles.permissions', $role), [
                'permissions' => [$permission->id],
            ])
            ->assertRedirect();

        $this->assertTrue($role->fresh()->hasPermissionTo('cms.manage'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.role.permission_change',
        ]);
    }

    public function test_admin_cannot_sync_role_permissions(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $role = \Spatie\Permission\Models\Role::findByName(Role::ContentEditor->value, 'web');
        $permission = \Spatie\Permission\Models\Permission::findByName('question.view', 'web');

        $this->actingAsStaff($admin)
            ->put(route('admin.roles.permissions', $role), [
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

        $log = \Modules\Admin\Models\AuditLog::query()->latest('id')->first();
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

        Notification::assertSentTo($student, \Illuminate\Auth\Notifications\ResetPassword::class);
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
