<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Actions\ProcessPaymentWebhookAction;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Support\GatewayResolver;
use Throwable;

/**
 * Browser return URL from payment gateways (user-facing redirect after pay).
 * IPN/webhook remains the source of truth; this path also attempts activation.
 */
final class BillingReturnController extends Controller
{
    public function __invoke(
        Request $request,
        string $gateway,
        GatewayResolver $gateways,
        ProcessPaymentWebhookAction $process,
    ): RedirectResponse {
        try {
            $adapter = $gateways->resolve($gateway);
            $payload = $adapter->verifyWebhook($request);
            $process->handle($gateway, $payload);
        } catch (Throwable $e) {
            Log::warning('billing.return.failed', [
                'gateway' => $gateway,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('subscription.upgrade')
                ->with('error', 'Không xác minh được thanh toán. Nếu đã trừ tiền, Premium sẽ kích hoạt trong vài phút.');
        }

        $session = CheckoutSession::query()
            ->where('uuid', $payload->orderId)
            ->orWhere('gateway_order_id', $payload->orderId)
            ->first();

        if ($session !== null && $payload->success) {
            return redirect()
                ->route('billing.confirmation', $session->uuid)
                ->with('status', 'Thanh toán thành công.');
        }

        return redirect()
            ->route('subscription.upgrade')
            ->with('error', 'Thanh toán không thành công. Vui lòng thử lại.');
    }
}
