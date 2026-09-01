<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::query()->exists()) {
            return;
        }

        $defaults = [
            ['general', 'site_name', config('app.name'), 'string'],
            ['general', 'support_email', '', 'string'],
            ['general', 'support_hotline', '', 'string'],
            ['general', 'fanpage_url', '', 'string'],
            ['general', 'zalo_url', '', 'string'],
            ['general', 'seo_description', 'Nền tảng ôn thi Y khoa với ngân hàng câu hỏi, học tập cá nhân hóa và AI Tutor.', 'string'],
            ['features', 'registration_enabled', true, 'boolean'],
            ['features', 'maintenance_mode', false, 'boolean'],
            ['features', 'free_test_question_limit', 20, 'integer'],
            ['integrations', 'livekit_url', '', 'string'],
            ['integrations', 'livekit_api_key', '', 'string'],
            ['integrations', 'notification_webhook_url', '', 'string'],
            ['reports', 'cache_warm_interval_days', 1, 'integer'],
        ];

        foreach ($defaults as [$group, $key, $value, $type]) {
            Setting::query()->create([
                'group' => $group,
                'key' => $key,
                'value' => match ($type) {
                    'boolean' => $value ? '1' : '0',
                    'integer' => (string) (int) $value,
                    default => (string) $value,
                },
                'type' => $type,
            ]);
        }
    }
}
