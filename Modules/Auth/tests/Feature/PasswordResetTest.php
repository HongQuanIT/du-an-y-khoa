<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_is_accessible_from_guest_and_authenticated_user(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Quên mật khẩu');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_settings_security_shows_forgot_password_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('Quên mật khẩu?');
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1!']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }
}
