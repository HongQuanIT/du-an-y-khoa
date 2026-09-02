<?php

declare(strict_types=1);

namespace Modules\Billing\Jobs;

use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Actions\ProcessPaymentWebhookAction;
use Modules\Billing\DTO\WebhookPayload;

final class ProcessBillingWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasQueueDisplayName;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    /** Giữ lock trùng webhook trong 1 giờ. */
    public int $uniqueFor = 3600;

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
    ) {
        $this->onQueue(QueueName::Billing->value);
    }

    public function displayName(): string
    {
        return sprintf(
            'billing:webhook:%s:%s:%s',
            $this->provider,
            $this->payload['eventType'],
            $this->payload['eventId'],
        );
    }

    public function uniqueId(): string
    {
        return $this->provider.':'.$this->payload['eventId'];
    }

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

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags(
            'billing',
            'webhook',
            'provider:'.$this->provider,
            'order:'.$this->payload['orderId'],
            'event:'.$this->payload['eventId'],
        );
    }
}
