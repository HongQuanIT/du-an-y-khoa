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

final class AppSidebarPlanCtaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_free_user_sees_upgrade_premium_in_sidebar(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nâng cấp Premium', false)
            ->assertDontSee('Nâng cấp tài khoản', false);
    }

    public function test_premium_user_sees_status_card_instead_of_upgrade_cta(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'purchase',
            'starts_at' => now(),
            'ends_at' => now()->addDays(20),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Premium', false)
            ->assertSee('Đang dùng', false)
            ->assertDontSee('Nâng cấp Premium', false)
            ->assertDontSee('Nâng cấp tài khoản', false);
    }
}
