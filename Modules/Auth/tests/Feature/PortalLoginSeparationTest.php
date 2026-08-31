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

final class PortalLoginSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_visiting_admin_is_sent_to_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_student_can_login_via_student_portal(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);

        $this->post('/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_cannot_login_via_student_portal(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $user->assignRole(Role::Admin->value);

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_staff_without_2fa_reaches_admin_dashboard_after_login(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $user->assignRole(Role::SuperAdmin->value);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_with_2fa_is_sent_to_challenge_after_admin_login(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $user->assignRole(Role::Admin->value);
        $this->enrollTwoFactor($user);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.2fa.challenge'));
    }

    public function test_student_cannot_login_via_admin_portal(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $user->assignRole(Role::Student->value);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => 'student@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_logout_returns_to_admin_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::ContentEditor->value);

        $this->actingAs($user)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_staff_cannot_open_learner_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);
        $this->enrollTwoFactor($user);

        $this->actingAs($user)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_dashboard_requires_2fa_session(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::SuperAdmin->value);
        $this->enrollTwoFactor($user);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.2fa.challenge'));
    }

    public function test_admin_dashboard_ok_after_2fa_challenge(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::SuperAdmin->value);
        $secret = $this->enrollTwoFactor($user);

        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->actingAs($user)
            ->post(route('admin.2fa.challenge.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'admin.login',
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Quản trị hệ thống', false)
            ->assertSee('Tổng quan vận hành', false);
    }

    public function test_content_editor_menu_hides_user_management(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::ContentEditor->value);
        $this->enrollTwoFactor($user);

        $this->actingAs($user)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Câu hỏi')
            ->assertDontSee('Người dùng');
    }

    public function test_guest_visiting_teach_is_sent_to_teach_login(): void
    {
        $this->get('/teach')
            ->assertRedirect(route('teach.login'));
    }

    public function test_instructor_can_login_via_teach_portal(): void
    {
        $user = User::factory()->create(['email' => 'instructor@example.com']);
        $user->assignRole(Role::Instructor->value);

        $this->post(route('teach.login.store'), [
            'email' => 'instructor@example.com',
            'password' => 'password',
        ])->assertRedirect(route('teach.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_instructor_cannot_login_via_student_or_admin_portal(): void
    {
        $user = User::factory()->create(['email' => 'instructor@example.com']);
        $user->assignRole(Role::Instructor->value);

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'instructor@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => 'instructor@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_student_cannot_login_via_teach_portal(): void
    {
        $student = User::factory()->create(['email' => 'student@example.com']);
        $student->assignRole(Role::Student->value);

        $this->from(route('teach.login'))
            ->post(route('teach.login.store'), [
                'email' => 'student@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('teach.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_staff_cannot_login_via_teach_portal(): void
    {
        $admin = User::factory()->create(['email' => 'admin2@example.com']);
        $admin->assignRole(Role::Admin->value);

        $this->from(route('teach.login'))
            ->post(route('teach.login.store'), [
                'email' => 'admin2@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('teach.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_instructor_cannot_open_learner_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('teach.dashboard'));
    }

    public function test_instructor_cannot_open_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_teach_logout_returns_to_teach_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);

        $this->actingAs($user)
            ->post(route('teach.logout'))
            ->assertRedirect(route('teach.login'));

        $this->assertGuest();
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
