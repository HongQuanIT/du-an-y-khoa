<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use InvalidArgumentException;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Gateways\FakeGateway;
use Modules\Billing\Gateways\VNPayGateway;

final class GatewayResolver
{
    public function __construct(
        private readonly GatewaySettings $settings,
    ) {}

    public function default(): PaymentGatewayInterface
    {
        $name = $this->settings->defaultGateway();

        if (! $this->settings->isReady($name)) {
            $available = $this->available();
            $name = $available[0] ?? 'fake';
        }

        return $this->resolve($name);
    }

    public function resolve(string $name): PaymentGatewayInterface
    {
        // Resolve any implemented adapter (webhooks/returns may arrive after toggle-off).
        return match ($name) {
            'vnpay' => app(VNPayGateway::class),
            'fake' => app(FakeGateway::class),
            default => throw new InvalidArgumentException("Cổng thanh toán không hỗ trợ: {$name}"),
        };
    }

    /** @return list<string> */
    public function available(): array
    {
        return $this->settings->availableForCheckout();
    }
}
