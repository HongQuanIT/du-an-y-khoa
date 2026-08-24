<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Billing\DTO\WebhookPayload;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\WebhookEvent;
use Modules\Billing\Support\GatewayResolver;
use Throwable;

final class ProcessPaymentWebhookAction
{
    use AsAction;

    public function __construct(
        private readonly GatewayResolver $gateways,
        private readonly ActivatePurchaseSubscriptionAction $activate,
    ) {}

    public function handle(string $provider, WebhookPayload $payload): WebhookEvent
    {
        $existing = WebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $payload->eventId)
            ->first();

        if ($existing !== null && $existing->status === 'processed') {
            return $existing;
        }

        $event = $existing ?? WebhookEvent::query()->create([
            'provider' => $provider,
            'event_id' => $payload->eventId,
            'event_type' => $payload->eventType,
            'payload' => $payload->raw,
            'status' => 'received',
        ]);

        try {
            /** @var CheckoutSession|null $session */
            $session = CheckoutSession::query()
                ->with('invoice')
                ->where(function ($q) use ($payload): void {
                    $q->where('uuid', $payload->orderId)
                        ->orWhere('gateway_order_id', $payload->orderId);
                })
                ->first();

            if ($session === null) {
                throw new \RuntimeException('Không tìm thấy checkout session: '.$payload->orderId);
            }

            if ($payload->amountCents > 0 && $payload->amountCents !== $session->totalCents()) {
                Log::warning('billing.webhook.amount_mismatch', [
                    'session' => $session->uuid,
                    'expected' => $session->totalCents(),
                    'got' => $payload->amountCents,
                ]);
                // Still proceed if success — VNPay amount unit quirks; trust signature.
            }

            if ($payload->success) {
                $this->activate->handle(
                    $session,
                    $payload->providerPaymentId,
                    $payload->raw,
                );
            } else {
                if ($session->status === 'pending') {
                    $session->forceFill(['status' => 'failed'])->save();

                    Payment::query()->create([
                        'invoice_id' => $session->invoice?->getKey(),
                        'checkout_session_id' => $session->getKey(),
                        'amount_cents' => $session->totalCents(),
                        'currency' => $session->currency,
                        'method' => $provider,
                        'status' => 'failed',
                        'provider' => $provider,
                        'provider_payment_id' => $payload->providerPaymentId,
                        'metadata' => $payload->raw,
                    ]);

                    $session->invoice?->forceFill(['status' => 'void'])->save();
                }
            }

            $event->forceFill([
                'status' => 'processed',
                'processed_at' => Carbon::now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $event;
    }
}
