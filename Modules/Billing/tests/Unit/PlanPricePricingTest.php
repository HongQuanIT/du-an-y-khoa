<?php

declare(strict_types=1);

namespace Modules\Billing\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Tests\TestCase;

final class PlanPricePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_prepaid_with_savings_percent_derives_compare_at_price(): void
    {
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        $price = PlanPrice::query()->updateOrCreate(
            ['plan_id' => $premium->id, 'slug' => 'premium-1y'],
            [
                'label' => '1 năm',
                'price_cents' => 1_790_000,
                'duration_days' => 365,
                'billing_type' => 'prepaid',
                'savings_percent' => 25,
                'is_public' => true,
                'sort_order' => 20,
            ],
        );

        $price->refresh();

        $this->assertSame(2_386_667, $price->compare_at_price_cents);
        $this->assertSame(25, $price->displaySavingsPercent());
        $this->assertSame(2_386_667, $price->listPriceCents());
    }

    public function test_prepaid_without_savings_auto_calculates_from_monthly_reference(): void
    {
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        $price = new PlanPrice([
            'plan_id' => $premium->id,
            'slug' => 'premium-test',
            'label' => 'Test',
            'price_cents' => 1_790_000,
            'duration_days' => 365,
            'billing_type' => 'prepaid',
            'savings_percent' => null,
            'is_public' => false,
            'sort_order' => 99,
        ]);
        $price->save();
        $price->refresh();

        $this->assertSame(2_388_000, $price->compare_at_price_cents);
        $this->assertSame(25, $price->savings_percent);
    }
}
