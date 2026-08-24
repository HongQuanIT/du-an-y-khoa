<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Billing\Actions\CreateCheckoutSessionAction;
use Modules\Billing\Support\GatewayResolver;

final class CheckoutController extends Controller
{
    public function store(Request $request, CreateCheckoutSessionAction $create, GatewayResolver $gateways): JsonResponse
    {
        $data = $request->validate([
            'plan_price_id' => ['required', 'integer', 'exists:billing_plan_prices,id'],
            'gateway' => ['nullable', 'string', Rule::in($gateways->available())],
        ]);

        $session = $create->handle(
            user: $request->user(),
            planPriceId: (int) $data['plan_price_id'],
            idempotencyKey: $request->header('Idempotency-Key'),
            gateway: $data['gateway'] ?? null,
            buyerIp: $request->ip(),
        );

        return ApiResponse::item([
            'type' => 'checkout_session',
            'id' => (string) $session->uuid,
            'attributes' => [
                'status' => $session->status,
                'amount_cents' => $session->totalCents(),
                'currency' => $session->currency,
                'gateway' => $session->gateway,
                'redirect_url' => $session->redirect_url,
                'expires_at' => $session->expires_at?->toIso8601String(),
                'plan_price_id' => $session->plan_price_id,
            ],
        ], 201);
    }
}
