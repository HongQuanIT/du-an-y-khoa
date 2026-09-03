<?php

declare(strict_types=1);

namespace Modules\Partner\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Support\MoneyFormatter;
use Modules\Partner\Enums\CommissionStatus;
use Modules\Partner\Enums\PayoutStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Models\PartnerCommission;
use Modules\Partner\Models\PartnerPayout;
use Modules\Partner\Support\PartnerSettings;

final class CreatePartnerPayoutAction
{
    use AsAction;

    public function handle(
        Partner $partner,
        Carbon $periodFrom,
        Carbon $periodTo,
        User $actor,
        ?string $note = null,
    ): PartnerPayout {
        return DB::transaction(function () use ($partner, $periodFrom, $periodTo, $actor, $note): PartnerPayout {
            $commissions = PartnerCommission::query()
                ->where('partner_id', $partner->getKey())
                ->where('status', CommissionStatus::Pending)
                ->whereDate('created_at', '>=', $periodFrom->toDateString())
                ->whereDate('created_at', '<=', $periodTo->toDateString())
                ->lockForUpdate()
                ->get();

            if ($commissions->isEmpty()) {
                throw ValidationException::withMessages([
                    'period' => 'Không có hoa hồng chờ duyệt trong kỳ này.',
                ]);
            }

            $amount = (int) $commissions->sum('commission_cents');
            $minimumPayout = PartnerSettings::minPayoutCents();

            if ($minimumPayout > 0 && $amount < $minimumPayout) {
                throw ValidationException::withMessages([
                    'period' => 'Tổng hoa hồng ('.MoneyFormatter::vnd($amount).') chưa đạt mức tối thiểu '
                        .MoneyFormatter::vnd($minimumPayout).'.',
                ]);
            }

            $payout = PartnerPayout::query()->create([
                'partner_id' => $partner->getKey(),
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'amount_cents' => $amount,
                'status' => PayoutStatus::Draft,
                'note' => $note,
                'created_by' => $actor->getKey(),
            ]);

            PartnerCommission::query()
                ->whereIn('id', $commissions->modelKeys())
                ->update([
                    'payout_id' => $payout->getKey(),
                    'status' => CommissionStatus::Approved->value,
                ]);

            $payout->forceFill([
                'status' => PayoutStatus::Approved,
            ])->save();

            return $payout->fresh();
        });
    }
}
