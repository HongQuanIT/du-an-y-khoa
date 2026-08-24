<?php

declare(strict_types=1);

namespace Modules\Billing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Actions\ProcessPaymentWebhookAction;
use Modules\Billing\DTO\WebhookPayload;

final class ProcessBillingWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *     eventId: string,
     *     eventType: string,
     *     orderId: string,
     *     success: bool,
     *     amountCents: int,
     *     providerPaymentId: ?string,
     *     raw: array<string, mixed>
     * }  $payload
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
    ) {}

    public function handle(ProcessPaymentWebhookAction $action): void
    {
        $action->handle($this->provider, new WebhookPayload(
            eventId: $this->payload['eventId'],
            eventType: $this->payload['eventType'],
            orderId: $this->payload['orderId'],
            success: $this->payload['success'],
            amountCents: $this->payload['amountCents'],
            providerPaymentId: $this->payload['providerPaymentId'] ?? null,
            raw: $this->payload['raw'] ?? [],
        ));
    }
}
