<?php

declare(strict_types=1);

namespace Modules\Partner\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Partner\Enums\PayoutStatus;

/**
 * @property int $id
 * @property int $partner_id
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property int $amount_cents
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $note
 * @property int|null $created_by
 */
class PartnerPayout extends Model
{
    protected $table = 'partner_payouts';

    protected $fillable = [
        'partner_id',
        'period_from',
        'period_to',
        'amount_cents',
        'status',
        'paid_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'amount_cents' => 'integer',
        'status' => PayoutStatus::class,
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PartnerCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class, 'payout_id');
    }
}
