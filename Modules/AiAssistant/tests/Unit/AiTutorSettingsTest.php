<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Tests\Unit;

use App\Models\User;
use App\Services\SettingService;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\AiAssistant\Services\AiQuotaService;
use Modules\AiAssistant\Support\AiTutorSettings;
use Tests\TestCase;

final class AiTutorSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_settings_override_env_config_defaults(): void
    {
        config([
            'aiassistant.free_daily_limit' => 10,
            'aiassistant.premium_daily_limit' => 100,
            'aiassistant.history_max_messages' => 8,
            'aiassistant.response_cache' => true,
        ]);

        $this->assertSame(10, AiTutorSettings::freeDailyLimit());
        $this->assertSame(100, AiTutorSettings::premiumDailyLimit());

        $settings = app(SettingService::class);
        $settings->set('ai_tutor.free_daily_limit', 3, 'integer');
        $settings->set('ai_tutor.premium_daily_limit', 40, 'integer');
        $settings->set('ai_tutor.history_max_messages', 4, 'integer');
        $settings->set('ai_tutor.response_cache', false, 'boolean');
        $settings->set('ai_tutor.response_cache_ttl_days', 3, 'integer');
        $settings->set('ai_tutor.tutor_model', 'gpt-4.1', 'string');
        $settings->set('ai_tutor.max_output_tokens', 500, 'integer');

        $this->assertSame(3, AiTutorSettings::freeDailyLimit());
        $this->assertSame(40, AiTutorSettings::premiumDailyLimit());
        $this->assertSame(4, AiTutorSettings::historyMaxMessages());
        $this->assertFalse(AiTutorSettings::responseCacheEnabled());
        $this->assertSame(3, AiTutorSettings::responseCacheTtlDays());
        $this->assertSame(3 * 86400, AiTutorSettings::responseCacheTtlSeconds());
        $this->assertSame('gpt-4.1', AiTutorSettings::tutorModel());
        $this->assertSame(500, AiTutorSettings::maxOutputTokens());

        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);
        $this->assertSame(3, app(AiQuotaService::class)->limitFor($user));
    }

    public function test_seeder_upserts_missing_keys_only(): void
    {
        $settings = app(SettingService::class);
        $settings->set('ai_tutor.free_daily_limit', 99, 'integer');

        $this->seed(\Modules\AiAssistant\Database\Seeders\AiTutorSettingsSeeder::class);

        $this->assertSame(99, setting('ai_tutor.free_daily_limit'));
        $this->assertNotNull(setting('ai_tutor.premium_daily_limit'));
        $this->assertNotNull(setting('ai_tutor.response_cache'));
    }
}
