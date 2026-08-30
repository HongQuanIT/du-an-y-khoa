<?php

declare(strict_types=1);

namespace Modules\Partner\Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;
use Modules\Partner\Support\PartnerSettings;

/**
 * Upserts partner.* settings so existing installs get new keys without wiping other settings.
 */
final class PartnerSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(SettingService::class);

        foreach (PartnerSettings::defaultRows() as $key => $row) {
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
