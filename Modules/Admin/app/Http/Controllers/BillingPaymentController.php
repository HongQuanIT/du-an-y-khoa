<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Actions\ExpireStaleCheckoutSessionsAction;
use Modules\Billing\Models\CheckoutSession;

final class BillingPaymentController extends Controller
{
    public function index(Request $request, ExpireStaleCheckoutSessionsAction $expire): View
    {
        $this->authorizePermission(Permission::BillingManage);

        // Keep admin list accurate even if the scheduler is idle locally.
        $expire->handle();

        $query = CheckoutSession::query()
            ->with(['user', 'planPrice.plan', 'payments' => fn ($q) => $q->latest('id')])
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($provider = $request->query('provider')) {
            $query->where('gateway', (string) $provider);
        }

        return view('admin::billing.payments.index', [
            'sessions' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'status' => $request->query('status'),
                'provider' => $request->query('provider'),
            ],
            'statusLabels' => [
                'pending' => 'Chờ thanh toán',
                'completed' => 'Thành công',
                'failed' => 'Thất bại',
                'expired' => 'Hết hạn',
            ],
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
