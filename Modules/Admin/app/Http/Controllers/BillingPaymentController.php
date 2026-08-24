<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Models\Payment;

final class BillingPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::BillingManage);

        $query = Payment::query()
            ->with(['checkoutSession.user', 'invoice'])
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($provider = $request->query('provider')) {
            $query->where('provider', (string) $provider);
        }

        return view('admin::billing.payments.index', [
            'payments' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'status' => $request->query('status'),
                'provider' => $request->query('provider'),
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
