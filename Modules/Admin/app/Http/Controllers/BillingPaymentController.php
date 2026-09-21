<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Actions\ExpireStaleCheckoutSessionsAction;
use Modules\Billing\Models\CheckoutSession;

final class BillingPaymentController extends Controller
{
    public function index(Request $request, ExpireStaleCheckoutSessionsAction $expire): View
    {
        $this->authorizePermission('billing_payment.view');

        // Keep admin list accurate even if the scheduler is idle locally.
        $expire->handle();

        $query = CheckoutSession::query()
            ->with(['user', 'planPrice.plan', 'payments' => fn ($q) => $q->latest('id')])
            ->latest('id');

        $statusLabels = [
            'pending' => 'Chờ thanh toán',
            'completed' => 'Thành công',
            'failed' => 'Thất bại',
            'expired' => 'Hết hạn',
        ];
        $statuses = array_values(array_intersect(
            array_map('strval', (array) $request->query('status', [])),
            array_keys($statusLabels),
        ));
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $providers = array_values(array_intersect(
            array_map('strval', (array) $request->query('provider', [])),
            ['fake', 'vnpay', 'momo', 'zalopay'],
        ));
        if ($providers !== []) {
            $query->whereIn('gateway', $providers);
        }

        return view('admin::billing.payments.index', [
            'sessions' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'status' => $statuses,
                'provider' => $providers,
            ],
            'statusLabels' => $statusLabels,
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
