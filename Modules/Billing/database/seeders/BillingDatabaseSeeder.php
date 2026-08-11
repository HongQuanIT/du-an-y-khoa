<?php

declare(strict_types=1);

namespace Modules\Billing\Database\Seeders;

use App\Support\Enums\Entitlement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Billing\Models\Institution;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\RedeemCode;

class BillingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $free = Plan::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'description' => 'Quyền truy cập cơ bản',
                'price_cents' => 0,
                'currency' => 'VND',
                'entitlements' => [],
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        $premium = Plan::query()->updateOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'description' => 'Toàn bộ Q-Bank, thư viện và phân tích nâng cao',
                'price_cents' => 990000,
                'currency' => 'VND',
                'entitlements' => [
                    Entitlement::QbankFull->value,
                    Entitlement::LibraryFull->value,
                    Entitlement::AnalyticsAdvanced->value,
                    Entitlement::ExamSimulation->value,
                ],
                'is_active' => true,
                'sort_order'  => 10,
            ],
        );

        RedeemCode::query()->updateOrCreate(
            ['code' => 'MEDLEARN2026'],
            [
                'plan_id' => $premium->getKey(),
                'duration_days' => 30,
                'max_uses' => 1000,
                'uses_count' => 0,
                'expires_at' => Carbon::now()->addYear(),
                'type' => 'promo',
            ],
        );

        Institution::query()->updateOrCreate(
            ['name' => 'Đại học Y Dược TP.HCM'],
            [
                'email_domains' => ['medlearn.local', 'student.medlearn.local'],
                'plan_id' => $premium->getKey(),
                'valid_until' => Carbon::parse('2026-09-18'),
                'is_active' => true,
            ],
        );

        $this->command?->info('Billing: gói Free/Premium, mã MEDLEARN2026, giấy phép @medlearn.local.');
    }
}
