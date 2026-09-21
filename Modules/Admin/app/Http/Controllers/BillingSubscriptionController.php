<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\BillingSubscriptionStats;

final class BillingSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission('billing_subscription.view');

        $query = Subscription::query()
            ->forStudents()
            ->with(['user', 'plan', 'planPrice'])
            ->latest('starts_at');

        $statuses = array_values(array_intersect(
            array_map('strval', (array) $request->query('status', [])),
            ['active', 'expired'],
        ));
        $statuses = $statuses === [] ? ['active'] : $statuses;
        if ($statuses === ['active']) {
            $query->active();
        } elseif ($statuses === ['expired']) {
            $query->expired();
        }

        $planIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $request->query('plan', []),
        )));
        if ($planIds !== []) {
            $query->whereIn('plan_id', $planIds);
        }

        $skus = array_values(array_filter(array_map('strval', (array) $request->query('sku', []))));
        if ($skus !== []) {
            $skuIds = array_values(array_filter(array_map('intval', $skus)));
            $hasUnassignedSku = in_array('unassigned', $skus, true);
            $query->where(function ($builder) use ($skuIds, $hasUnassignedSku): void {
                if ($skuIds !== []) {
                    $builder->whereIn('plan_price_id', $skuIds);
                }
                if ($hasUnassignedSku) {
                    $method = $skuIds !== [] ? 'orWhereNull' : 'whereNull';
                    $builder->{$method}('plan_price_id');
                }
            });
        }

        $sources = array_values(array_intersect(
            array_map('strval', (array) $request->query('source', [])),
            array_keys(BillingSubscriptionStats::SOURCE_LABELS),
        ));
        if ($sources !== []) {
            $query->whereIn('source', $sources);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $keyword = '%'.addcslashes($search, '\\%_').'%';
            $query->whereHas('user', function ($builder) use ($keyword): void {
                $builder->where('name', 'like', $keyword)
                    ->orWhere('email', 'like', $keyword);
            });
        }

        $plans = Plan::query()->ordered()->get(['id', 'name', 'slug']);
        $prices = PlanPrice::query()
            ->with('plan:id,name')
            ->ordered()
            ->get(['id', 'plan_id', 'label', 'slug']);

        return view('admin::billing.subscriptions.index', [
            'subscriptions' => $query->paginate(20)->withQueryString(),
            'plans' => $plans,
            'prices' => $prices,
            'sourceLabels' => BillingSubscriptionStats::SOURCE_LABELS,
            'filters' => [
                'q' => $search,
                'status' => $statuses,
                'plan' => array_map('strval', $planIds),
                'sku' => $skus,
                'source' => $sources,
            ],
            'canViewUsers' => $this->actor()->can('user.view'),
        ]);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless($this->actor()->canAny([$permission]), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
