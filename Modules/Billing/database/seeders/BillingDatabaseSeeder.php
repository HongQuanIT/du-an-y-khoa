<?php

declare(strict_types=1);

namespace Modules\Billing\Database\Seeders;

use App\Support\Enums\Entitlement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Billing\Models\Institution;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\RedeemCode;

class BillingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $free = Plan::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Miễn phí',
                'description' => 'Cơ bản cho người mới bắt đầu',
                'price_cents' => 0,
                'currency' => 'VND',
                'entitlements' => [],
                'features' => [
                    '20 câu hỏi/ngày',
                    'Thư viện giới hạn',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        PlanPrice::query()->updateOrCreate(
            ['plan_id' => $free->id, 'slug' => 'free'],
            [
                'label' => 'Miễn phí',
                'price_cents' => 0,
                'currency' => 'VND',
                'duration_days' => null,
                'billing_type' => 'none',
                'cta_label' => 'Bắt đầu ngay',
                'is_featured' => false,
                'is_public' => true,
                'sort_order' => 0,
            ],
        );

        $premiumEntitlements = [
            Entitlement::QbankFull->value,
            Entitlement::LibraryFull->value,
            Entitlement::AiTutor->value,
            Entitlement::AnalyticsAdvanced->value,
            Entitlement::ExamSimulation->value,
        ];

        $premiumFeatures = [
            'Toàn bộ QBank & Thư viện',
            'AI Mentor không giới hạn',
            'Mô phỏng thi thật',
            'Phân tích lỗ hổng kiến thức',
            'Ưu tiên hỗ trợ 24/7',
        ];

        $premium = Plan::query()->updateOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'description' => 'Giải pháp ôn thi toàn diện',
                'price_cents' => 199_000,
                'currency' => 'VND',
                'entitlements' => $premiumEntitlements,
                'features' => $premiumFeatures,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        $monthlyPrice = 199_000;

        $skus = [
            [
                'slug' => 'premium-monthly',
                'label' => '1 tháng',
                'price_cents' => $monthlyPrice,
                'duration_days' => 30,
                'billing_type' => 'recurring',
                'savings_percent' => null,
                'badge_label' => null,
                'cta_label' => 'Nâng cấp theo tháng',
                'is_featured' => false,
                'sort_order' => 10,
            ],
            [
                'slug' => 'premium-1y',
                'label' => '1 năm',
                'price_cents' => 1_790_000,
                'duration_days' => 365,
                'billing_type' => 'prepaid',
                'savings_percent' => 25,
                'badge_label' => 'Giá theo năm',
                'cta_label' => 'Mua gói 1 năm',
                'is_featured' => true,
                'sort_order' => 20,
            ],
            [
                'slug' => 'premium-2y',
                'label' => '2 năm',
                'price_cents' => 2_990_000,
                'duration_days' => 730,
                'billing_type' => 'prepaid',
                'savings_percent' => 37,
                'badge_label' => null,
                'cta_label' => 'Mua gói 2 năm',
                'is_featured' => false,
                'sort_order' => 30,
            ],
            [
                'slug' => 'premium-3y',
                'label' => '3 năm',
                'price_cents' => 3_990_000,
                'duration_days' => 1095,
                'billing_type' => 'prepaid',
                'savings_percent' => 44,
                'badge_label' => 'Tiết kiệm nhất',
                'cta_label' => 'Mua gói 3 năm',
                'is_featured' => false,
                'sort_order' => 40,
            ],
        ];

        foreach ($skus as $sku) {
            PlanPrice::query()->updateOrCreate(
                ['plan_id' => $premium->id, 'slug' => $sku['slug']],
                array_merge($sku, [
                    'currency' => 'VND',
                    'is_public' => true,
                ]),
            );
        }

        // Promo code + fake institution are local/dev only.
        if (app()->environment('local')) {
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

            $this->command?->info('Billing: gói Free/Premium + SKU bảng giá, mã MEDLEARN2026, giấy phép @medlearn.local.');

            return;
        }

        $this->command?->info('Billing: gói Free/Premium + SKU bảng giá (không seed promo/institution).');
    }
}
