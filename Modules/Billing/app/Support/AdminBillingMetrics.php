<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use Illuminate\Support\Carbon;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;

final class AdminBillingMetrics
{
    /**
     * MRR ước tính từ gói Premium active × giá quy đổi tháng (plan_price).
     */
    public static function mrrCents(): int
    {
        $premiumPlanId = Plan::query()->where('slug', 'premium')->value('id');
        if ($premiumPlanId === null) {
            return 0;
        }

        $subscriptions = Subscription::query()
            ->forStudents()
            ->active()
            ->where('plan_id', $premiumPlanId)
            ->with('planPrice:id,price_cents,duration_days,billing_type')
            ->get(['id', 'plan_price_id']);

        return (int) $subscriptions->sum(function (Subscription $subscription): int {
            $price = $subscription->planPrice;
            if ($price === null) {
                return 0;
            }

            $monthly = $price->perMonthCents();

            return $monthly ?? $price->price_cents;
        });
    }

    public static function revenueMonthCents(): int
    {
        return (int) Payment::query()
            ->where('status', 'succeeded')
            ->where('paid_at', '>=', Carbon::now()->startOfMonth())
            ->sum('amount_cents');
    }

    public static function revenueMonthDeltaPercent(): ?float
    {
        $current = self::revenueMonthCents();
        $previous = (int) Payment::query()
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ])
            ->sum('amount_cents');

        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    public static function monthlyRevenueSeries(int $months = 6): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $payments = Payment::query()
            ->where('status', 'succeeded')
            ->where('paid_at', '>=', $start)
            ->get(['paid_at', 'amount_cents']);

        /** @var array<string, int> $totals */
        $totals = [];
        foreach ($payments as $payment) {
            if ($payment->paid_at === null) {
                continue;
            }
            $key = $payment->paid_at->format('Y-m');
            $totals[$key] = ($totals[$key] ?? 0) + (int) $payment->amount_cents;
        }

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $series[] = [
                'label' => 'T'.$month->format('n').'/'.$month->format('Y'),
                'value' => $totals[$key] ?? 0,
            ];
        }

        return $series;
    }

    public static function formatCompactVnd(int $amountCents): string
    {
        if ($amountCents >= 1_000_000_000) {
            return rtrim(rtrim(number_format($amountCents / 1_000_000_000, 2, ',', '.'), '0'), ',').' tỷ₫';
        }

        if ($amountCents >= 1_000_000) {
            return rtrim(rtrim(number_format($amountCents / 1_000_000, 1, ',', '.'), '0'), ',').' triệu₫';
        }

        return MoneyFormatter::vnd($amountCents);
    }
}
