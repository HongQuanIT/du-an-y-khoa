<?php

declare(strict_types=1);

namespace Modules\Partner\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Partner\Enums\PartnerStatus;

/**
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property int $default_commission_rate_bps
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Partner extends Model
{
    protected $table = 'partners';

    protected $fillable = [
        'user_id',
        'display_name',
        'default_commission_rate_bps',
        'status',
    ];

    protected $casts = [
        'default_commission_rate_bps' => 'integer',
        'status' => PartnerStatus::class,
    ];

    public function isActive(): bool
    {
        return $this->status === PartnerStatus::Active;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PartnerInviteCode, $this> */
    public function inviteCodes(): HasMany
    {
        return $this->hasMany(PartnerInviteCode::class, 'partner_id');
    }

    /** @return HasMany<PartnerAttribution, $this> */
    public function attributions(): HasMany
    {
        return $this->hasMany(PartnerAttribution::class, 'partner_id');
    }

    /** @return HasMany<PartnerCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class, 'partner_id');
    }

    /** @return HasMany<PartnerPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class, 'partner_id');
    }

    public function commissionRatePercent(): float
    {
        return round($this->default_commission_rate_bps / 100, 2);
    }

    public static function forUser(User $user): ?self
    {
        return self::query()->where('user_id', $user->getKey())->first();
    }
}
