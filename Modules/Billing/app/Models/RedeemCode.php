<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int|null $plan_id
 * @property list<string>|null $entitlements
 * @property int|null $duration_days
 * @property int|null $max_uses
 * @property int $uses_count
 * @property Carbon|null $expires_at
 * @property string $type
 */
class RedeemCode extends Model
{
    protected $table = 'billing_redeem_codes';

    protected $fillable = [
        'code',
        'plan_id',
        'entitlements',
        'duration_days',
        'max_uses',
        'uses_count',
        'expires_at',
        'type',
    ];

    protected $casts = [
        'entitlements' => 'array',
        'expires_at' => 'datetime',
        'duration_days' => 'integer',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
    ];

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return HasMany<RedeemRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(RedeemRedemption::class, 'redeem_code_id');
    }

    public function isAvailable(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
