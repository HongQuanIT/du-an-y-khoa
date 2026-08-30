<?php

declare(strict_types=1);

namespace Modules\Partner\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Billing\Models\Payment;
use Modules\Partner\Enums\CommissionStatus;

/**
 * @property int $id
 * @property int $partner_id
 * @property int $attribution_id
 * @property int $payment_id
 * @property int $referred_user_id
 * @property int $gross_cents
 * @property int $rate_bps
 * @property int $commission_cents
 * @property string $status
 * @property int|null $payout_id
 * @property Carbon|null $created_at
 */
class PartnerCommission extends Model
{
    protected $table = 'partner_commissions';

    protected $fillable = [
        'partner_id',
        'attribution_id',
        'payment_id',
        'referred_user_id',
        'gross_cents',
        'rate_bps',
        'commission_cents',
        'status',
        'payout_id',
    ];

    protected $casts = [
        'gross_cents' => 'integer',
        'rate_bps' => 'integer',
        'commission_cents' => 'integer',
        'status' => CommissionStatus::class,
    ];

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /** @return BelongsTo<PartnerAttribution, $this> */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(PartnerAttribution::class, 'attribution_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /** @return BelongsTo<PartnerPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(PartnerPayout::class, 'payout_id');
    }
}
