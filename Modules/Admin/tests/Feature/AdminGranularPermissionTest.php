<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role as SystemRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Modules\Auth\Notifications\ResetPasswordNotification;
use Modules\Admin\Support\AdminMenu;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\Profession;
use Modules\Admin\Support\AdminRouteAccess;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Enums\ClassroomApprovalStatus;
use Modules\Classroom\Enums\ClassroomPurpose;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminGranularPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_classroom_approval_uses_granular_permission_in_route_and_action(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'classroom_reviewer', 'guard_name' => 'web', 'portal' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $host = User::factory()->create();
        $host->assignRole('instructor');
        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Permission test',
            'purpose' => ClassroomPurpose::FeedbackReview->value,
        ]);
        // Ensure without permission, it's forbidden.
        $this->actingAsWithWebSession($user)->post(route('admin.classrooms.approve', $classroom))->assertForbidden();
        $user->givePermissionTo('classroom_oversight.approve');
        $this->actingAsWithWebSession($user)->post(route('admin.classrooms.approve', $classroom))->assertForbidden();
        $user->givePermissionTo('classroom_oversight.view');
        $this->actingAsWithWebSession($user)->post(route('admin.classrooms.approve', $classroom))->assertRedirect();
        $this->assertSame(ClassroomApprovalStatus::Approved, $classroom->fresh()->approval_status);
    }

    public function test_old_management_permissions_do_not_unlock_admin_screens(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'restricted_admin', 'guard_name' => 'web', 'portal' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $screens = [
            'user.view' => 'admin.users.index',
            'learner_catalog.view' => 'admin.institutions.index',
            'role.view' => 'admin.roles.index',
            'billing_plan.view' => 'admin.billing.plans.index',
            'exam.view' => 'admin.exams.index',
            'classroom_oversight.view' => 'admin.classrooms.index',
            'system_setting.view' => 'admin.settings.index',
            'taxonomy.view' => 'admin.taxonomy.index',
            'media.view' => 'admin.media.index',
            'question_feedback.view' => 'admin.question-feedback.index',
        ];

        foreach ($screens as $permission => $route) {
            $this->actingAsWithWebSession($user)->get(route($route))->assertForbidden();
            $this->assertNotContains($route, array_column(AdminMenu::for($user), 'route'));
            $user->givePermissionTo($permission);
            $this->actingAsWithWebSession($user)->get(route($route))->assertOk();
            $user->revokePermissionTo($permission);
            $this->actingAsWithWebSession($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_action_routes_are_hidden_without_implied_view_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'partner_code_operator', 'guard_name' => 'web', 'portal' => 'admin']);
        $role->givePermissionTo('partner_code.update');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertFalse(AdminRouteAccess::allows($user, 'admin.partners.codes.toggle'));

        $user->givePermissionTo('partner_code.view');

        $this->assertTrue(AdminRouteAccess::allows($user, 'admin.partners.codes.toggle'));
    }

    public function test_taxonomy_view_gates_every_classification_screen(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'classification_viewer', 'guard_name' => 'web', 'portal' => 'admin']);
        $role->givePermissionTo(['taxonomy.view', 'blueprint.view', 'curriculum.view', 'tag.view']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $screens = [
            'admin.taxonomy.index',
            'admin.blueprints.index',
            'admin.curriculum.index',
            'admin.tags.index',
        ];

        foreach ($screens as $route) {
            $this->actingAsWithWebSession($user)->get(route($route))->assertOk();
        }

        $role->revokePermissionTo('taxonomy.view');

        foreach ($screens as $route) {
            $this->actingAsWithWebSession($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_super_admin_question_flag_permission_controls_review_sidebar(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::findByName(SystemRole::SuperAdmin->value, 'web');
        $role->revokePermissionTo('question_flag.view');
        $role->revokePermissionTo(Permission::QuestionFlag->value);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertFalse($user->can('question_flag.view'));
        $this->assertFalse($user->can(Permission::QuestionFlag->value));
        $this->assertNotContains('admin.questions.flags.index', array_column(AdminMenu::for($user), 'route'));

        $this->actingAsWithWebSession($user)
            ->get(route('reviewer.questions.flags.index'))
            ->assertForbidden();
    }

    public function test_user_view_cannot_change_status_without_status_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'status_support', 'guard_name' => 'web', 'portal' => 'admin']);
        $role->givePermissionTo(['user.view']);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $target = User::factory()->create();
        $target->assignRole('student');
        $this->actingAsWithWebSession($actor)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Danh sách không được hiển thị');

        $this->actingAsWithWebSession($actor)->patch(route('admin.users.status', $target), ['status' => 'suspended'])->assertForbidden();
        $this->actingAsWithWebSession($actor)->get(route('admin.users.show', $target))->assertOk()
            ->assertDontSee('action="'.route('admin.users.status', $target).'"', false);
    }

    public function test_user_lookup_returns_only_the_learner_matched_by_full_email(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create([
            'name' => 'learner_email_support',
            'guard_name' => 'web',
            'portal' => 'admin',
            'display_name' => 'Hỗ trợ tra cứu học viên',
        ]);
        $role->givePermissionTo('user.lookup');
        $agent = User::factory()->create();
        $agent->assignRole($role);

        $learner = User::factory()->create([
            'name' => 'Hoc Vien An',
            'email' => 'an.hv@example.com',
        ]);
        $learner->assignRole(SystemRole::Student->value);
        $other = User::factory()->create([
            'name' => 'Hoc Vien Binh',
            'email' => 'binh.hv@example.com',
        ]);
        $other->assignRole(SystemRole::Student->value);
        $staff = User::factory()->create(['email' => 'staff.ops@example.com']);
        $staff->assignRole(SystemRole::Admin->value);

        $this->assertContains('admin.users.index', array_column(AdminMenu::for($agent), 'route'));

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Danh sách không được hiển thị')
            ->assertDontSee('an.hv@example.com')
            ->assertDontSee('binh.hv@example.com')
            ->assertDontSee('Cổng truy cập');

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.index', ['q' => 'an', 'role' => [SystemRole::Student->value]]))
            ->assertOk()
            ->assertSee('không tìm theo tên hoặc một phần thông tin')
            ->assertDontSee('an.hv@example.com')
            ->assertDontSee('binh.hv@example.com');

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.index', ['q' => 'AN.HV@example.com']))
            ->assertOk()
            ->assertSee('an.hv@example.com')
            ->assertSee('Hoc Vien An')
            ->assertSee('signature=', false)
            ->assertDontSee('binh.hv@example.com')
            ->assertDontSee('staff.ops@example.com');

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.index', ['q' => strtolower((string) $learner->learner_code)]))
            ->assertOk()
            ->assertSee('an.hv@example.com')
            ->assertDontSee('binh.hv@example.com');

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.index', ['q' => substr((string) $learner->learner_code, 0, 4)]))
            ->assertOk()
            ->assertDontSee('an.hv@example.com');

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.index', ['q' => 'staff.ops@example.com']))
            ->assertOk()
            ->assertSee('Không tìm thấy học viên với email hoặc mã này')
            ->assertDontSee($staff->name);

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.show', $learner))
            ->assertForbidden();

        $this->actingAsWithWebSession($agent)
            ->get(URL::temporarySignedRoute('admin.users.show', now()->addHour(), $learner))
            ->assertOk()
            ->assertSee('an.hv@example.com')
            ->assertDontSee('Lưu hồ sơ học viên')
            ->assertDontSee('Cập nhật trạng thái')
            ->assertDontSee('Hỗ trợ kích hoạt 2FA');

        $this->actingAsWithWebSession($agent)
            ->get(URL::temporarySignedRoute('admin.users.show', now()->addHour(), $staff))
            ->assertNotFound();
    }

    public function test_user_update_permission_can_edit_a_learner_but_not_staff(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create([
            'name' => 'learner_support_editor',
            'guard_name' => 'web',
            'portal' => 'admin',
        ]);
        $role->givePermissionTo(['user.lookup', 'user.update']);
        $agent = User::factory()->create();
        $agent->assignRole($role);

        $country = Country::query()->create(['code' => 'VN', 'name' => 'Việt Nam', 'is_active' => true, 'sort_order' => 0]);
        $unit = AdministrativeUnit::query()->create([
            'country_id' => $country->id,
            'code' => 'HN',
            'name' => 'Hà Nội',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $institution = Institution::query()->create([
            'country_id' => $country->id,
            'administrative_unit_id' => $unit->id,
            'name' => 'Đại học Y Hà Nội',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $profession = Profession::query()->create([
            'code' => 'doctor',
            'name' => 'Bác sĩ',
            'requires_education_stage' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $learner = User::factory()->create(['email' => 'edit-me@example.com']);
        $learner->assignRole(SystemRole::Student->value);
        $staff = User::factory()->create();
        $staff->assignRole(SystemRole::Instructor->value);

        $this->actingAsWithWebSession($agent)
            ->get(route('admin.users.show', $learner))
            ->assertForbidden();

        $show = URL::temporarySignedRoute('admin.users.show', now()->addHour(), $learner);

        $this->actingAsWithWebSession($agent)
            ->get($show)
            ->assertOk()
            ->assertSee('id="learner-institution-search"', false)
            ->assertSee('Lưu hồ sơ học viên')
            ->assertSee('Cập nhật trạng thái')
            ->assertSee('Gửi email đặt lại mật khẩu')
            ->assertSee('Hỗ trợ kích hoạt 2FA')
            ->assertSee('signature=', false)
            ->assertDontSee('Lưu quyền truy cập');

        $this->actingAsWithWebSession($agent)
            ->patch(URL::temporarySignedRoute('admin.users.profile', now()->addHour(), $learner), [
                'country_id' => $country->id,
                'administrative_unit_id' => $unit->id,
                'institution_id' => $institution->id,
                'profession_id' => $profession->id,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('learner_profiles', [
            'user_id' => $learner->id,
            'institution_id' => $institution->id,
            'profession_id' => $profession->id,
        ]);

        $this->actingAsWithWebSession($agent)
            ->get(URL::temporarySignedRoute('admin.users.show', now()->addHour(), $learner))
            ->assertOk()
            ->assertSee('value="Đại học Y Hà Nội"', false)
            ->assertSee('value="Hà Nội"', false)
            ->assertSee('value="Việt Nam"', false)
            ->assertSee('value="Bác sĩ"', false);

        $this->actingAsWithWebSession($agent)
            ->patch(URL::temporarySignedRoute('admin.users.status', now()->addHour(), $learner), ['status' => 'suspended'])
            ->assertRedirect();
        $this->assertSame('suspended', $learner->fresh()->status->value);

        $this->actingAsWithWebSession($agent)
            ->post(URL::temporarySignedRoute('admin.users.enable-2fa', now()->addHour(), $learner))
            ->assertRedirect();
        $this->actingAsWithWebSession($agent)
            ->get(URL::temporarySignedRoute('admin.users.show', now()->addHour(), $learner))
            ->assertOk()
            ->assertSee('Mã QR kích hoạt 2FA của '.$learner->name);

        $this->actingAsWithWebSession($agent)
            ->patch(route('admin.users.status', $staff), ['status' => 'suspended'])
            ->assertForbidden();
        $this->actingAsWithWebSession($agent)
            ->patch(route('admin.users.profile', $staff), [
                'country_id' => $country->id,
                'administrative_unit_id' => $unit->id,
                'institution_id' => $institution->id,
                'profession_id' => $profession->id,
            ])
            ->assertForbidden();
    }

    public function test_lookup_role_can_use_account_actions_without_directory_permission(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'learner_account_support', 'guard_name' => 'web', 'portal' => 'admin']);
        $role->givePermissionTo([
            'user.lookup',
            'user.password_reset',
            'user.status_update',
            'user.two_factor_manage',
        ]);
        $agent = User::factory()->create();
        $agent->assignRole($role);
        $learner = User::factory()->create(['email' => 'reset-me@example.com']);
        $learner->assignRole(SystemRole::Student->value);

        $this->assertFalse($agent->can('user.view'));
        $this->assertTrue(AdminRouteAccess::allows($agent, 'admin.users.reset-password'));
        $this->assertTrue(AdminRouteAccess::allows($agent, 'admin.users.status'));
        $this->assertTrue(AdminRouteAccess::allows($agent, 'admin.users.reset-2fa'));

        $show = URL::temporarySignedRoute('admin.users.show', now()->addHour(), $learner);

        $this->actingAsWithWebSession($agent)
            ->get($show)
            ->assertOk()
            ->assertSee('Gửi email đặt lại mật khẩu')
            ->assertSee('Cập nhật trạng thái')
            ->assertSee('Hỗ trợ kích hoạt 2FA');

        $this->actingAsWithWebSession($agent)
            ->post(route('admin.users.reset-password', $learner))
            ->assertForbidden();

        $this->actingAsWithWebSession($agent)
            ->post(URL::temporarySignedRoute('admin.users.reset-password', now()->addHour(), $learner))
            ->assertRedirect();

        Notification::assertSentTo($learner, ResetPasswordNotification::class);
    }
}
