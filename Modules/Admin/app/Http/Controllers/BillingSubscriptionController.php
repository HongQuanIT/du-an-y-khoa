<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
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
        $this->authorizePermission(Permission::BillingManage);

        $query = Subscription::query()
            ->forStudents()
            ->with(['user', 'plan', 'planPrice'])
            ->latest('starts_at');

        $status = (string) $request->query('status', 'active');
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'expired') {
            $query->expired();
        }

        if ($planId = $request->query('plan')) {
            $query->where('plan_id', (int) $planId);
        }

        $sku = $request->query('sku');
        if ($sku === 'unassigned') {
            $query->whereNull('plan_price_id');
        } elseif ($sku !== null && $sku !== '') {
            $query->where('plan_price_id', (int) $sku);
        }

        if ($source = $request->query('source')) {
            $query->where('source', (string) $source);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->whereHas('user', function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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
                'status' => $status,
                'plan' => $request->query('plan'),
                'sku' => $sku,
                'source' => $request->query('source'),
            ],
            'canViewUsers' => $this->actor()->can(Permission::UserView->value),
        ]);
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
