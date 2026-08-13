<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\SaveBillingPlanAction;
use Modules\Admin\Actions\SavePlanPriceAction;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\BillingSubscriptionStats;

final class BillingPlanController extends Controller
{
    public function index(): View
    {
        $this->authorizePermission(Permission::BillingManage);

        $plans = Plan::query()
            ->with(['prices' => fn ($query) => $query->ordered()])
            ->withCount('prices')
            ->ordered()
            ->get();

        $stats = BillingSubscriptionStats::forPlans($plans);
        $premiumPlan = $plans->firstWhere('slug', 'premium');

        return view('admin::billing.plans.index', [
            'plans' => $plans,
            'overview' => BillingSubscriptionStats::overview(),
            'stats' => $stats,
            'premiumPlan' => $premiumPlan,
            'skuBreakdown' => $premiumPlan !== null
                ? BillingSubscriptionStats::premiumSkuBreakdown($premiumPlan)
                : [],
            'unassignedSku' => $premiumPlan !== null
                ? BillingSubscriptionStats::unassignedSkuForPlan($premiumPlan->id)
                : null,
            'sourceLabels' => BillingSubscriptionStats::SOURCE_LABELS,
            'canManage' => $this->actor()->can(Permission::BillingManage->value),
        ]);
    }

    public function edit(Plan $plan): View
    {
        $this->authorizePermission(Permission::BillingManage);

        $plan->load(['prices' => fn ($query) => $query->ordered()]);

        $priceStats = [];
        foreach ($plan->prices as $price) {
            $priceStats[$price->id] = BillingSubscriptionStats::forPlanPrice($price->id);
        }

        $unassignedSku = $plan->isFree()
            ? null
            : BillingSubscriptionStats::unassignedSkuForPlan($plan->id);

        return view('admin::billing.plans.form', [
            'plan' => $plan,
            'planStats' => BillingSubscriptionStats::forPlan($plan),
            'priceStats' => $priceStats,
            'unassignedSku' => $unassignedSku,
            'sourceLabels' => BillingSubscriptionStats::SOURCE_LABELS,
            'entitlements' => Entitlement::cases(),
            'entitlementLabels' => \Modules\Billing\Support\EntitlementLabels::map(),
        ]);
    }

    public function update(Request $request, Plan $plan, SaveBillingPlanAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::BillingManage);

        $payload = $this->validatedPlanPayload($request);
        $action->handle($this->actor(), $plan, $payload);

        return back()->with('status', 'Đã lưu gói '.$plan->name.'.');
    }

    public function createPrice(Plan $plan): View
    {
        $this->authorizePermission(Permission::BillingManage);

        return view('admin::billing.plan-prices.form', [
            'plan' => $plan,
            'price' => new PlanPrice([
                'billing_type' => 'prepaid',
                'is_public' => true,
                'sort_order' => ($plan->prices()->max('sort_order') ?? 0) + 10,
            ]),
            'isNew' => true,
        ]);
    }

    public function storePrice(Request $request, Plan $plan, SavePlanPriceAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::BillingManage);

        $action->handle($this->actor(), $plan, null, $this->validatedPricePayload($request, $plan));

        return redirect()
            ->route('admin.billing.plans.edit', $plan)
            ->with('status', 'Đã thêm mức giá.');
    }

    public function editPrice(PlanPrice $planPrice): View
    {
        $this->authorizePermission(Permission::BillingManage);

        $planPrice->load('plan');

        return view('admin::billing.plan-prices.form', [
            'plan' => $planPrice->plan,
            'price' => $planPrice,
            'isNew' => false,
        ]);
    }

    public function updatePrice(Request $request, PlanPrice $planPrice, SavePlanPriceAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::BillingManage);

        $planPrice->load('plan');
        $action->handle($this->actor(), $planPrice->plan, $planPrice, $this->validatedPricePayload($request, $planPrice->plan, $planPrice));

        return redirect()
            ->route('admin.billing.plans.edit', $planPrice->plan)
            ->with('status', 'Đã cập nhật mức giá.');
    }

    public function destroyPrice(PlanPrice $planPrice): RedirectResponse
    {
        $this->authorizePermission(Permission::BillingManage);

        $plan = $planPrice->plan;
        $planPrice->delete();

        return redirect()
            ->route('admin.billing.plans.edit', $plan)
            ->with('status', 'Đã xóa mức giá.');
    }

    /** @return array<string, mixed> */
    private function validatedPlanPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'features_text' => ['nullable', 'string', 'max:5000'],
            'entitlements' => ['nullable', 'array'],
            'entitlements.*' => ['string', Rule::in(Entitlement::values())],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $features = array_values(array_filter(array_map(
            trim(...),
            preg_split('/\r\n|\r|\n/', (string) ($validated['features_text'] ?? '')) ?: [],
        )));

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'features' => $features,
            'entitlements' => $validated['entitlements'] ?? [],
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $validated['sort_order'],
        ];
    }

    /** @return array<string, mixed> */
    private function validatedPricePayload(Request $request, Plan $plan, ?PlanPrice $existing = null): array
    {
        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('billing_plan_prices', 'slug')
                    ->where('plan_id', $plan->getKey())
                    ->ignore($existing?->getKey()),
            ],
            'label' => ['required', 'string', 'max:120'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'billing_type' => ['required', Rule::in(['none', 'recurring', 'prepaid'])],
            'badge_label' => ['nullable', 'string', 'max:80'],
            'savings_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'slug' => $validated['slug'],
            'label' => $validated['label'],
            'price_cents' => (int) $validated['price_cents'],
            'duration_days' => isset($validated['duration_days']) ? (int) $validated['duration_days'] : null,
            'billing_type' => $validated['billing_type'],
            'badge_label' => $validated['badge_label'] ?? null,
            'savings_percent' => isset($validated['savings_percent']) ? (int) $validated['savings_percent'] : null,
            'cta_label' => $validated['cta_label'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
            'is_public' => $request->boolean('is_public'),
            'sort_order' => (int) $validated['sort_order'],
        ];
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
