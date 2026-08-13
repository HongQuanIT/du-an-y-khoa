<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\Auth\StudentTwoFactorDevice;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class StudentLoginTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_without_2fa_reaches_dashboard_after_login(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);

        $this->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_student_with_2fa_is_challenged_after_login(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);
        $this->enrollTwoFactor($user);

        $this->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('student.2fa.challenge'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertRedirect(route('student.2fa.challenge'));
    }

    public function test_student_with_2fa_cannot_open_app_before_challenge(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);
        $this->enrollTwoFactor($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('student.2fa.challenge'));

        $this->actingAs($user)
            ->get(route('settings.edit', ['tab' => 'security']))
            ->assertRedirect(route('student.2fa.challenge'));
    }

    public function test_student_completes_challenge_and_reaches_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);
        $secret = $this->enrollTwoFactor($user);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('student.2fa.challenge'));

        $this->post(route('student.2fa.challenge.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertCookie(StudentTwoFactorDevice::COOKIE)
            ->assertSessionHas(TwoFactorSession::KEY);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_trusted_device_skips_challenge_on_next_login(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);
        $this->enrollTwoFactor($user);

        $this->withCookie(
            StudentTwoFactorDevice::COOKIE,
            $this->devicePayload($user),
        )->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_expired_trusted_device_still_requires_challenge(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);
        $this->enrollTwoFactor($user);

        $this->withCookie(
            StudentTwoFactorDevice::COOKIE,
            $this->devicePayload($user, expired: true),
        )->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('student.2fa.challenge'));
    }

    public function test_instructor_with_2fa_skips_student_challenge_on_teach_login(): void
    {
        $user = User::factory()->create(['email' => 'instructor@example.com']);
        $user->assignRole(Role::Instructor->value);
        $this->enrollTwoFactor($user);

        $this->post(route('teach.login.store'), [
            'email' => 'instructor@example.com',
            'password' => 'password',
        ])->assertRedirect(route('teach.dashboard', absolute: false));

        $this->get(route('teach.dashboard'))->assertOk();
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

    private function devicePayload(User $user, bool $expired = false): string
    {
        $user->load('twoFactorSecret');

        return json_encode([
            'uid' => $user->id,
            'cid' => $user->twoFactorSecret?->confirmed_at?->timestamp,
            'exp' => ($expired ? now()->subDay() : now()->addDays(30))->timestamp,
        ], JSON_THROW_ON_ERROR);
    }
}
