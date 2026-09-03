<?php

declare(strict_types=1);

namespace Modules\Partner\Tests\Feature;

use App\Models\User;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Models\Partner;
use Tests\TestCase;

final class PartnerRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_role_change_creates_partner_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $user = User::factory()->create(['name' => 'CTV mới']);
        $user->assignRole(Role::Student->value);

        $this->actingAsWithWebSession($admin)
            ->patch(route('admin.users.role', $user), [
                'portal' => PortalGroup::Partner->value,
                'role' => Role::Partner->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->hasRole(Role::Partner->value));
        $this->assertDatabaseHas('partners', [
            'user_id' => $user->getKey(),
            'display_name' => 'CTV mới',
            'default_commission_rate_bps' => 1000,
            'status' => PartnerStatus::Active->value,
        ]);
    }

    public function test_existing_partner_role_without_profile_is_repaired_on_portal_access(): void
    {
        $user = User::factory()->create(['name' => 'CTV cần phục hồi']);
        $user->assignRole(Role::Partner->value);

        $this->assertDatabaseMissing('partners', ['user_id' => $user->getKey()]);

        $this->actingAsWithWebSession($user)
            ->get(route('partner.dashboard'))
            ->assertOk()
            ->assertSee('CTV cần phục hồi');

        $this->assertDatabaseHas('partners', [
            'user_id' => $user->getKey(),
            'display_name' => 'CTV cần phục hồi',
            'default_commission_rate_bps' => 1000,
            'status' => PartnerStatus::Active->value,
        ]);
    }

    public function test_admin_creating_partner_user_also_creates_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $response = $this->actingAsWithWebSession($admin)
            ->post(route('admin.users.store'), [
                'portal' => PortalGroup::Partner->value,
                'role' => Role::Partner->value,
                'name' => 'CTV tạo từ Admin',
                'email' => 'partner-created@example.com',
                'password' => 'Partner123!',
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'partner-created@example.com')->firstOrFail();
        $partner = Partner::query()->where('user_id', $user->getKey())->firstOrFail();

        $response->assertRedirect(route('admin.partners.show', $partner));
        $this->assertTrue($user->hasRole(Role::Partner->value));
        $this->assertSame('CTV tạo từ Admin', $partner->display_name);
        $this->assertSame(1000, $partner->default_commission_rate_bps);
        $this->assertSame(PartnerStatus::Active, $partner->status);
    }
}
