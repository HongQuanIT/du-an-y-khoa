<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Enums\PortalGroup;
use App\Support\Rbac\PermissionRegistry;
use App\Support\Rbac\PermissionSynchronizer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Models\Partner;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registry_contains_the_expected_permission_scale_without_duplicate_names(): void
    {
        $names = app(PermissionRegistry::class)->names();

        $this->assertGreaterThanOrEqual(200, count($names));
        $this->assertLessThanOrEqual(300, count($names));
        $this->assertCount(count(array_unique($names)), $names);
        $this->assertContains('user.role_assign', $names);
        $this->assertContains('classroom_session.start', $names);
        $this->assertContains('partner_payout.mark_paid', $names);
    }

    public function test_permission_sync_is_additive_and_restores_missing_catalog_entries(): void
    {
        Permission::findByName('search.use', 'web')->delete();
        Permission::findOrCreate('integration.keep_existing', 'web');

        $result = app(PermissionSynchronizer::class)->sync();

        $this->assertGreaterThanOrEqual(1, $result['created']);
        $this->assertDatabaseHas('permissions', ['name' => 'search.use', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'integration.keep_existing', 'guard_name' => 'web']);
    }

    public function test_rbac_doctor_accepts_a_fresh_seeded_matrix(): void
    {
        $this->artisan('rbac:doctor')->assertSuccessful();
    }

    public function test_user_without_a_role_cannot_enter_a_learner_feature(): void
    {
        $user = User::factory()->create();

        $this->actingAsWithWebSession($user)
            ->get(route('search.index'))
            ->assertForbidden();
    }

    public function test_custom_admin_role_can_enter_only_the_screen_granted_to_it(): void
    {
        $role = Role::query()->create([
            'name' => 'learner_support_agent',
            'guard_name' => 'web',
            'portal' => PortalGroup::Admin->value,
            'display_name' => 'Nhân viên hỗ trợ học viên',
        ]);
        $role->givePermissionTo('user.view_any');

        $user = User::factory()->create();
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAsWithWebSession($user)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAsWithWebSession($user)
            ->get(route('admin.billing.plans.index'))
            ->assertForbidden();
    }

    public function test_custom_instructor_role_is_recognized_by_portal_metadata(): void
    {
        $role = Role::query()->create([
            'name' => 'teaching_assistant',
            'guard_name' => 'web',
            'portal' => PortalGroup::Instructor->value,
            'display_name' => 'Trợ giảng',
        ]);
        $role->givePermissionTo('teaching_dashboard.view');

        $user = User::factory()->create();
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAsWithWebSession($user)
            ->get(route('teach.dashboard'))
            ->assertOk();
    }

    public function test_partner_feature_is_denied_when_its_permission_is_not_assigned(): void
    {
        $role = Role::query()->create([
            'name' => 'limited_partner',
            'guard_name' => 'web',
            'portal' => PortalGroup::Partner->value,
            'display_name' => 'Cộng tác viên giới hạn',
        ]);
        $role->givePermissionTo(['partner.portal', 'partner_dashboard.view']);

        $user = User::factory()->create();
        $user->assignRole($role);
        Partner::query()->create([
            'user_id' => $user->getKey(),
            'display_name' => 'CTV giới hạn',
            'default_commission_rate_bps' => 1000,
            'status' => PartnerStatus::Active,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAsWithWebSession($user)
            ->get(route('partner.dashboard'))
            ->assertOk();

        $this->actingAsWithWebSession($user)
            ->get(route('partner.commissions.index'))
            ->assertForbidden();
    }
}
