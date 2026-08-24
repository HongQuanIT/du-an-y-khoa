<?php

declare(strict_types=1);

namespace Modules\Billing\DTO;

final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $orderId,
        public bool $success,
        public int $amountCents,
        public ?string $providerPaymentId = null,
        public array $raw = [],
    ) {}
}
