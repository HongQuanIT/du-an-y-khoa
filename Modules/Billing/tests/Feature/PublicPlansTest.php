<?php

declare(strict_types=1);

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Tests\TestCase;

final class PublicPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_pricing_page_loads_plans_from_database(): void
    {
        $this->get(route('landing.pricing'))
            ->assertOk()
            ->assertSee('Miễn phí')
            ->assertSee('Premium')
            ->assertSee('1790000')
            ->assertSee('199000');
    }

    public function test_pricing_shows_current_plan_badge_for_premium_user(): void
    {
        $user = User::factory()->create();
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'redeem',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->get(route('landing.pricing'))
            ->assertOk()
            ->assertSee('Gói của bạn')
            ->assertSee('Bạn đang dùng');
    }

    public function test_public_plans_api_returns_catalog(): void
    {
        $this->getJson(route('api.billing.plans.index'))
            ->assertOk()
            ->assertJsonPath('data.plans.0.attributes.slug', 'free')
            ->assertJsonStructure([
                'data' => ['plans'],
                'meta' => ['request_id'],
            ]);
    }

    public function test_subscription_api_requires_auth(): void
    {
        $this->getJson(route('api.billing.subscription.show'))
            ->assertUnauthorized();
    }

    public function test_subscription_api_returns_current_plan(): void
    {
        $user = User::factory()->create();
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'redeem',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('api.billing.subscription.show'))
            ->assertOk()
            ->assertJsonPath('data.attributes.plan_slug', 'premium')
            ->assertJsonPath('data.attributes.is_free', false);
    }

    public function test_subscription_page_requires_auth(): void
    {
        $this->get(route('subscription.show'))
            ->assertRedirect(route('login'));
    }

    public function test_subscription_page_shows_current_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('subscription.show'))
            ->assertOk()
            ->assertSee('Gói đăng ký của bạn')
            ->assertSee('Free');
    }
}
