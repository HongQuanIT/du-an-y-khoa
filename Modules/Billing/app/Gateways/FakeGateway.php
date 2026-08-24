<?php

declare(strict_types=1);

namespace Modules\Billing\Gateways;

use Illuminate\Http\Request;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\DTO\CheckoutRequest;
use Modules\Billing\DTO\CheckoutResult;
use Modules\Billing\DTO\WebhookPayload;
use RuntimeException;

/**
 * Local/testing gateway — redirects to an in-app fake payment page.
 */
final class FakeGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        // orderId is the checkout session uuid
        $redirectUrl = route('billing.fake-pay.show', ['uuid' => $request->orderId]);

        return new CheckoutResult(
            redirectUrl: $redirectUrl,
            gatewayOrderId: $request->orderId,
            metadata: ['provider' => 'fake'],
        );
    }

    public function verifyWebhook(Request $request): WebhookPayload
    {
        $orderId = (string) $request->input('order_id', '');
        $success = filter_var($request->input('success', true), FILTER_VALIDATE_BOOLEAN);
        $amount = (int) $request->input('amount_cents', 0);
        $eventId = (string) $request->input('event_id', $orderId.':'.($success ? 'ok' : 'fail'));

        if ($orderId === '') {
            throw new RuntimeException('Fake webhook thiếu order_id.');
        }

        return new WebhookPayload(
            eventId: $eventId,
            eventType: $success ? 'payment.succeeded' : 'payment.failed',
            orderId: $orderId,
            success: $success,
            amountCents: $amount,
            providerPaymentId: 'fake-'.$eventId,
            raw: $request->all(),
        );
    }

    public function supportsRecurring(): bool
    {
        return false;
    }
}
