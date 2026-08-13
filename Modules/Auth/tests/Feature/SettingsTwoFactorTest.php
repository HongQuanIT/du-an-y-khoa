<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class SettingsTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_is_redirected_from_settings_2fa_setup(): void
    {
        $this->get(route('settings.2fa.setup'))
            ->assertRedirect(route('login'));
    }

    public function test_security_tab_shows_2fa_section_for_student(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('Xác thực hai bước (2FA)')
            ->assertSee('Bật 2FA');
    }

    public function test_student_can_enable_2fa_via_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $this->actingAs($user)
            ->get(route('settings.2fa.setup'))
            ->assertOk()
            ->assertSee('Bật xác thực 2 bước');

        $user->refresh();
        $secret = $user->twoFactorSecret?->secret;
        $this->assertNotNull($secret);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->actingAs($user)
            ->post(route('settings.2fa.confirm'), ['code' => $code])
            ->assertRedirect(route('settings.2fa.recovery'))
            ->assertSessionHas('two_factor_recovery_codes')
            ->assertSessionHas(TwoFactorSession::KEY);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)
            ->withSession([
                'two_factor_recovery_codes' => ['AAAA-BBBB'],
                TwoFactorSession::KEY => now()->timestamp,
            ])
            ->get(route('settings.2fa.recovery'))
            ->assertOk()
            ->assertSee('AAAA-BBBB');

        $this->actingAs($user)
            ->post(route('settings.2fa.recovery.finish'))
            ->assertRedirect(route('profile.show', ['tab' => 'security']))
            ->assertSessionHas('status');
    }

    public function test_disable_with_wrong_password_keeps_2fa(): void
    {
        $user = User::factory()->create(['password' => 'Password1!']);
        $user->assignRole(Role::Student->value);
        $this->enrollTwoFactor($user);

        $this->actingAs($user)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->from(route('profile.show', ['tab' => 'security']))
            ->delete(route('settings.2fa.disable'), [
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'security']))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disable_with_correct_password_removes_2fa(): void
    {
        $user = User::factory()->create(['password' => 'Password1!']);
        $user->assignRole(Role::Student->value);
        $this->enrollTwoFactor($user);

        $this->actingAs($user)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->from(route('profile.show', ['tab' => 'security']))
            ->delete(route('settings.2fa.disable'), [
                'current_password' => 'Password1!',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'security']))
            ->assertSessionHas('status')
            ->assertSessionMissing(TwoFactorSession::KEY);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseMissing('two_factor_secrets', ['user_id' => $user->id]);
    }

    public function test_staff_cannot_use_settings_2fa_setup(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);

        $this->actingAs($user)
            ->get(route('settings.2fa.setup'))
            ->assertForbidden();
    }

    public function test_staff_security_tab_hides_2fa_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('Đổi mật khẩu')
            ->assertDontSee('Xác thực hai bước (2FA)');
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

        return $secret;
    }
}
