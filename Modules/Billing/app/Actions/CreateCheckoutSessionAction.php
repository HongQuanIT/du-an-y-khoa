<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\DTO\CheckoutRequest;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\GatewayResolver;
use Modules\Billing\Support\GatewaySettings;

final class CreateCheckoutSessionAction
{
    use AsAction;

    public function __construct(
        private readonly GatewayResolver $gateways,
        private readonly GatewaySettings $gatewaySettings,
    ) {}

    public function handle(
        User $user,
        int $planPriceId,
        ?string $idempotencyKey = null,
        ?string $gateway = null,
        ?string $buyerIp = null,
    ): CheckoutSession {
        $idempotencyKey = $idempotencyKey !== null && $idempotencyKey !== ''
            ? $idempotencyKey
            : (string) Str::uuid();

        $existing = CheckoutSession::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            if ((int) $existing->user_id !== (int) $user->getKey()) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Idempotency key không hợp lệ.',
                ]);
            }

            return $existing->load(['planPrice.plan', 'invoice']);
        }

        /** @var PlanPrice|null $planPrice */
        $planPrice = PlanPrice::query()
            ->with('plan')
            ->whereKey($planPriceId)
            ->public()
            ->first();

        if ($planPrice === null || $planPrice->plan === null || ! $planPrice->plan->is_active) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Gói không khả dụng.',
            ]);
        }

        if ($planPrice->price_cents <= 0 || $planPrice->billing_type === 'none') {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Gói miễn phí không cần thanh toán.',
            ]);
        }

        $available = $this->gateways->available();
        if ($available === []) {
            throw ValidationException::withMessages([
                'gateway' => 'Chưa có cổng thanh toán nào sẵn sàng. Liên hệ quản trị viên.',
            ]);
        }

        $gatewayName = $gateway ?: $this->gatewaySettings->defaultGateway();
        if (! in_array($gatewayName, $available, true)) {
            throw ValidationException::withMessages([
                'gateway' => 'Cổng thanh toán không khả dụng.',
            ]);
        }

        $adapter = $this->gateways->resolve($gatewayName);

        $taxRate = (float) config('billing.tax_rate', 0);
        $amountCents = $planPrice->price_cents;
        $taxCents = (int) round($amountCents * $taxRate);
        $ttl = (int) config('billing.checkout_ttl_minutes', 60);

        return DB::transaction(function () use (
            $user,
            $planPrice,
            $idempotencyKey,
            $gatewayName,
            $adapter,
            $amountCents,
            $taxCents,
            $ttl,
            $buyerIp,
        ): CheckoutSession {
            $session = CheckoutSession::query()->create([
                'user_id' => $user->getKey(),
                'plan_price_id' => $planPrice->getKey(),
                'amount_cents' => $amountCents,
                'tax_cents' => $taxCents,
                'discount_cents' => 0,
                'currency' => $planPrice->currency,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'gateway' => $gatewayName,
                'expires_at' => Carbon::now()->addMinutes($ttl),
            ]);

            $invoice = Invoice::query()->create([
                'user_id' => $user->getKey(),
                'checkout_session_id' => $session->getKey(),
                'number' => $this->nextInvoiceNumber(),
                'amount_cents' => $amountCents + $taxCents,
                'tax_cents' => $taxCents,
                'discount_cents' => 0,
                'currency' => $planPrice->currency,
                'status' => 'open',
                'description' => sprintf(
                    'Premium %s — %s',
                    $planPrice->plan?->name ?? 'Premium',
                    $planPrice->label,
                ),
                'issued_at' => Carbon::now(),
            ]);

            Payment::query()->create([
                'invoice_id' => $invoice->getKey(),
                'checkout_session_id' => $session->getKey(),
                'amount_cents' => $session->totalCents(),
                'currency' => $session->currency,
                'method' => $gatewayName,
                'status' => 'pending',
                'provider' => $gatewayName,
            ]);

            $result = $adapter->createCheckout(new CheckoutRequest(
                orderId: $session->uuid,
                amountCents: $session->totalCents(),
                currency: $session->currency,
                description: 'MedLearn Premium — '.$planPrice->label,
                returnUrl: route('billing.return', ['gateway' => $gatewayName]),
                ipnUrl: route('webhooks.billing', ['provider' => $gatewayName]),
                buyerEmail: (string) $user->email,
                buyerIp: $buyerIp ?? '',
            ));

            $session->forceFill([
                'redirect_url' => $result->redirectUrl,
                'gateway_order_id' => $result->gatewayOrderId ?? $session->uuid,
            ])->save();

            return $session->load(['planPrice.plan', 'invoice']);
        });
    }

    private function nextInvoiceNumber(): string
    {
        $year = Carbon::now()->format('Y');
        $count = Invoice::query()->where('number', 'like', "INV-{$year}-%")->count() + 1;

        return sprintf('INV-%s-%04d', $year, $count);
    }
}
