<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\Subscription;

final class BillingSubscriptionStats
{
    /** @var array<string, string> */
    public const SOURCE_LABELS = [
        'purchase' => 'Mua trực tiếp',
        'redeem' => 'Đổi mã',
        'institution' => 'Giấy phép tổ chức',
    ];

    /**
     * @return array{
     *     total_students: int,
     *     free_students: int,
     *     premium_students: int,
     *     expiring_premium_students: int,
     * }
     */
    public static function overview(): array
    {
        $premiumPlanId = self::premiumPlanId();
        $totalStudents = self::totalStudents();
        $premiumStudents = self::countPremiumStudents($premiumPlanId);

        return [
            'total_students' => $totalStudents,
            'free_students' => max(0, $totalStudents - $premiumStudents),
            'premium_students' => $premiumStudents,
            'expiring_premium_students' => self::countExpiringPremiumStudents($premiumPlanId),
        ];
    }

    /**
     * @return array{learners: int, history: int}
     */
    public static function forPlan(Plan $plan): array
    {
        if ($plan->isFree()) {
            return [
                'learners' => self::countFreeStudents(),
                'history' => 0,
            ];
        }

        return [
            'learners' => self::countPremiumStudents($plan->id),
            'history' => (int) self::studentSubscriptionsQuery()
                ->where('plan_id', $plan->id)
                ->count(),
        ];
    }

    /**
     * @return array{
     *     active_users: int,
     *     total: int,
     *     by_source: array<string, int>,
     * }
     */
    public static function forPlanPrice(int $planPriceId): array
    {
        $activeQuery = self::studentSubscriptionsQuery()
            ->active()
            ->where('plan_price_id', $planPriceId);

        $bySource = (clone $activeQuery)
            ->select('source', DB::raw('count(distinct user_id) as aggregate'))
            ->groupBy('source')
            ->pluck('aggregate', 'source')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'active_users' => self::countDistinctStudentUsers($activeQuery),
            'total' => (int) self::studentSubscriptionsQuery()
                ->where('plan_price_id', $planPriceId)
                ->count(),
            'by_source' => $bySource,
        ];
    }

    /**
     * Premium học viên chưa gắn SKU cụ thể (đổi mã, giấy phép tổ chức…).
     *
     * @return array{active_users: int, total: int, by_source: array<string, int>}
     */
    public static function unassignedSkuForPlan(int $planId): array
    {
        $activeQuery = self::studentSubscriptionsQuery()
            ->active()
            ->where('plan_id', $planId)
            ->whereNull('plan_price_id');

        $bySource = (clone $activeQuery)
            ->select('source', DB::raw('count(distinct user_id) as aggregate'))
            ->groupBy('source')
            ->pluck('aggregate', 'source')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'active_users' => self::countDistinctStudentUsers($activeQuery),
            'total' => (int) self::studentSubscriptionsQuery()
                ->where('plan_id', $planId)
                ->whereNull('plan_price_id')
                ->count(),
            'by_source' => $bySource,
        ];
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @return array{
     *     plans: array<int, array{learners: int, history: int}>,
     *     prices: array<int, array{active_users: int, total: int, by_source: array<string, int>}>,
     *     unassigned: array<int, array{active_users: int, total: int, by_source: array<string, int>}>,
     * }
     */
    public static function forPlans(Collection $plans): array
    {
        $priceIds = $plans->flatMap(fn (Plan $plan): Collection => $plan->prices)->pluck('id')->all();

        $priceStats = [];
        foreach ($priceIds as $priceId) {
            $priceStats[$priceId] = self::forPlanPrice((int) $priceId);
        }

        $unassigned = [];
        $planStats = [];

        foreach ($plans as $plan) {
            $planStats[$plan->id] = self::forPlan($plan);

            if (! $plan->isFree()) {
                $unassigned[$plan->id] = self::unassignedSkuForPlan($plan->id);
            }
        }

        return [
            'plans' => $planStats,
            'prices' => $priceStats,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * @return list<array{
     *     price: PlanPrice,
     *     active_users: int,
     *     share_percent: float,
     *     by_source: array<string, int>,
     * }>
     */
    public static function premiumSkuBreakdown(Plan $plan): array
    {
        if ($plan->isFree()) {
            return [];
        }

        $plan->loadMissing(['prices' => fn ($query) => $query->ordered()]);

        $premiumStudents = max(1, self::countPremiumStudents($plan->id));
        $rows = [];

        foreach ($plan->prices as $price) {
            if ($price->slug === 'free') {
                continue;
            }

            $stat = self::forPlanPrice($price->id);

            $rows[] = [
                'price' => $price,
                'active_users' => $stat['active_users'],
                'share_percent' => round(($stat['active_users'] / $premiumStudents) * 100, 1),
                'by_source' => $stat['by_source'],
            ];
        }

        return $rows;
    }

    public static function sourceLabel(string $source): string
    {
        return self::SOURCE_LABELS[$source] ?? $source;
    }

    public static function totalStudents(): int
    {
        return (int) User::role(Role::Student->value)->count();
    }

    public static function countPremiumStudents(?int $premiumPlanId = null): int
    {
        $premiumPlanId ??= self::premiumPlanId();

        if ($premiumPlanId === null) {
            return 0;
        }

        return self::countDistinctStudentUsers(
            self::studentSubscriptionsQuery()
                ->active()
                ->where('plan_id', $premiumPlanId),
        );
    }

    public static function countFreeStudents(): int
    {
        return max(0, self::totalStudents() - self::countPremiumStudents());
    }

    /** @param  Builder<Subscription>  $query */
    private static function countDistinctStudentUsers(Builder $query): int
    {
        return (int) (clone $query)->distinct('user_id')->count('user_id');
    }

    /** @return Builder<Subscription> */
    private static function studentSubscriptionsQuery(): Builder
    {
        return Subscription::query()->forStudents();
    }

    private static function premiumPlanId(): ?int
    {
        $id = Plan::query()->where('slug', 'premium')->value('id');

        return $id === null ? null : (int) $id;
    }

    private static function countExpiringPremiumStudents(?int $premiumPlanId): int
    {
        if ($premiumPlanId === null) {
            return 0;
        }

        return self::countDistinctStudentUsers(
            self::studentSubscriptionsQuery()
                ->active()
                ->where('plan_id', $premiumPlanId)
                ->whereNotNull('ends_at')
                ->where('ends_at', '>', Carbon::now())
                ->where('ends_at', '<=', Carbon::now()->addDays(30)),
        );
    }
}
