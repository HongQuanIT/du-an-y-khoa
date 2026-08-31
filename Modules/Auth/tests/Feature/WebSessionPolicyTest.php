<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorTrustedDevice;
use App\Support\Auth\WebSessionManager;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class WebSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_new_login_replaces_active_session_binding(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);
        $user->forceFill(['active_web_session_id' => 'previous-device-session'])->save();

        $this->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotSame('previous-device-session', $user->fresh()->active_web_session_id);
        $this->assertSame(session()->getId(), $user->fresh()->active_web_session_id);
    }

    public function test_stale_session_binding_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);
        $user->forceFill(['active_web_session_id' => 'another-device'])->save();

        $this->actingAs($user)
            ->withSession([
                WebSessionManager::BOUND_SESSION_ID => 'another-device',
                WebSessionManager::LOGGED_IN_AT => now()->timestamp,
                WebSessionManager::LAST_ACTIVITY_AT => now()->timestamp,
            ])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_idle_timeout_forces_re_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $idleHours = (int) config('auth-session.idle_timeout_hours', 24);

        $this->actingAs($user)
            ->withSession([
                WebSessionManager::BOUND_SESSION_ID => 'stale-session',
                WebSessionManager::LOGGED_IN_AT => now()->subDays(2)->timestamp,
                WebSessionManager::LAST_ACTIVITY_AT => now()->subHours($idleHours + 1)->timestamp,
            ])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_trusted_device_skips_2fa_within_trust_period(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);
        $this->enrollTwoFactor($user);

        $response = $this->withCookie(
            TwoFactorTrustedDevice::COOKIE,
            $this->devicePayload($user),
        )->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->carrySessionFrom($response)
            ->get(route('dashboard'))
            ->assertOk();
    }

    /**
     * @return string plain TOTP secret
     */
    private function enrollTwoFactor(User $user): string
    {
        $secret = (new TotpService)->generateSecret();

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => $secret,
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        $user->unsetRelation('twoFactorSecret');

        return $secret;
    }

    private function devicePayload(User $user): string
    {
        $user->load('twoFactorSecret');

        return json_encode([
            'uid' => $user->id,
            'cid' => $user->twoFactorSecret?->confirmed_at?->timestamp,
            'exp' => now()->addDays(30)->timestamp,
        ], JSON_THROW_ON_ERROR);
    }
}
