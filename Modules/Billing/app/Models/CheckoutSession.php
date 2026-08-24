<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $plan_price_id
 * @property string|null $coupon_code
 * @property int $amount_cents
 * @property int $tax_cents
 * @property int $discount_cents
 * @property string $currency
 * @property string $status
 * @property string $idempotency_key
 * @property string $gateway
 * @property string|null $gateway_order_id
 * @property string|null $redirect_url
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 */
class CheckoutSession extends Model
{
    protected $table = 'billing_checkout_sessions';

    protected $fillable = [
        'uuid',
        'user_id',
        'plan_price_id',
        'coupon_code',
        'amount_cents',
        'tax_cents',
        'discount_cents',
        'currency',
        'status',
        'idempotency_key',
        'gateway',
        'gateway_order_id',
        'redirect_url',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'tax_cents' => 'integer',
        'discount_cents' => 'integer',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if ($session->uuid === null || $session->uuid === '') {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PlanPrice, $this> */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'checkout_session_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'checkout_session_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function totalCents(): int
    {
        return max(0, $this->amount_cents + $this->tax_cents - $this->discount_cents);
    }
}
