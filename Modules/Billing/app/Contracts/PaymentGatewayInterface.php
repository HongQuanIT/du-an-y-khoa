<?php

declare(strict_types=1);

namespace Modules\Billing\Contracts;

use Illuminate\Http\Request;
use Modules\Billing\DTO\CheckoutRequest;
use Modules\Billing\DTO\CheckoutResult;
use Modules\Billing\DTO\WebhookPayload;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function createCheckout(CheckoutRequest $request): CheckoutResult;

    public function verifyWebhook(Request $request): WebhookPayload;

    public function supportsRecurring(): bool;
}
