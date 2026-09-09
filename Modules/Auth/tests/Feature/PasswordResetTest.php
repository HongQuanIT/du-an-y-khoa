<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\Auth\Notifications\ResetPasswordNotification;
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

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_email_is_branded_and_written_in_vietnamese(): void
    {
        Notification::fake();

        $user = User::factory()->create(['name' => 'Nguyễn Văn An']);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user): bool {
                $mail = $notification->toMail($user);
                $html = $mail->render();

                return $mail->subject === 'Đặt lại mật khẩu '.config('app.name')
                    && str_contains($html, 'Xin chào Nguyễn Văn An')
                    && str_contains($html, 'Đặt lại mật khẩu')
                    && str_contains($html, '60 phút');
            },
        );
    }

    public function test_forgot_password_does_not_reveal_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'unknown@example.com',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas(
                'status',
                'Nếu email tồn tại trong hệ thống, chúng tôi đã gửi liên kết đặt lại mật khẩu.',
            )
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_forgot_password_normalizes_the_email_address(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'student@example.com']);

        $this->post(route('password.email'), [
            'email' => '  STUDENT@EXAMPLE.COM  ',
        ])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
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

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1!']);
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPassword1!', $user->fresh()->password));
    }

    public function test_password_reset_token_cannot_be_reused(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1!']);
        $token = Password::createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ];

        $this->post(route('password.update'), $payload)
            ->assertRedirect(route('login'));

        $this->post(route('password.update'), [
            ...$payload,
            'password' => 'AnotherPassword1!',
            'password_confirmation' => 'AnotherPassword1!',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('NewPassword1!', $user->fresh()->password));
    }

    public function test_social_account_can_create_a_password_through_the_reset_flow(): void
    {
        $user = User::factory()->create(['password' => str()->password(64)]);
        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
            'provider_email' => $user->email,
            'provider_name' => $user->name,
        ]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => strtoupper($user->email),
            'password' => 'SocialPassword1!',
            'password_confirmation' => 'SocialPassword1!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('SocialPassword1!', $user->fresh()->password));
    }
}
