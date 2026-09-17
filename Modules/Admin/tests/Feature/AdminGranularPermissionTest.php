<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Support\AdminMenu;
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
        $this->actingAsWithWebSession($user)->post(route('admin.classrooms.approve', $classroom))->assertRedirect();
        $this->assertSame(ClassroomApprovalStatus::Approved, $classroom->fresh()->approval_status);
    }

    public function test_old_management_permissions_do_not_unlock_admin_screens(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'restricted_admin', 'guard_name' => 'web', 'portal' => 'admin']);
        $role->givePermissionTo(['billing.manage', 'system.manage']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $screens = [
            'user.view_any' => 'admin.users.index',
            'learner_catalog.view_any' => 'admin.institutions.index',
            'role.view_any' => 'admin.roles.index',
            'billing_plan.view' => 'admin.billing.plans.index',
            'exam.view_any' => 'admin.exams.index',
            'classroom_oversight.view_any' => 'admin.classrooms.index',
            'system_setting.view' => 'admin.settings.index',
            'taxonomy.view' => 'admin.taxonomy.index',
            'media.view' => 'admin.media.index',
            'question_feedback.view_any' => 'admin.question-feedback.index',
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

    public function test_user_view_cannot_change_status_without_status_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'status_support', 'guard_name' => 'web', 'portal' => 'admin']);
        $role->givePermissionTo(['user.view']);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $target = User::factory()->create();
        $target->assignRole('student');
        $this->actingAsWithWebSession($actor)->patch(route('admin.users.status', $target), ['status' => 'suspended'])->assertForbidden();
        $this->actingAsWithWebSession($actor)->get(route('admin.users.show', $target))->assertOk()
            ->assertDontSee('action="'.route('admin.users.status', $target).'"', false);
    }
}
