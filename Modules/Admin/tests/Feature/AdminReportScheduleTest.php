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
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Enums\ReportScheduleFrequency;
use Modules\Admin\Mail\ScheduledReportMail;
use Modules\Admin\Models\ReportSchedule;
use Modules\Admin\Support\AdminReportCache;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdminReportScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();
    }

    public function test_admin_can_create_weekly_report_schedule(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->post(route('admin.reports.schedules.store', ['category' => 'users', 'report' => 'dau-mau']), [
                'range_key' => '30d',
                'frequency' => 'weekly',
                'weekday' => 1,
                'send_time' => '08:00',
                'send_email' => '1',
                'recipients' => 'ops@example.com, finance@example.com',
            ])
            ->assertRedirect(route('admin.reports.show', [
                'category' => 'users',
                'report' => 'dau-mau',
                'range' => '30d',
            ]));

        $schedule = ReportSchedule::query()->first();
        $this->assertNotNull($schedule);
        $this->assertSame('users', $schedule->category_slug);
        $this->assertSame('dau-mau', $schedule->report_slug);
        $this->assertSame(ReportScheduleFrequency::Weekly, $schedule->frequency);
        $this->assertSame(1, $schedule->weekday);
        $this->assertSame(['ops@example.com', 'finance@example.com'], $schedule->recipients);
        $this->assertTrue($schedule->is_active);
        $this->assertTrue($schedule->send_email);
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_admin_can_create_schedule_without_email(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->post(route('admin.reports.schedules.store', ['category' => 'users', 'report' => 'dau-mau']), [
                'range_key' => '7d',
                'frequency' => 'daily',
                'send_time' => '09:00',
                'send_email' => '0',
                'recipients' => '',
            ])
            ->assertRedirect();

        $schedule = ReportSchedule::query()->first();
        $this->assertNotNull($schedule);
        $this->assertFalse($schedule->send_email);
        $this->assertSame([], $schedule->recipients);
    }

    public function test_admin_can_toggle_email_and_schedule(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $schedule = $this->makeSchedule($admin);

        $this->actingAsStaff($admin)
            ->post(route('admin.reports.schedules.toggle-email', $schedule))
            ->assertRedirect();

        $this->assertFalse($schedule->fresh()->send_email);

        $this->flushSession();
        $this->actingAsStaff($admin)
            ->post(route('admin.reports.schedules.toggle', $schedule))
            ->assertRedirect();

        $this->assertFalse($schedule->fresh()->is_active);
        $this->assertNull($schedule->fresh()->next_run_at);

        $this->flushSession();
        $this->actingAsStaff($admin)
            ->from(route('admin.reports.index'))
            ->post(route('admin.reports.schedules.destroy', $schedule))
            ->assertRedirect(route('admin.reports.index'));

        $this->assertDatabaseMissing('report_schedules', ['id' => $schedule->id]);
    }

    public function test_due_schedule_sends_mail_with_csv(): void
    {
        Mail::fake();

        $admin = $this->staffUser(Role::Admin);
        $schedule = $this->makeSchedule($admin, [
            'next_run_at' => now()->subMinute(),
            'is_active' => true,
            'send_email' => true,
        ]);

        $this->artisan('admin:report-schedules-run')
            ->assertSuccessful();

        Mail::assertQueued(ScheduledReportMail::class, function (ScheduledReportMail $mail) use ($schedule): bool {
            return $mail->schedule->is($schedule)
                && $mail->hasTo('ops@example.com');
        });

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue($schedule->next_run_at->greaterThan(now()));
    }

    public function test_due_schedule_skips_mail_when_email_disabled(): void
    {
        Mail::fake();

        $admin = $this->staffUser(Role::Admin);
        $this->makeSchedule($admin, [
            'next_run_at' => now()->subMinute(),
            'is_active' => true,
            'send_email' => false,
        ]);

        $this->artisan('admin:report-schedules-run')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_warm_cache_command_stores_report_snapshots(): void
    {
        $this->artisan('admin:reports-warm-cache')
            ->assertSuccessful();

        $cached = AdminReportCache::get('users', 'dau-mau', '30d');
        $this->assertNotNull($cached);
        $this->assertArrayHasKey('kpis', $cached);
        $this->assertNotNull(AdminReportCache::meta()['warmed_at']);
    }

    public function test_auto_warm_respects_interval_setting(): void
    {
        app(\App\Services\SettingService::class)->set('reports.cache_warm_interval_days', 7, 'integer');

        AdminReportCache::markWarmed(1);
        $this->assertFalse(AdminReportCache::shouldAutoWarm());

        // Giả lập lần warm cách đây 8 ngày
        \Illuminate\Support\Facades\Cache::put(AdminReportCache::metaKey(), [
            'warmed_at' => now()->subDays(8)->toIso8601String(),
            'count' => 1,
            'interval_days' => 7,
        ], 3600);

        $this->assertTrue(AdminReportCache::shouldAutoWarm());
        $this->assertSame(7, AdminReportCache::warmIntervalDays());
    }

    public function test_catalog_lists_schedules(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $this->makeSchedule($admin);

        $this->actingAsStaff($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Báo cáo đã lên lịch')
            ->assertSee('ops@example.com')
            ->assertSee('Lịch bật')
            ->assertSee('Email bật')
            ->assertSee('Tắt email');
    }

    public function test_editor_without_report_permission_cannot_schedule(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $this->assertFalse($editor->can(Permission::ReportExport->value));

        $this->actingAsStaff($editor)
            ->post(route('admin.reports.schedules.store', ['category' => 'content', 'report' => 'coverage']), [
                'range_key' => '7d',
                'frequency' => 'daily',
                'send_time' => '09:00',
                'send_email' => '1',
                'recipients' => 'editor@example.com',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_queue_report_refresh_and_poll_status(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->postJson(route('admin.reports.refresh', ['category' => 'users', 'report' => 'dau-mau']), [
                'range' => '30d',
            ])
            ->assertOk()
            ->assertJsonPath('queued', true)
            ->assertJsonPath('status', 'queued');

        $this->assertSame('queued', AdminReportCache::refreshStatus('users', 'dau-mau', '30d')['status']);

        // Mô phỏng worker xử lý job
        (new \Modules\Admin\Jobs\RefreshAdminReportCacheJob('users', 'dau-mau', '30d'))
            ->handle(\Modules\Admin\Actions\GetAdminReportDataAction::make());

        $this->assertSame('ready', AdminReportCache::refreshStatus('users', 'dau-mau', '30d')['status']);
        $this->assertNotNull(AdminReportCache::get('users', 'dau-mau', '30d'));

        $this->flushSession();
        $this->actingAsStaff($admin)
            ->getJson(route('admin.reports.refresh-status', [
                'category' => 'users',
                'report' => 'dau-mau',
                'range' => '30d',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'ready');
    }

    public function test_refresh_job_is_dispatched_to_queue(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->postJson(route('admin.reports.refresh', ['category' => 'users', 'report' => 'signups']), [
                'range' => '7d',
            ])
            ->assertOk()
            ->assertJsonPath('queued', true);

        \Illuminate\Support\Facades\Queue::assertPushed(\Modules\Admin\Jobs\RefreshAdminReportCacheJob::class);
        $this->assertSame('queued', AdminReportCache::refreshStatus('users', 'signups', '7d')['status']);
    }

    public function test_show_page_has_refresh_button(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get(route('admin.reports.show', ['category' => 'users', 'report' => 'dau-mau']))
            ->assertOk()
            ->assertSee('Làm mới báo cáo')
            ->assertSee('reportRefreshBanner');
    }

    public function test_admin_can_queue_warm_all_caches(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->from(route('admin.reports.index'))
            ->post(route('admin.reports.cache.warm-all'))
            ->assertRedirect(route('admin.reports.index'));

        \Illuminate\Support\Facades\Queue::assertPushed(\Modules\Admin\Jobs\WarmAllAdminReportCachesJob::class);
    }

    /** @param array<string, mixed> $overrides */
    private function makeSchedule(User $admin, array $overrides = []): ReportSchedule
    {
        return ReportSchedule::query()->create(array_merge([
            'created_by' => $admin->id,
            'category_slug' => 'users',
            'report_slug' => 'dau-mau',
            'range_key' => '30d',
            'frequency' => ReportScheduleFrequency::Weekly,
            'weekday' => 1,
            'day_of_month' => null,
            'send_time' => '08:00:00',
            'recipients' => ['ops@example.com'],
            'is_active' => true,
            'send_email' => true,
            'next_run_at' => now()->addDay(),
        ], $overrides));
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
