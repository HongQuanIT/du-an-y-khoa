<?php

declare(strict_types=1);

namespace Modules\Partner\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Partner\Enums\AttributionSource;

/**
 * @property int $id
 * @property int $partner_id
 * @property int $invite_code_id
 * @property int $referred_user_id
 * @property Carbon $attributed_at
 * @property string $source
 */
class PartnerAttribution extends Model
{
    protected $table = 'partner_attributions';

    protected $fillable = [
        'partner_id',
        'invite_code_id',
        'referred_user_id',
        'attributed_at',
        'source',
    ];

    protected $casts = [
        'attributed_at' => 'datetime',
        'source' => AttributionSource::class,
    ];

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /** @return BelongsTo<PartnerInviteCode, $this> */
    public function inviteCode(): BelongsTo
    {
        return $this->belongsTo(PartnerInviteCode::class, 'invite_code_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /** @return HasMany<PartnerCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class, 'attribution_id');
    }
}
