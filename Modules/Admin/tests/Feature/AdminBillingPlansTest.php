<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

final class AdminBillingPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_admin_can_view_billing_plans_index(): void
    {
        $admin = $this->staffUser(Role::Admin->value);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.plans.index'))
            ->assertOk()
            ->assertSee('Gói & bảng giá')
            ->assertSee('Premium');
    }

    public function test_admin_can_update_plan_tier(): void
    {
        $admin = $this->staffUser(Role::Admin->value);
        $plan = Plan::query()->where('slug', 'free')->firstOrFail();

        $this->actingAsStaff($admin)
            ->put(route('admin.billing.plans.update', $plan), [
                'name' => 'Free tier',
                'description' => 'Cập nhật mô tả',
                'features_text' => "20 câu/ngày\nThư viện giới hạn",
                'entitlements' => [],
                'is_active' => '1',
                'sort_order' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('billing_plans', [
            'id' => $plan->id,
            'name' => 'Free tier',
        ]);
    }

    public function test_non_admin_cannot_access_billing_plans(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $this->actingAs($user)
            ->get(route('admin.billing.plans.index'))
            ->assertForbidden();
    }

    private function staffUser(string $role): User
    {
        SpatiePermission::findOrCreate(Permission::BillingManage->value, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo(Permission::BillingManage->value);

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
