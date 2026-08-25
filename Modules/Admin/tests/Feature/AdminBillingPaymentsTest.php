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
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\PlanPrice;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

final class AdminBillingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_admin_payments_lists_pending_and_failed_checkouts(): void
    {
        $admin = $this->staffUser();

        $learner = User::factory()->create(['email' => 'buyer@example.com', 'name' => 'Buyer Test']);
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        CheckoutSession::query()->create([
            'user_id' => $learner->id,
            'plan_price_id' => $price->id,
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'pending',
            'idempotency_key' => 'test-pending-1',
            'gateway' => 'vnpay',
            'expires_at' => now()->addHour(),
        ]);

        CheckoutSession::query()->create([
            'user_id' => $learner->id,
            'plan_price_id' => $price->id,
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'failed',
            'idempotency_key' => 'test-failed-1',
            'gateway' => 'vnpay',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.payments.index'))
            ->assertOk()
            ->assertSee('buyer@example.com', false)
            ->assertSee('Chờ thanh toán', false)
            ->assertSee('Thất bại', false);
    }

    public function test_admin_payments_marks_stale_pending_as_expired(): void
    {
        $admin = $this->staffUser();

        $learner = User::factory()->create(['email' => 'stale@example.com']);
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        $session = CheckoutSession::query()->create([
            'user_id' => $learner->id,
            'plan_price_id' => $price->id,
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'pending',
            'idempotency_key' => 'stale-pending',
            'gateway' => 'vnpay',
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.payments.index'))
            ->assertOk()
            ->assertSee('Hết hạn', false)
            ->assertSee('stale@example.com', false);

        $this->assertSame('expired', $session->fresh()->status);
    }

    public function test_admin_can_filter_pending_checkouts(): void
    {
        $admin = $this->staffUser();

        $learner = User::factory()->create(['email' => 'pending-only@example.com']);
        $price = PlanPrice::query()->where('slug', 'premium-monthly')->firstOrFail();

        CheckoutSession::query()->create([
            'user_id' => $learner->id,
            'plan_price_id' => $price->id,
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'pending',
            'idempotency_key' => 'filter-pending',
            'gateway' => 'fake',
            'expires_at' => now()->addHour(),
        ]);

        CheckoutSession::query()->create([
            'user_id' => $learner->id,
            'plan_price_id' => $price->id,
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'completed',
            'idempotency_key' => 'filter-completed',
            'gateway' => 'fake',
            'expires_at' => now()->addHour(),
            'completed_at' => now(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.payments.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Chờ thanh toán', false)
            ->assertSee('pending-only@example.com', false)
            ->assertDontSee('xong ', false);
    }

    private function staffUser(): User
    {
        SpatiePermission::findOrCreate(Permission::BillingManage->value, 'web');

        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);
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
