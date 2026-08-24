<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Actions\ProcessPaymentWebhookAction;
use Modules\Billing\Jobs\ProcessBillingWebhookJob;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Support\GatewayResolver;
use Throwable;

final class BillingWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        GatewayResolver $gateways,
        ProcessPaymentWebhookAction $process,
    ): JsonResponse|RedirectResponse {
        try {
            $gateway = $gateways->resolve($provider);
            $payload = $gateway->verifyWebhook($request);
        } catch (Throwable $e) {
            Log::warning('billing.webhook.verify_failed', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature'], 400);
        }

        // Process sync for reliability on IPN; job available for heavy work later.
        try {
            if (config('queue.default') === 'sync') {
                $process->handle($provider, $payload);
            } else {
                ProcessBillingWebhookJob::dispatch($provider, [
                    'eventId' => $payload->eventId,
                    'eventType' => $payload->eventType,
                    'orderId' => $payload->orderId,
                    'success' => $payload->success,
                    'amountCents' => $payload->amountCents,
                    'providerPaymentId' => $payload->providerPaymentId,
                    'raw' => $payload->raw,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('billing.webhook.process_failed', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['RspCode' => '99', 'Message' => 'Processing error'], 500);
        }

        // VNPay IPN expects RspCode 00
        if ($provider === 'vnpay' && ! $request->expectsJson() && $request->isMethod('get')) {
            // Return URL flow — redirect user to confirmation
            $session = CheckoutSession::query()
                ->where('uuid', $payload->orderId)
                ->orWhere('gateway_order_id', $payload->orderId)
                ->first();

            if ($session !== null && $payload->success) {
                return redirect()->route('billing.confirmation', $session->uuid);
            }

            return redirect()
                ->route('subscription.upgrade')
                ->with('error', 'Thanh toán không thành công.');
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }
}
