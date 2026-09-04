<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Admin\Enums\ContactInquiryStatus;
use Modules\Admin\Enums\ContactSubject;
use Modules\Admin\Models\AuditLog;
use Modules\Admin\Models\ContactInquiry;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionSession;
use Tests\TestCase;

final class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::forget('admin:dashboard:aggregates');
    }

    public function test_admin_dashboard_shows_real_kpis_and_operational_sections(): void
    {
        $admin = $this->staffUser(Role::Admin);

        User::factory()->count(3)->create()->each(function (User $user): void {
            $user->assignRole(Role::Student->value);
        });

        DailyLearningStat::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'date' => now()->toDateString(),
            'questions_answered' => 12,
            'correct_answers' => 8,
            'study_seconds' => 600,
            'completed_sessions' => 1,
            'daily_goal_reached' => true,
        ]);

        $question = Question::factory()->free()->withOptions()->create();
        $learner = User::factory()->create();
        $session = QuestionSession::factory()->for($learner)->create();
        QuestionFeedback::query()->create([
            'user_id' => $learner->getKey(),
            'question_id' => $question->getKey(),
            'question_session_id' => $session->getKey(),
            'target' => 'question',
            'category' => 'other',
            'message' => 'Test feedback',
            'status' => QuestionFeedback::STATUS_PENDING,
        ]);

        ContactInquiry::query()->create([
            'reference' => ContactInquiry::generateReference(),
            'name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'subject' => ContactSubject::Payment,
            'message' => 'Cần hỗ trợ thanh toán',
            'status' => ContactInquiryStatus::New,
        ]);

        AuditLog::query()->create([
            'event_id' => (string) Str::uuid(),
            'actor_id' => $admin->id,
            'action' => 'admin.contact.update',
            'created_at' => now(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tổng quan vận hành')
            ->assertSee('DAU')
            ->assertSee('MAU')
            ->assertSee('Đăng ký mới (7 ngày)')
            ->assertSee('Học viên Premium')
            ->assertSee('MRR (ước tính)')
            ->assertSee('Doanh thu tháng')
            ->assertSee('Tăng trưởng người dùng')
            ->assertSee('Mức độ tương tác')
            ->assertSee('Doanh thu theo tháng')
            ->assertSee('Câu hỏi published')
            ->assertSee('Feedback chờ xử lý')
            ->assertSee('Liên hệ mới')
            ->assertSee('Sức khỏe hệ thống')
            ->assertSee('Thanh toán')
            ->assertSee('Phản hồi câu hỏi')
            ->assertSee('Hoạt động quản trị gần đây')
            ->assertSee('Thao tác nhanh')
            ->assertSee('Cập nhật');
    }

    public function test_content_editor_without_billing_permission_does_not_see_billing_kpis(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->assertFalse($editor->can(Permission::BillingManage->value));
        $this->assertTrue($editor->can(Permission::QuestionView->value));

        $this->actingAsStaff($editor)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Câu hỏi published')
            ->assertDontSee('Học viên Premium')
            ->assertDontSee('Premium sắp hết hạn')
            ->assertDontSee('MRR (ước tính)')
            ->assertDontSee('Tăng trưởng người dùng');
    }

    public function test_dashboard_shows_alert_when_feedback_backlog_exists(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = Question::factory()->free()->withOptions()->create();
        $learner = User::factory()->create();
        $session = QuestionSession::factory()->for($learner)->create();

        for ($i = 0; $i < 3; $i++) {
            QuestionFeedback::query()->create([
                'user_id' => $learner->getKey(),
                'question_id' => $question->getKey(),
                'question_session_id' => $session->getKey(),
                'target' => 'question',
                'category' => 'other',
                'message' => "Feedback {$i}",
                'status' => QuestionFeedback::STATUS_PENDING,
            ]);
        }

        $this->actingAsStaff($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Feedback chờ xử lý')
            ->assertSee('Phản hồi câu hỏi')
            ->assertSee('ổn định');
    }

    public function test_dashboard_audit_feed_requires_audit_permission(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $this->assertFalse($editor->can(Permission::AuditView->value));

        $this->actingAsStaff($editor)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Hoạt động quản trị gần đây');
    }

    public function test_dashboard_audit_feed_shows_recent_log_for_auditor(): void
    {
        $admin = $this->staffUser(Role::Admin);

        AuditLog::query()->create([
            'event_id' => (string) Str::uuid(),
            'actor_id' => $admin->id,
            'action' => 'admin.contact.update',
            'created_at' => now(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Hoạt động quản trị gần đây');
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
