<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Database\Seeders\SettingsSeeder;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_setting_service_casts_values_and_uses_cache(): void
    {
        $service = app(SettingService::class);

        $service->set('general.site_name', 'MedLearn', 'string');
        $service->set('features.maintenance_mode', true, 'boolean');
        $service->set('features.free_test_question_limit', 30, 'integer');
        $service->set('integrations.payload', ['enabled' => true], 'json');

        $this->assertSame('MedLearn', $service->get('general.site_name'));
        $this->assertTrue($service->get('features.maintenance_mode'));
        $this->assertSame(30, $service->get('features.free_test_question_limit'));
        $this->assertSame(['enabled' => true], $service->get('integrations.payload'));

        $queries = 0;
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            if (str_contains($event->sql, 'settings')) {
                $queries++;
            }
        });

        $this->assertSame('MedLearn', $service->get('general.site_name'));
        $this->assertSame(0, $queries);
    }

    public function test_setting_service_forgets_cache_when_setting_is_updated(): void
    {
        $service = app(SettingService::class);

        Setting::query()
            ->where('group', 'general')
            ->where('key', 'site_name')
            ->update(['value' => 'Old name']);

        $this->assertSame('Old name', $service->get('general.site_name'));

        Setting::query()
            ->where('group', 'general')
            ->where('key', 'site_name')
            ->update(['value' => 'Changed outside cache']);

        $this->assertSame('Old name', $service->get('general.site_name'));

        $service->set('general.site_name', 'Saved name', 'string');

        $this->assertSame('Saved name', $service->get('general.site_name'));
    }

    public function test_super_admin_can_view_and_update_settings(): void
    {
        $admin = $this->staffUser(Role::SuperAdmin);

        $this->actingAsStaff($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Cài đặt hệ thống')
            ->assertSee('Cấu hình chung')
            ->assertSee('Báo cáo')
            ->assertSee('AI Tutor');

        $this->flushSession();
        $this->actingAsStaff($admin)
            ->post(route('admin.settings.update'), [
                'settings' => [
                    'general' => [
                        'site_name' => 'Y khoa Pro',
                        'support_email' => 'support@example.com',
                        'support_hotline' => '1900 0000',
                        'fanpage_url' => 'https://facebook.com/ykhoa',
                        'zalo_url' => 'https://zalo.me/19000000',
                        'seo_description' => 'Nền tảng ôn thi y khoa.',
                    ],
                    'features' => [
                        'registration_enabled' => '1',
                        'free_test_question_limit' => '25',
                    ],
                    'integrations' => [
                        'livekit_url' => 'https://livekit.example.com',
                        'livekit_api_key' => 'lk_api_key',
                        'notification_webhook_url' => 'https://hooks.example.com/admin',
                    ],
                    'reports' => [
                        'cache_warm_interval_days' => '7',
                    ],
                    'partner' => [
                        'attribution_window_days' => '7',
                        'default_commission_rate_percent' => '10',
                        'default_invite_expires_days' => '7',
                        'default_invite_max_uses' => '0',
                        'commission_on_renewals' => '1',
                        'first_payment_window_days' => '0',
                        'min_payout_cents' => '0',
                        'overwrite_attribution' => '0',
                        'require_active_partner' => '1',
                    ],
                    'ai_tutor' => [
                        'free_daily_limit' => '5',
                        'premium_daily_limit' => '50',
                        'history_max_messages' => '6',
                        'response_cache' => '1',
                        'response_cache_ttl_days' => '7',
                        'tutor_model' => 'gpt-4.1-mini',
                        'max_output_tokens' => '800',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('settings', [
            'group' => 'general',
            'key' => 'site_name',
            'value' => 'Y khoa Pro',
            'type' => 'string',
        ]);

        $this->assertDatabaseHas('settings', [
            'group' => 'reports',
            'key' => 'cache_warm_interval_days',
            'value' => '7',
            'type' => 'integer',
        ]);

        $this->assertDatabaseHas('settings', [
            'group' => 'ai_tutor',
            'key' => 'free_daily_limit',
            'value' => '5',
            'type' => 'integer',
        ]);

        $this->assertFalse(setting('features.maintenance_mode'));
        $this->assertSame(25, setting('features.free_test_question_limit'));
        $this->assertSame(7, setting('reports.cache_warm_interval_days'));
        $this->assertSame(7, \Modules\Admin\Support\AdminReportCache::warmIntervalDays());
        $this->assertSame(5, \Modules\AiAssistant\Support\AiTutorSettings::freeDailyLimit());
        $this->assertSame(50, \Modules\AiAssistant\Support\AiTutorSettings::premiumDailyLimit());
    }

    public function test_student_cannot_access_admin_settings(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_public_header_footer_read_general_settings(): void
    {
        $service = app(SettingService::class);
        $service->set('general.site_name', 'Y khoa Pro', 'string');
        $service->set('general.support_hotline', '1900 0000', 'string');
        $service->set('general.fanpage_url', 'https://facebook.com/ykhoa', 'string');

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Y khoa Pro')
            ->assertSee('1900 0000')
            ->assertSee('https://facebook.com/ykhoa');
    }

    public function test_default_settings_are_seeded_on_fresh_database(): void
    {
        $this->assertDatabaseHas('settings', [
            'group' => 'general',
            'key' => 'site_name',
        ]);
        $this->assertSame(config('app.name'), setting('general.site_name'));
        $this->assertSame(20, setting('features.free_test_question_limit'));
    }

    public function test_public_seo_uses_setting_description_when_available(): void
    {
        app(SettingService::class)->set('general.seo_description', 'Mô tả SEO hệ thống từ settings.', 'string');

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Mô tả SEO hệ thống từ settings.', false);
    }

    public function test_registration_can_be_disabled_from_settings(): void
    {
        app(SettingService::class)->set('features.registration_enabled', false, 'boolean');

        $payload = [
            'name' => 'Learner',
            'email' => 'learner@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'terms' => '1',
        ];

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), $payload)->assertNotFound();
    }

    public function test_maintenance_mode_blocks_public_pages_but_keeps_admin_available(): void
    {
        $service = app(SettingService::class);
        $service->set('general.site_name', 'Y khoa Pro', 'string');
        $service->set('features.maintenance_mode', true, 'boolean');

        $this->get(route('landing.home'))
            ->assertStatus(503)
            ->assertSee('Y khoa Pro đang bảo trì');

        $this->get(route('admin.login'))->assertOk();
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }
}
