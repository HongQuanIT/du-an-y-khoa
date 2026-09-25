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
use Modules\QuestionBank\Enums\ReviewFlagOutcome;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewerFlag;
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

    public function test_reviewer_shell_and_profile_match_admin_design_system(): void
    {
        $reviewer = User::factory()->create(['name' => 'Reviewer UI']);
        $reviewer->assignRole(Role::Reviewer->value);

        $this->actingAs($reviewer)
            ->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('Cổng reviewer')
            ->assertSee('Review câu hỏi')
            ->assertSee('Quản lý tài khoản')
            ->assertSee(route('reviewer.notifications.index'), false)
            ->assertSee(route('reviewer.logout'), false)
            ->assertDontSee('account_circle')
            ->assertDontSee(route('admin.logout'), false);

        $this->get(route('reviewer.profile.show'))
            ->assertOk()
            ->assertSee('Hồ sơ reviewer')
            ->assertSee('Thông tin tài khoản')
            ->assertSee('Ảnh đại diện')
            ->assertDontSee(route('reviewer.profile.show', ['tab' => 'appearance']), false)
            ->assertDontSee('Chế độ giao diện');

        $this->get(route('reviewer.profile.show', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('Đổi mật khẩu')
            ->assertSee('Xác thực hai bước')
            ->assertDontSee(route('reviewer.profile.show', ['tab' => 'appearance']), false)
            ->assertDontSee('Chế độ giao diện');

        $this->get(route('reviewer.profile.show', ['tab' => 'appearance']))
            ->assertOk()
            ->assertSee('Thông tin tài khoản')
            ->assertDontSee('Chế độ giao diện');
    }

    public function test_reviewer_dashboard_and_notification_permissions_control_shell(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName(Role::Reviewer->value, 'web');
        $role->revokePermissionTo(['reviewer_dashboard.view', 'reviewer_notification.view']);

        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::Reviewer->value);

        $this->actingAs($reviewer)
            ->get(route('reviewer.dashboard'))
            ->assertForbidden();

        $this->get(route('reviewer.questions.flags.index'))
            ->assertOk()
            ->assertDontSee('Tổng quan')
            ->assertDontSee(route('reviewer.notifications.index'), false)
            ->assertSee('Review câu hỏi');

        $this->get(route('reviewer.notifications.index'))->assertForbidden();
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
            ->assertRedirect(route('reviewer.questions.flags.show', $pending));
        $this->get(route('reviewer.questions.flags.show', $pending->fresh()))
            ->assertOk()
            ->assertSee('Bạn đã chọn');
        $this->get(route('reviewer.questions.flags.index', ['tab' => 'done']))
            ->assertOk()->assertSee($pending->code);
    }

    public function test_reviewer_is_redirected_to_history_after_question_is_published(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::Reviewer->value);
        $question = Question::factory()->create(['status' => QuestionStatus::InFlagReview->value]);

        $question->forceFill([
            'reviewer_1_id' => $reviewer->getKey(),
            'reviewer_1_flag' => ReviewerFlag::Green->value,
            'status' => QuestionStatus::Published,
        ])->save();

        $this->actingAs($reviewer)
            ->get(route('reviewer.questions.flags.show', $question->fresh()))
            ->assertRedirect(route('reviewer.questions.flags.index', ['tab' => 'done']))
            ->assertSessionHas('status', 'Câu hỏi đã được xuất bản và không còn trong hàng đợi review.');
    }

    public function test_dashboard_shows_personal_review_metrics_and_eligible_queue(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::Reviewer->value);
        $other = User::factory()->create();
        $pending = Question::factory()->create(['status' => QuestionStatus::InFlagReview->value]);
        $own = Question::factory()->create(['status' => QuestionStatus::InFlagReview->value, 'created_by' => $reviewer->getKey()]);
        $reviewed = Question::factory()->create(['status' => QuestionStatus::PendingPublish->value, 'reviewer_1_id' => $reviewer->getKey(), 'reviewer_1_flag' => ReviewerFlag::Green->value]);
        $otherReviewed = Question::factory()->create(['status' => QuestionStatus::PendingPublish->value]);

        QuestionReviewerFlag::query()->create([
            'question_id' => $reviewed->getKey(), 'review_cycle' => 1, 'reviewer_id' => $reviewer->getKey(),
            'flag' => ReviewerFlag::Green, 'reviewed_at' => now(), 'outcome' => ReviewFlagOutcome::FalsePositive,
        ]);
        QuestionReviewerFlag::query()->create([
            'question_id' => $otherReviewed->getKey(), 'review_cycle' => 1, 'reviewer_id' => $other->getKey(),
            'flag' => ReviewerFlag::Red, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('Nhịp độ review')
            ->assertSee('Phân bố cờ')
            ->assertSee('Tỷ lệ cờ xanh')
            ->assertSee('1 xanh · 0 đỏ')
            ->assertSee('Bị đánh dấu sai')
            ->assertSee($pending->code)
            ->assertSee($reviewed->code)
            ->assertDontSee($own->code)
            ->assertDontSee($otherReviewed->code);

        $response->assertViewHas('charts', function (array $charts): bool {
            return count($charts[0]['labels']) === 30
                && $charts[0]['datasets'][0]['data'][29] === 1
                && $charts[1]['datasets'][0]['data'] === [1, 0];
        });
    }

    public function test_dashboard_has_useful_empty_state(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::Reviewer->value);

        $this->actingAs($reviewer)->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('Đã xử lý hết hàng đợi')
            ->assertSee('Hiện không có câu hỏi chờ review.')
            ->assertSee('Bạn chưa có lượt review nào.');
    }

    public function test_dashboard_hides_queue_when_question_view_permission_is_removed(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName(Role::Reviewer->value, 'web');
        $role->revokePermissionTo('question_flag.view');
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::Reviewer->value);
        $pending = Question::factory()->create(['status' => QuestionStatus::InFlagReview->value]);

        $this->actingAs($reviewer)->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('Chưa có quyền xem câu hỏi')
            ->assertDontSee($pending->code)
            ->assertDontSee(route('reviewer.questions.flags.index'), false);
    }
}
