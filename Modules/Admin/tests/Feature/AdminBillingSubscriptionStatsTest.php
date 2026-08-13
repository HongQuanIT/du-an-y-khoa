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
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\Subscription;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

final class AdminBillingSubscriptionStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_plans_index_shows_student_kpis(): void
    {
        $admin = $this->staffUser(Role::Admin->value);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        $learner = $this->studentUser();
        $this->studentUser();

        Subscription::query()->create([
            'user_id' => $learner->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'redeem',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.plans.index'))
            ->assertOk()
            ->assertSee('Tổng học viên')
            ->assertSee('Học viên Free')
            ->assertSee('Học viên Premium')
            ->assertSee('Phân bổ Premium theo SKU')
            ->assertSee('2', false)
            ->assertSee('1', false);
    }

    public function test_stats_exclude_non_student_users(): void
    {
        $admin = $this->staffUser(Role::Admin->value);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        $student = $this->studentUser();
        $instructor = User::factory()->create();
        $instructor->assignRole(Role::Instructor->value);

        Subscription::query()->create([
            'user_id' => $student->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'purchase',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        Subscription::query()->create([
            'user_id' => $instructor->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'purchase',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.plans.index'))
            ->assertOk()
            ->assertSee('Học viên Premium')
            ->assertSee('1', false);
    }

    public function test_plan_edit_shows_sku_stats_and_unassigned_bucket(): void
    {
        $admin = $this->staffUser(Role::Admin->value);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        $monthly = PlanPrice::query()->where('slug', 'premium-monthly')->firstOrFail();
        $learnerWithSku = $this->studentUser();
        $learnerUnassigned = $this->studentUser();

        Subscription::query()->create([
            'user_id' => $learnerWithSku->id,
            'plan_id' => $premium->id,
            'plan_price_id' => $monthly->id,
            'status' => 'active',
            'source' => 'purchase',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        Subscription::query()->create([
            'user_id' => $learnerUnassigned->id,
            'plan_id' => $premium->id,
            'plan_price_id' => null,
            'status' => 'active',
            'source' => 'redeem',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.plans.edit', $premium))
            ->assertOk()
            ->assertSee('Học viên')
            ->assertSee('1 học viên')
            ->assertSee('Chưa gắn SKU')
            ->assertSee('Mua trực tiếp: 1')
            ->assertSee('Đổi mã: 1');
    }

    public function test_subscriptions_index_filters_by_plan_sku_and_source(): void
    {
        $admin = $this->staffUser(Role::Admin->value);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        $monthly = PlanPrice::query()->where('slug', 'premium-monthly')->firstOrFail();
        $target = $this->studentUser(['name' => 'Nguyen Van A', 'email' => 'a@test.com']);
        $other = $this->studentUser(['name' => 'Other User', 'email' => 'other@test.com']);

        Subscription::query()->create([
            'user_id' => $target->id,
            'plan_id' => $premium->id,
            'plan_price_id' => $monthly->id,
            'status' => 'active',
            'source' => 'purchase',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        Subscription::query()->create([
            'user_id' => $other->id,
            'plan_id' => $premium->id,
            'plan_price_id' => null,
            'status' => 'active',
            'source' => 'redeem',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.subscriptions.index', [
                'plan' => $premium->id,
                'sku' => $monthly->id,
                'source' => 'purchase',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSee('Nguyen Van A')
            ->assertDontSee('Other User');

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.subscriptions.index', [
                'plan' => $premium->id,
                'sku' => 'unassigned',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSee('Other User')
            ->assertSee('Chưa gắn SKU')
            ->assertDontSee('Nguyen Van A');
    }

    public function test_subscriptions_index_excludes_non_students(): void
    {
        $admin = $this->staffUser(Role::Admin->value);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        $instructor = User::factory()->create(['name' => 'Instructor User']);
        $instructor->assignRole(Role::Instructor->value);

        Subscription::query()->create([
            'user_id' => $instructor->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'purchase',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.subscriptions.index'))
            ->assertOk()
            ->assertDontSee('Instructor User');
    }

    public function test_non_admin_cannot_access_subscriptions_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $this->actingAs($user)
            ->get(route('admin.billing.subscriptions.index'))
            ->assertForbidden();
    }

    /** @param  array<string, mixed>  $attributes */
    private function studentUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::Student->value);

        return $user;
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
