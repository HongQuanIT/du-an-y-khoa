<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\Auth\Models\LearnerProfile;
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

    public function test_authenticated_learner_can_open_reset_link_instead_of_being_redirected_to_dashboard(): void
    {
        $signedInUser = User::factory()->create();
        LearnerProfile::query()->create(['user_id' => $signedInUser->id]);
        $token = Password::createToken($signedInUser);

        $this->actingAs($signedInUser)
            ->get(route('password.reset', [
                'token' => $token,
                'email' => $signedInUser->email,
            ]))
            ->assertOk()
            ->assertSee('Đặt lại mật khẩu')
            ->assertSee($signedInUser->email);
    }

    public function test_authenticated_user_can_only_request_and_open_password_reset_for_self(): void
    {
        Notification::fake();
        $signedInUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($signedInUser)
            ->post(route('password.email'), ['email' => $otherUser->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($signedInUser, ResetPasswordNotification::class);
        Notification::assertNotSentTo($otherUser, ResetPasswordNotification::class);

        $otherToken = Password::createToken($otherUser);
        $this->get(route('password.reset', [
            'token' => $otherToken,
            'email' => $otherUser->email,
        ]))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'Bạn chỉ có thể đặt lại mật khẩu cho tài khoản đang đăng nhập.',
            ]);
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

    public function test_repeated_request_for_the_same_email_is_throttled_without_duplicate_token_error(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        $this->assertSame(
            1,
            \DB::table('password_reset_tokens')->where('email', $user->email)->count(),
        );
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
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

    public function test_forgot_password_rejects_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'unknown@example.com',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Email chưa tồn tại trong hệ thống.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_blocked_accounts_cannot_request_a_password_reset_link(): void
    {
        Notification::fake();

        foreach ([UserStatus::Suspended, UserStatus::Banned] as $status) {
            $user = User::factory()->create(['status' => $status]);

            $this->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect()
                ->assertSessionHasErrors([
                    'email' => 'Tài khoản này đang bị khóa hoặc không còn hoạt động.',
                ]);

            Notification::assertNotSentTo($user, ResetPasswordNotification::class);
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        }
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

    public function test_one_ip_can_only_request_password_reset_three_times_per_day(): void
    {
        Notification::fake();
        $users = User::factory()->count(4)->create();
        $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.23']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $client->post(route('password.email'), ['email' => $users[$attempt]->email])
                ->assertRedirect()
                ->assertSessionMissing('errors');
        }

        $client->post(route('password.email'), ['email' => $users[3]->email])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Địa chỉ IP này đã yêu cầu đặt lại mật khẩu quá 3 lần trong ngày. Vui lòng thử lại vào ngày mai.',
            ]);

        Notification::assertCount(3);
        Notification::assertNotSentTo($users[3], ResetPasswordNotification::class);
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

    public function test_blocked_account_cannot_use_an_existing_password_reset_token(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1!']);
        $token = Password::createToken($user);
        $user->forceFill(['status' => UserStatus::Suspended])->save();

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
        $user = User::factory()->create([
            'password' => str()->password(64),
            'password_set_at' => null,
        ]);
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
        $this->assertNotNull($user->fresh()->password_set_at);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'SocialPassword1!',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
