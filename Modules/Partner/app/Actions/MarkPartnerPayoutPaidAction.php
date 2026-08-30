<?php

declare(strict_types=1);

namespace Modules\Partner\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Partner\Enums\CommissionStatus;
use Modules\Partner\Enums\PayoutStatus;
use Modules\Partner\Models\PartnerCommission;
use Modules\Partner\Models\PartnerPayout;

final class MarkPartnerPayoutPaidAction
{
    use AsAction;

    public function handle(PartnerPayout $payout): PartnerPayout
    {
        if ($payout->status === PayoutStatus::Paid) {
            return $payout;
        }

        if (! in_array($payout->status, [PayoutStatus::Draft, PayoutStatus::Approved], true)) {
            throw ValidationException::withMessages([
                'payout' => 'Không thể đánh dấu đã chi cho kỳ này.',
            ]);
        }

        return DB::transaction(function () use ($payout): PartnerPayout {
            /** @var PartnerPayout $locked */
            $locked = PartnerPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            $locked->forceFill([
                'status' => PayoutStatus::Paid,
                'paid_at' => Carbon::now(),
            ])->save();

            PartnerCommission::query()
                ->where('payout_id', $locked->getKey())
                ->update(['status' => CommissionStatus::Paid->value]);

            return $locked->fresh();
        });
    }
}
