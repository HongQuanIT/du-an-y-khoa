<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Subscription;

/**
 * Activate Premium after a successful payment for a checkout session.
 * Same-plan renewals extend ends_at (stack remaining days).
 */
final class ActivatePurchaseSubscriptionAction
{
    use AsAction;

    public function __construct(
        private readonly InvalidateEntitlementCacheAction $invalidateCache,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        CheckoutSession $session,
        ?string $providerPaymentId = null,
        array $metadata = [],
    ): Subscription {
        return DB::transaction(function () use ($session, $providerPaymentId, $metadata): Subscription {
            /** @var CheckoutSession $locked */
            $locked = CheckoutSession::query()
                ->with(['planPrice.plan', 'invoice'])
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            if ($locked->isCompleted()) {
                /** @var Subscription $existing */
                $existing = Subscription::query()
                    ->where('checkout_session_id', $locked->getKey())
                    ->orderByDesc('id')
                    ->firstOrFail();

                return $existing->load(['plan', 'planPrice']);
            }

            if (in_array($locked->status, ['failed', 'expired'], true)) {
                throw ValidationException::withMessages([
                    'checkout' => 'Phiên thanh toán đã kết thúc.',
                ]);
            }

            $planPrice = $locked->planPrice;
            $plan = $planPrice?->plan;

            if ($planPrice === null || $plan === null) {
                throw ValidationException::withMessages([
                    'checkout' => 'Gói thanh toán không hợp lệ.',
                ]);
            }

            $durationDays = max(1, $planPrice->duration_days ?? 30);
            $now = Carbon::now();

            /** @var Subscription|null $activeSamePlan */
            $activeSamePlan = Subscription::query()
                ->where('user_id', $locked->user_id)
                ->where('plan_id', $plan->getKey())
                ->where('status', 'active')
                ->where(function ($query) use ($now): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', $now);
                })
                ->orderByDesc('ends_at')
                ->lockForUpdate()
                ->first();

            if ($activeSamePlan !== null) {
                $base = $activeSamePlan->ends_at !== null && $activeSamePlan->ends_at->isFuture()
                    ? $activeSamePlan->ends_at->copy()
                    : $now->copy();

                $activeSamePlan->forceFill([
                    'ends_at' => $base->addDays($durationDays),
                    'plan_price_id' => $planPrice->getKey(),
                    'checkout_session_id' => $locked->getKey(),
                    'provider' => $locked->gateway,
                    'source' => 'purchase',
                ])->save();

                $subscription = $activeSamePlan;
            } else {
                $subscription = Subscription::query()->create([
                    'user_id' => $locked->user_id,
                    'plan_id' => $plan->getKey(),
                    'plan_price_id' => $planPrice->getKey(),
                    'checkout_session_id' => $locked->getKey(),
                    'status' => 'active',
                    'source' => 'purchase',
                    'starts_at' => $now,
                    'ends_at' => $now->copy()->addDays($durationDays),
                    'provider' => $locked->gateway,
                ]);
            }

            /** @var Invoice|null $invoice */
            $invoice = $locked->invoice;
            if ($invoice !== null) {
                $invoice->forceFill([
                    'subscription_id' => $subscription->getKey(),
                    'status' => 'paid',
                    'paid_at' => $now,
                    'provider_invoice_id' => $providerPaymentId,
                ])->save();
            }

            Payment::query()->create([
                'invoice_id' => $invoice?->getKey(),
                'checkout_session_id' => $locked->getKey(),
                'amount_cents' => $locked->totalCents(),
                'currency' => $locked->currency,
                'method' => $locked->gateway,
                'status' => 'succeeded',
                'provider' => $locked->gateway,
                'provider_payment_id' => $providerPaymentId,
                'metadata' => $metadata !== [] ? $metadata : null,
                'paid_at' => $now,
            ]);

            $locked->forceFill([
                'status' => 'completed',
                'completed_at' => $now,
            ])->save();

            $this->invalidateCache->handle((int) $locked->user_id);

            return $subscription->load(['plan', 'planPrice']);
        });
    }
}
