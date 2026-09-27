<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdaptiveLogBriefingTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->logPath = storage_path('framework/testing-adaptive.log');
        config(['logging.channels.adaptive.path' => $this->logPath]);
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_admin_can_delete_the_whole_adaptive_log(): void
    {
        file_put_contents($this->logPath, "[2026-09-27 10:00:00] testing.DEBUG: [adaptive] path {\"trace_id\":\"run1\"}\n");
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get(route('admin.adaptive-briefing'))
            ->assertOk()
            ->assertSee('Xóa log', false);

        $this->actingAsStaff($admin)
            ->delete(route('admin.adaptive-briefing.destroy'))
            ->assertRedirect(route('admin.adaptive-briefing'))
            ->assertSessionHas('status', 'Đã xóa toàn bộ log thích ứng.');

        $this->assertFileDoesNotExist($this->logPath);

        $this->actingAsStaff($admin)
            ->get(route('admin.adaptive-briefing'))
            ->assertOk()
            ->assertSee('Chưa có lần chọn câu nào', false)
            ->assertDontSee('Xóa log', false);
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
