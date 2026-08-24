<?php

declare(strict_types=1);

namespace Modules\Billing\DTO;

final readonly class CheckoutRequest
{
    public function __construct(
        public string $orderId,
        public int $amountCents,
        public string $currency,
        public string $description,
        public string $returnUrl,
        public string $ipnUrl,
        public string $buyerEmail,
        public string $buyerIp,
    ) {}
}
