<?php

declare(strict_types=1);

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\CheckoutIntent;
use Tests\TestCase;

final class GuestCheckoutIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_pricing_guest_premium_cta_includes_plan_price_id(): void
    {
        $monthly = PlanPrice::query()->where('slug', 'premium-monthly')->firstOrFail();

        $this->get(route('landing.pricing'))
            ->assertOk()
            ->assertSee('plan_price_id='.$monthly->id, false)
            ->assertSee('/register?plan_price_id=', false);
    }

    public function test_register_with_plan_price_id_redirects_to_upgrade_after_signup(): void
    {
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        $this->get(route('register', ['plan_price_id' => $price->id]))
            ->assertOk()
            ->assertSee('xác nhận gói', false);

        $this->post(route('register', ['plan_price_id' => $price->id]), [
            'name' => 'Học viên Test',
            'email' => 'buyer@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
            'plan_price_id' => $price->id,
        ])->assertRedirect(CheckoutIntent::upgradeUrl($price->id));

        $this->assertAuthenticated();
    }

    public function test_login_with_plan_price_id_redirects_to_upgrade(): void
    {
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'returning@example.com',
            'password' => 'password',
        ]);

        $this->get(route('login', ['plan_price_id' => $price->id]))
            ->assertOk()
            ->assertSee('xác nhận gói', false);

        $this->post(route('login', ['plan_price_id' => $price->id]), [
            'email' => 'returning@example.com',
            'password' => 'password',
            'plan_price_id' => $price->id,
        ])->assertRedirect(CheckoutIntent::upgradeUrl($price->id));

        $this->assertAuthenticatedAs($user);
    }

    public function test_upgrade_highlights_selected_plan_price(): void
    {
        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        $this->actingAs($user)
            ->get(route('subscription.upgrade', ['plan_price_id' => $price->id]))
            ->assertOk()
            ->assertSee('Bạn đã chọn gói từ bảng giá', false)
            ->assertSee('Đã chọn', false)
            ->assertSee('id="selected-plan"', false);
    }

    public function test_invalid_plan_price_id_is_ignored_on_register(): void
    {
        $this->get(route('register', ['plan_price_id' => 999999]))
            ->assertOk()
            ->assertDontSee('xác nhận gói', false);

        $this->post(route('register'), [
            'name' => 'Học viên Free',
            'email' => 'free@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertNull(session(CheckoutIntent::SESSION_KEY));
    }
}
