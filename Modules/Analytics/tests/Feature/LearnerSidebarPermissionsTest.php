<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LearnerSidebarPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sidebar_displays_all_sections_when_permissions_are_granted(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Tổng quan')
            ->assertSee('Ngân hàng câu hỏi')
            ->assertSee('Kế hoạch học tập')
            ->assertSee('Lớp học')
            ->assertSee('Kỳ thi');
    }

    public function test_disabling_view_permissions_hides_respective_sidebar_items(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $studentRole = \Spatie\Permission\Models\Role::findByName(Role::Student->value);

        // 1. Revoke exam.view, keep exam.take & exam.review
        $studentRole->revokePermissionTo('exam.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $student->forgetCachedPermissions();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Kỳ thi');

        $this->actingAs($student)
            ->get(route('exam.index'))
            ->assertForbidden();

        // 2. Revoke classroom.view, keep classroom.join & classroom.leave
        $studentRole->revokePermissionTo('classroom.view');
        \Illuminate\Support\Facades\Cache::flush();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $student->forgetCachedPermissions();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Lớp học');

        $this->actingAs($student)
            ->get(route('classroom.index'))
            ->assertForbidden();

        // 3. Revoke question.view
        $studentRole->revokePermissionTo('question.view');
        \Illuminate\Support\Facades\Cache::flush();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $student->forgetCachedPermissions();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Ngân hàng câu hỏi');

        $this->actingAs($student)
            ->get(route('qbank.index'))
            ->assertForbidden();

        // 4. Revoke study_plan.view
        $studentRole->revokePermissionTo('study_plan.view');
        \Illuminate\Support\Facades\Cache::flush();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $student->forgetCachedPermissions();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Kế hoạch học tập');

        $this->actingAs($student)
            ->get(route('study-plan.index'))
            ->assertForbidden();

        // 5. Revoke learner_dashboard.view
        $studentRole->revokePermissionTo('learner_dashboard.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $student->forgetCachedPermissions();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertForbidden();
    }
}
