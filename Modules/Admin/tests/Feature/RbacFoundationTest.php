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

        $this->assertGreaterThanOrEqual(170, count($names));
        $this->assertLessThanOrEqual(300, count($names));
        $this->assertCount(count(array_unique($names)), $names);
        $this->assertContains('user.role_assign', $names);
        $this->assertContains('classroom_session.start', $names);
        $this->assertContains('partner_payout.mark_paid', $names);
        $this->assertContains('learning_tool.note', $names);
        $this->assertContains('learning_tool.flag', $names);
        $this->assertContains('learning_tool.highlight', $names);
        $this->assertContains('learning_tool.research', $names);
        $this->assertNotContains('feature_flag.manage', $names);
        $this->assertNotContains('support_conversation.update', $names);
        $this->assertNotContains('support.manage', $names);
        $this->assertNotContains('notification.broadcast', $names);
        $this->assertNotContains('notification.update', $names);
        $this->assertNotContains('notification.view', $names);
        $this->assertNotContains('instructor.assign', $names);
        $this->assertNotContains('live.force_end', $names);
        $this->assertNotContains('live_hand.raise', $names);
        $this->assertNotContains('live_message.create', $names);
        $this->assertNotContains('live_message.manage', $names);
        $this->assertNotContains('live_hand.manage', $names);
        $this->assertNotContains('live_question.view', $names);
        $this->assertNotContains('live_question.update', $names);
        $this->assertNotContains('live_chat.mute', $names);
        $this->assertNotContains('classroom_member.view', $names);
        $this->assertNotContains('classroom_member.invite', $names);
        $this->assertNotContains('classroom_member.remove', $names);
        $this->assertNotContains('classroom_member.ban', $names);
        $this->assertNotContains('study_plan.delete', $names);
        $this->assertNotContains('study_plan.replan', $names);
        $this->assertNotContains('study_plan.update', $names);
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

    public function test_permission_catalog_standardizes_action_order(): void
    {
        $this->assertSame(10, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.view_any'));
        $this->assertSame(11, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.view'));
        $this->assertSame(20, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.create'));
        $this->assertSame(30, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.update'));
        $this->assertSame(30, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.edit'));
        $this->assertSame(40, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.delete'));
        $this->assertSame(50, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.import'));
        $this->assertSame(60, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.export'));
        $this->assertSame(70, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.approve'));
        $this->assertSame(75, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.reject'));
        $this->assertSame(100, \Modules\Admin\Support\PermissionCatalog::actionPriority('question.manage'));

        $this->assertSame('Xem danh sách', \Modules\Admin\Support\PermissionCatalog::actionLabel('question.view_any'));
        $this->assertSame('Xem chi tiết', \Modules\Admin\Support\PermissionCatalog::actionLabel('question.view'));
        $this->assertSame('Phê duyệt', \Modules\Admin\Support\PermissionCatalog::actionLabel('question.approve'));
        $this->assertSame('Từ chối', \Modules\Admin\Support\PermissionCatalog::actionLabel('question.reject'));

        $grouped = \Modules\Admin\Support\PermissionCatalog::groupedByPortal();
        $adminPortal = $grouped[PortalGroup::Admin->value] ?? null;
        $this->assertNotNull($adminPortal);

        // Verify that in question module, question resource has permissions sorted by standardized action order
        $questionModule = collect($adminPortal['modules'])->firstWhere('key', 'question_bank');
        $this->assertNotNull($questionModule);
        $questionResource = collect($questionModule['resources'])->firstWhere('key', 'question');
        $this->assertNotNull($questionResource);

        $questionPermNames = $questionResource['permissions']->pluck('name')->all();
        $this->assertContains('question.view_any', $questionPermNames);
        $this->assertContains('question.view', $questionPermNames);

        $orderedActionPriorities = $questionResource['permissions']
            ->map(fn ($perm) => \Modules\Admin\Support\PermissionCatalog::actionPriority($perm->name))
            ->values()
            ->all();

        $sortedActionPriorities = $orderedActionPriorities;
        sort($sortedActionPriorities);
        $this->assertSame($sortedActionPriorities, $orderedActionPriorities);

        // Verify 2FA toggle permissions exist for all 4 portals
        $registry = app(\App\Support\Rbac\PermissionRegistry::class);
        $admin2fa = $registry->find('profile.two_factor_toggle');
        $this->assertNotNull($admin2fa);
        $this->assertContains(PortalGroup::Admin, $admin2fa->portals);
        $this->assertContains(PortalGroup::Learner, $admin2fa->portals);

        $instructor2fa = $registry->find('teach_profile.two_factor_toggle');
        $this->assertNotNull($instructor2fa);
        $this->assertContains(PortalGroup::Instructor, $instructor2fa->portals);

        $partner2fa = $registry->find('partner_profile.two_factor_toggle');
        $this->assertNotNull($partner2fa);
        $this->assertContains(PortalGroup::Partner, $partner2fa->portals);

        $this->assertSame('Bật / tắt 2FA', \Modules\Admin\Support\PermissionCatalog::actionLabel('profile.two_factor_toggle'));
    }
}
