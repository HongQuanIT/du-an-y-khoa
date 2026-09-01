<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Payment;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionSession;
use Tests\TestCase;

final class AdminReportsCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_reports_catalog_and_category(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Trung tâm báo cáo')
            ->assertSee('Người dùng & Tăng trưởng')
            ->assertSee('Doanh thu & Churn');

        $this->flushSession();
        $this->actingAsStaff($admin)
            ->get(route('admin.reports.show-category', 'users'))
            ->assertOk()
            ->assertSee('DAU / MAU')
            ->assertSee('Đăng ký mới');
    }

    public function test_admin_can_view_dau_mau_report_with_real_data(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        DailyLearningStat::query()->create([
            'user_id' => $student->id,
            'date' => now()->toDateString(),
            'questions_answered' => 10,
            'correct_answers' => 7,
            'study_seconds' => 600,
            'completed_sessions' => 1,
            'daily_goal_reached' => true,
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.reports.show', ['category' => 'users', 'report' => 'dau-mau', 'range' => '30d']))
            ->assertOk()
            ->assertSee('DAU / MAU')
            ->assertSee('DAU hôm nay')
            ->assertSee('MAU (trong kỳ)')
            ->assertSee('Xuất CSV')
            ->assertDontSee('Phase 2');
    }

    public function test_admin_can_view_revenue_mrr_report(): void
    {
        $this->seed(BillingDatabaseSeeder::class);
        $admin = $this->staffUser(Role::Admin);

        Payment::query()->create([
            'amount_cents' => 199000,
            'currency' => 'VND',
            'method' => 'vnpay',
            'status' => 'succeeded',
            'provider' => 'vnpay',
            'paid_at' => now(),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.reports.show', ['category' => 'revenue', 'report' => 'mrr']))
            ->assertOk()
            ->assertSee('MRR & Doanh thu')
            ->assertSee('MRR (ước tính)')
            ->assertSee('Doanh thu kỳ');
    }

    public function test_admin_can_export_report_csv(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $response = $this->actingAsStaff($admin)
            ->get(route('admin.reports.export', ['category' => 'content', 'report' => 'coverage']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('Trạng thái', $response->streamedContent());
    }

    public function test_content_editor_without_report_permission_is_forbidden(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $this->assertFalse($editor->can(Permission::ReportExport->value));

        $this->actingAsStaff($editor)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    public function test_unknown_report_returns_not_found(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get(route('admin.reports.show', ['category' => 'users', 'report' => 'unknown']))
            ->assertNotFound();
    }

    public function test_admin_without_billing_cannot_open_revenue_report(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $editor->givePermissionTo(Permission::ReportExport->value);

        $this->assertFalse($editor->can(Permission::BillingManage->value));

        $this->actingAsStaff($editor)
            ->get(route('admin.reports.show', ['category' => 'revenue', 'report' => 'mrr']))
            ->assertForbidden();
    }

    public function test_flags_report_shows_feedback_categories(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = Question::factory()->free()->withOptions()->create();
        $learner = User::factory()->create();
        $session = QuestionSession::factory()->for($learner)->create();

        QuestionFeedback::query()->create([
            'user_id' => $learner->id,
            'question_id' => $question->id,
            'question_session_id' => $session->id,
            'target' => 'question',
            'category' => 'incorrect',
            'message' => 'Sai đáp án',
            'status' => QuestionFeedback::STATUS_PENDING,
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.reports.show', ['category' => 'content', 'report' => 'flags']))
            ->assertOk()
            ->assertSee('Feedback trong kỳ')
            ->assertSee('Nội dung không chính xác');
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
