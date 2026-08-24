<?php

declare(strict_types=1);

namespace Modules\Billing\DTO;

final readonly class CheckoutResult
{
    public function __construct(
        public string $redirectUrl,
        public ?string $gatewayOrderId = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
