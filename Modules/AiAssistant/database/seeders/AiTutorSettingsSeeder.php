<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;
use Modules\AiAssistant\Support\AiTutorSettings;

/** Upserts ai_tutor.* so existing installs get keys without wiping other settings. */
final class AiTutorSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(SettingService::class);

        foreach (AiTutorSettings::defaultRows() as $key => $row) {
            [$group, $settingKey] = explode('.', $key, 2);

            $exists = Setting::query()
                ->where('group', $group)
                ->where('key', $settingKey)
                ->exists();

            if ($exists) {
                continue;
            }

            $service->set($key, $row['value'], $row['type']);
        }
    }
}
