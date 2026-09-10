<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\Auth\Enums\AuthenticationMethod;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\SocialAccount;
use Tests\TestCase;

final class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, AuthDatabaseSeeder::class]);
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'google-client',
            'client_secret' => 'google-secret',
            'redirect' => '/auth/social/google/callback',
        ]);
        config()->set('services.facebook', [
            'enabled' => true,
            'client_id' => 'facebook-client',
            'client_secret' => 'facebook-secret',
            'redirect' => '/auth/social/facebook/callback',
        ]);
    }

    public function test_social_buttons_render_and_both_entry_modes_redirect_to_provider(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Tiếp tục với Google')
            ->assertSee('Tiếp tục với Facebook')
            ->assertDontSee('LinkedIn');

        Socialite::fake('google');
        $this->post(route('social.redirect', ['provider' => 'google']), [
            'mode' => 'register',
        ])->assertRedirect('https://socialite.fake/google/authorize');

        Socialite::fake('facebook');
        $this->post(route('social.redirect', ['provider' => 'facebook']), [
            'mode' => 'login',
        ])->assertRedirect('https://socialite.fake/facebook/authorize');
    }

    public function test_google_can_create_a_new_learner_and_continue_to_onboarding(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-new-123',
            'name' => 'Nguyễn Google',
            'email' => 'google-new@example.com',
            'avatar' => 'https://example.com/google-avatar.jpg',
        ]));

        $this->withSession([
            'social_auth.mode' => 'register',
            'registration_attribution' => ['utm_source' => 'facebook-ads'],
        ])->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('onboarding.profile'));

        $user = User::query()->where('email', 'google-new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole(Role::Student->value));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password_set_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-new-123',
        ]);
        $this->assertDatabaseHas('learner_profiles', [
            'user_id' => $user->id,
            'registration_method' => 'google',
            'utm_source' => 'facebook-ads',
        ]);
    }

    public function test_social_only_user_is_told_to_reset_password_before_email_login(): void
    {
        $user = User::factory()->create([
            'email' => 'social-passwordless@example.com',
            'password_set_at' => null,
        ]);
        $user->assignRole(Role::Student->value);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-passwordless-123',
            'provider_email' => $user->email,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'AnyPassword1!',
        ])->assertSessionHasErrors([
            'email' => 'Tài khoản này đăng ký bằng mạng xã hội và chưa có mật khẩu. Vui lòng bấm Quên mật khẩu để thiết lập mật khẩu.',
        ]);

        $this->assertGuest();
    }

    public function test_linked_facebook_account_can_login_without_creating_a_duplicate_user(): void
    {
        $user = User::factory()->create(['email' => 'linked@example.com']);
        $user->assignRole(Role::Student->value);
        LearnerProfile::query()->create(['user_id' => $user->id, 'onboarding_completed_at' => now()]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'provider_user_id' => 'facebook-linked-123',
            'provider_email' => $user->email,
        ]);
        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'facebook-linked-123',
            'name' => 'Facebook Updated',
            'email' => $user->email,
        ]));

        $this->withSession(['social_auth.mode' => 'login'])
            ->get(route('social.callback', ['provider' => 'facebook']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->where('email', $user->email)->count());
        $this->assertSame(AuthenticationMethod::Facebook, $user->fresh()->last_login_method);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_name' => 'Facebook Updated',
        ]);
    }

    public function test_matching_email_links_social_identity_without_creating_a_duplicate_user(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'Password1',
        ]);
        $user->assignRole(Role::Student->value);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-existing-123',
            'name' => 'Existing User',
            'email' => $user->email,
        ]));

        $this->withSession([
            'social_auth.mode' => 'register',
        ])->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('onboarding.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->where('email', $user->email)->count());
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-existing-123',
        ]);
    }

    public function test_google_then_facebook_with_the_same_email_share_one_user(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-shared-123',
            'name' => 'Shared Account',
            'email' => 'shared@example.com',
        ]));

        $this->withSession(['social_auth.mode' => 'register'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('onboarding.profile'));

        $user = User::query()->where('email', 'shared@example.com')->firstOrFail();
        $this->post(route('logout'));

        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'facebook-shared-123',
            'name' => 'Shared Account Facebook',
            'email' => 'shared@example.com',
        ]));

        $this->withSession(['social_auth.mode' => 'login'])
            ->get(route('social.callback', ['provider' => 'facebook']))
            ->assertRedirect(route('onboarding.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->where('email', 'shared@example.com')->count());
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-shared-123',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'facebook',
            'provider_user_id' => 'facebook-shared-123',
        ]);
        $this->assertSame(AuthenticationMethod::Facebook, $user->fresh()->last_login_method);
    }

    public function test_another_identity_cannot_replace_an_existing_provider_link(): void
    {
        $user = User::factory()->create(['email' => 'claimed@example.com']);
        $user->assignRole(Role::Student->value);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'original-google-id',
            'provider_email' => $user->email,
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'different-google-id',
            'email' => $user->email,
        ]));

        $this->withSession(['social_auth.mode' => 'login'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'original-google-id',
        ]);
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'different-google-id']);
    }

    public function test_login_mode_also_registers_an_unknown_social_user(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-unknown-123',
            'email' => 'unknown@example.com',
        ]));

        $this->withSession(['social_auth.mode' => 'login'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('onboarding.profile'));

        $this->assertDatabaseHas('users', ['email' => 'unknown@example.com']);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'google-unknown-123',
        ]);
    }

    public function test_social_registration_fails_safely_when_provider_does_not_return_email(): void
    {
        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'facebook-no-email',
            'email' => null,
        ]));

        $this->withSession([
            'social_auth.mode' => 'register',
        ])->get(route('social.callback', ['provider' => 'facebook']))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('social');

        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'facebook-no-email']);
    }

    public function test_suspended_user_cannot_login_with_a_linked_social_account(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended-social@example.com',
            'status' => UserStatus::Suspended,
        ]);
        $user->assignRole(Role::Student->value);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-suspended-123',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-suspended-123',
            'email' => $user->email,
        ]));

        $this->withSession(['social_auth.mode' => 'login'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
    }
}
