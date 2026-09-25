<?php

declare(strict_types=1);

namespace Modules\Reviewer\Tests\Feature;

use App\Models\User;
use App\Support\Auth\WebSessionManager;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class ReviewerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reviewer_login_and_admin_boundary(): void
    {
        $reviewer = User::factory()->create(['email' => 'reviewer@example.com']);
        $reviewer->assignRole(Role::Reviewer->value);

        $this->get(route('reviewer.dashboard'))->assertRedirect(route('reviewer.login'));
        $this->post(route('reviewer.login.store'), [
            'email' => 'reviewer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('reviewer.dashboard', absolute: false));

        $this->assertAuthenticatedAs($reviewer);

        session()->forget(WebSessionManager::BOUND_SESSION_ID);

        $this->actingAs($reviewer)->get(route('reviewer.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get('/admin/questions/flags')->assertNotFound();
    }

    public function test_admin_cannot_enter_reviewer_portal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($admin)->get(route('reviewer.dashboard'))->assertForbidden();
    }

    public function test_reviewer_two_factor_challenge_uses_reviewer_portal(): void
    {
        $reviewer = User::factory()->create(['email' => 'reviewer-2fa@example.com']);
        $reviewer->assignRole(Role::Reviewer->value);
        $secret = (new TotpService)->generateSecret();
        TwoFactorSecret::query()->create([
            'user_id' => $reviewer->getKey(),
            'secret' => $secret,
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        $this->post(route('reviewer.login.store'), [
            'email' => $reviewer->email,
            'password' => 'password',
        ])->assertRedirect(route('reviewer.2fa.challenge'));

        session()->forget(WebSessionManager::BOUND_SESSION_ID);
        $this->actingAs($reviewer)
            ->post(route('reviewer.2fa.challenge.verify'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route('reviewer.dashboard', absolute: false));
    }

    public function test_reviewer_can_flag_pending_question_and_only_see_own_history(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::Reviewer->value);
        $pending = Question::factory()->create(['status' => QuestionStatus::InFlagReview->value]);
        $hidden = Question::factory()->create(['status' => QuestionStatus::Draft->value]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.questions.flags.index'))
            ->assertOk()
            ->assertSee($pending->code)
            ->assertDontSee($hidden->code);

        $this->get(route('reviewer.questions.flags.show', $hidden))->assertForbidden();
        $this->post(route('reviewer.questions.flags.store', $pending), ['flag' => ReviewerFlag::Red->value])
            ->assertSessionHasErrors('note');
        $this->post(route('reviewer.questions.flags.store', $pending), ['flag' => ReviewerFlag::Green->value])
            ->assertRedirect(route('reviewer.questions.flags.index', ['tab' => 'done']));
        $this->get(route('reviewer.questions.flags.index', ['tab' => 'done']))
            ->assertOk()->assertSee($pending->code);
    }
}
