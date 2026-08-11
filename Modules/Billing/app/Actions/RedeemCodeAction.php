<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\RedeemCode;
use Modules\Billing\Models\RedeemRedemption;
use Modules\Billing\Models\Subscription;

final class RedeemCodeAction
{
    use AsAction;

    public function handle(User $user, string $code): Subscription
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'code' => 'Vui lòng nhập mã kích hoạt.',
            ]);
        }

        return DB::transaction(function () use ($user, $normalized): Subscription {
            /** @var RedeemCode|null $redeemCode */
            $redeemCode = RedeemCode::query()
                ->lockForUpdate()
                ->where('code', $normalized)
                ->first();

            if ($redeemCode === null) {
                throw ValidationException::withMessages([
                    'code' => 'Mã không hợp lệ hoặc đã hết hạn.',
                ]);
            }

            if (! $redeemCode->isAvailable()) {
                throw ValidationException::withMessages([
                    'code' => 'Mã không hợp lệ hoặc đã hết hạn.',
                ]);
            }

            $alreadyRedeemed = RedeemRedemption::query()
                ->where('user_id', $user->getKey())
                ->where('redeem_code_id', $redeemCode->getKey())
                ->exists();

            if ($alreadyRedeemed) {
                throw ValidationException::withMessages([
                    'code' => 'Bạn đã sử dụng mã này trước đó.',
                ]);
            }

            $plan = $this->resolvePlan($redeemCode);
            $startsAt = Carbon::now();
            $endsAt = $redeemCode->duration_days !== null
                ? $startsAt->copy()->addDays($redeemCode->duration_days)
                : null;

            $subscription = Subscription::query()->create([
                'user_id' => $user->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => 'active',
                'source' => 'redeem',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            RedeemRedemption::query()->create([
                'user_id' => $user->getKey(),
                'redeem_code_id' => $redeemCode->getKey(),
                'redeemed_at' => $startsAt,
            ]);

            $redeemCode->increment('uses_count');

            if ($plan->price_cents > 0) {
                Invoice::query()->create([
                    'user_id' => $user->getKey(),
                    'subscription_id' => $subscription->getKey(),
                    'number' => $this->nextInvoiceNumber(),
                    'amount_cents' => $plan->price_cents,
                    'currency' => $plan->currency,
                    'status' => 'paid',
                    'description' => 'Đổi mã: '.$redeemCode->code,
                    'issued_at' => $startsAt,
                ]);
            }

            return $subscription->load('plan');
        });
    }

    private function resolvePlan(RedeemCode $redeemCode): Plan
    {
        if ($redeemCode->plan instanceof Plan) {
            return $redeemCode->plan;
        }

        /** @var Plan $promoPlan */
        $promoPlan = Plan::query()->firstOrCreate(
            ['slug' => 'promo-redeem'],
            [
                'name' => 'Khuyến mãi',
                'description' => 'Quyền truy cập từ mã đổi',
                'price_cents' => 0,
                'currency' => 'VND',
                'entitlements' => $redeemCode->entitlements ?? [],
                'is_active' => true,
                'sort_order' => 99,
            ],
        );

        if ($redeemCode->entitlements !== null && $redeemCode->entitlements !== []) {
            $promoPlan->forceFill([
                'entitlements' => $redeemCode->entitlements,
            ])->save();
        }

        return $promoPlan;
    }

    private function nextInvoiceNumber(): string
    {
        $year = Carbon::now()->format('Y');
        $count = Invoice::query()->where('number', 'like', "INV-{$year}-%")->count() + 1;

        return sprintf('INV-%s-%04d', $year, $count);
    }
}
