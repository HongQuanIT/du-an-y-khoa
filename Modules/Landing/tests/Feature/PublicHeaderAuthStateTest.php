<?php

declare(strict_types=1);

namespace Modules\Landing\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Tests\TestCase;

final class PublicHeaderAuthStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_guest_landing_shows_login_and_register(): void
    {
        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Đăng nhập', false)
            ->assertSee('Đăng ký', false)
            ->assertDontSee('Tạo phiên học', false);
    }

    public function test_authenticated_free_user_sees_name_plan_and_study_cta(): void
    {
        $user = User::factory()->create(['name' => 'Nguyễn Văn An']);
        $user->assignRole('student');

        $html = $this->actingAs($user)
            ->get(route('landing.pricing'))
            ->assertOk()
            ->assertSee('Nguyễn Văn An', false)
            ->assertSee('Free', false)
            ->assertSee('Tạo phiên học', false)
            ->assertSee('/qbank', false)
            ->getContent();

        $this->assertStringNotContainsString('href="'.route('login').'"', $html);
        $this->assertStringNotContainsString('>Đăng nhập<', $html);
        $this->assertStringNotContainsString('>Đăng ký<', $html);
    }

    public function test_authenticated_premium_user_sees_prominent_premium_badge(): void
    {
        $user = User::factory()->create(['name' => 'Trần Premium']);
        $user->assignRole('student');
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $premium->id,
            'status' => 'active',
            'source' => 'purchase',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Trần Premium', false)
            ->assertSee('Premium', false)
            ->assertSee('premium-badge', false)
            ->assertSee('Tạo phiên học', false)
            ->assertDontSee('>Đăng nhập<', false)
            ->assertDontSee('>Đăng ký<', false);
    }
}
