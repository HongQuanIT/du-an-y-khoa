<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Tests\Unit;

use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\AiAssistant\Exceptions\QuotaExceededException;
use Modules\AiAssistant\Services\AiQuotaService;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Tests\TestCase;

final class AiQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiQuotaService $quota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config([
            'aiassistant.free_daily_limit' => 10,
            'aiassistant.premium_daily_limit' => 100,
        ]);
        $this->quota = app(AiQuotaService::class);
    }

    public function test_free_user_limited_to_ten(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $this->assertFalse($this->quota->isUnlimited($user));
        $this->assertFalse($this->quota->isPremium($user));
        $this->assertSame(10, $this->quota->limitFor($user));
        $this->assertSame(10, $this->quota->remaining($user));

        for ($i = 0; $i < 10; $i++) {
            $this->quota->consume($user);
        }

        $this->assertSame(0, $this->quota->remaining($user));

        try {
            $this->quota->consume($user);
            $this->fail('Expected QuotaExceededException');
        } catch (QuotaExceededException) {
            $this->assertTrue(true);
        }
    }

    public function test_premium_user_soft_capped_at_one_hundred(): void
    {
        $this->seed(BillingDatabaseSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $plan = Plan::query()->where('slug', 'premium')->firstOrFail();
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => 'active',
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addMonth(),
            'source' => 'test',
        ]);

        $user = $user->fresh();
        $this->assertTrue($user->hasEntitlement(Entitlement::AiTutor->value));
        $this->assertTrue($this->quota->isPremium($user));
        $this->assertFalse($this->quota->isUnlimited($user));
        $this->assertSame(100, $this->quota->limitFor($user));

        $snap = $this->quota->snapshot($user);
        $this->assertFalse($snap['unlimited']);
        $this->assertSame(100, $snap['limit']);
        $this->assertSame(100, $snap['remaining']);

        $this->quota->consume($user);
        $this->assertSame(99, $this->quota->remaining($user));
    }

    public function test_staff_is_unlimited_and_skips_ledger(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);

        $this->assertTrue($this->quota->isUnlimited($user));
        $this->assertSame(0, $this->quota->limitFor($user));

        $this->quota->consume($user);
        $this->quota->consume($user);

        $this->assertSame(0, $this->quota->used($user));
        $snap = $this->quota->snapshot($user);
        $this->assertTrue($snap['unlimited']);
        $this->assertNull($snap['remaining']);
    }
}
