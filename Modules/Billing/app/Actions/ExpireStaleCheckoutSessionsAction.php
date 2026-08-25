<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;

/**
 * Mark overdue pending checkouts as expired (and sync payment/invoice).
 */
final class ExpireStaleCheckoutSessionsAction
{
    use AsAction;

    public function handle(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        return (int) DB::transaction(function () use ($now): int {
            $ids = CheckoutSession::query()
                ->where('status', 'pending')
                ->where('expires_at', '<=', $now)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            CheckoutSession::query()
                ->whereIn('id', $ids)
                ->update(['status' => 'expired']);

            Payment::query()
                ->whereIn('checkout_session_id', $ids)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            Invoice::query()
                ->whereIn('checkout_session_id', $ids)
                ->where('status', 'open')
                ->update(['status' => 'void']);

            return $ids->count();
        });
    }
}
