<?php

declare(strict_types=1);

namespace Modules\Partner\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Payment;
use Modules\Partner\Enums\CommissionStatus;
use Modules\Partner\Models\PartnerAttribution;
use Modules\Partner\Models\PartnerCommission;
use Modules\Partner\Support\PartnerSettings;

/**
 * Create commission from a succeeded payment (idempotent on payment_id).
 *
 * Gated by partner settings: active partner, renewals, first-payment window.
 */
final class RecordPartnerCommissionAction
{
    use AsAction;

    public function handle(Payment $payment, int $referredUserId): ?PartnerCommission
    {
        if ($payment->status !== 'succeeded' || $payment->amount_cents <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payment, $referredUserId): ?PartnerCommission {
            $existing = PartnerCommission::query()
                ->where('payment_id', $payment->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            /** @var PartnerAttribution|null $attribution */
            $attribution = PartnerAttribution::query()
                ->with(['inviteCode.partner', 'partner'])
                ->where('referred_user_id', $referredUserId)
                ->first();

            if ($attribution === null) {
                return null;
            }

            $partner = $attribution->partner;
            if ($partner === null) {
                return null;
            }

            if (PartnerSettings::requireActivePartner() && ! $partner->isActive()) {
                return null;
            }

            if (! PartnerSettings::commissionOnRenewals()) {
                $prior = PartnerCommission::query()
                    ->where('referred_user_id', $referredUserId)
                    ->where('status', '!=', CommissionStatus::Void->value)
                    ->exists();

                if ($prior) {
                    return null;
                }
            }

            $windowDays = PartnerSettings::firstPaymentWindowDays();
            if ($windowDays > 0) {
                $paidAt = $payment->paid_at ?? Carbon::now();
                $deadline = $attribution->attributed_at->copy()->addDays($windowDays);
                if ($paidAt->gt($deadline)) {
                    return null;
                }
            }

            $invite = $attribution->inviteCode;
            $rateBps = $invite?->effectiveRateBps() ?? (int) $partner->default_commission_rate_bps;
            if ($rateBps <= 0) {
                return null;
            }

            $commissionCents = (int) intdiv($payment->amount_cents * $rateBps, 10_000);

            return PartnerCommission::query()->create([
                'partner_id' => $attribution->partner_id,
                'attribution_id' => $attribution->getKey(),
                'payment_id' => $payment->getKey(),
                'referred_user_id' => $referredUserId,
                'gross_cents' => $payment->amount_cents,
                'rate_bps' => $rateBps,
                'commission_cents' => $commissionCents,
                'status' => CommissionStatus::Pending,
            ]);
        });
    }
}
