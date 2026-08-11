<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RedeemRedemption extends Model
{
    protected $table = 'billing_redeem_redemptions';

    protected $fillable = [
        'user_id',
        'redeem_code_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<RedeemCode, $this> */
    public function redeemCode(): BelongsTo
    {
        return $this->belongsTo(RedeemCode::class, 'redeem_code_id');
    }
}
