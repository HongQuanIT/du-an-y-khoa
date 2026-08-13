<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Actions\ListPublicPlansAction;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\CurrentSubscription;
use Modules\Billing\Support\EntitlementLabels;

final class PlanController extends Controller
{
    public function index(ListPublicPlansAction $list): JsonResponse
    {
        $catalog = $list->handle();

        $data = $catalog['plans']->map(fn (Plan $plan): array => [
            'id' => $plan->id,
            'type' => 'plan',
            'attributes' => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'features' => $plan->features ?? [],
                'entitlements' => $plan->entitlements ?? [],
                'entitlement_labels' => EntitlementLabels::labels($plan->entitlements),
                'is_free' => $plan->isFree(),
                'prices' => $plan->prices->map(fn (PlanPrice $price): array => self::pricePayload($price))->values(),
            ],
        ])->values();

        return ApiResponse::item(['plans' => $data]);
    }

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return ApiResponse::error('UNAUTHENTICATED', 'Cần đăng nhập.', 401);
        }

        $current = CurrentSubscription::for($user);

        return ApiResponse::item([
            'id' => $current['plan_id'],
            'type' => 'subscription',
            'attributes' => $current,
        ]);
    }

    /** @return array<string, mixed> */
    private static function pricePayload(PlanPrice $price): array
    {
        return [
            'id' => $price->id,
            'slug' => $price->slug,
            'label' => $price->label,
            'price_cents' => $price->price_cents,
            'compare_at_price_cents' => $price->listPriceCents(),
            'per_month_cents' => $price->perMonthCents(),
            'currency' => $price->currency,
            'duration_days' => $price->duration_days,
            'billing_type' => $price->billing_type,
            'badge_label' => $price->badge_label,
            'savings_percent' => $price->displaySavingsPercent(),
            'cta_label' => $price->cta_label,
            'is_featured' => $price->is_featured,
        ];
    }
}
