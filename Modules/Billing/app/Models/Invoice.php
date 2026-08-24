<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $subscription_id
 * @property string $number
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 * @property string $description
 * @property Carbon $issued_at
 */
class Invoice extends Model
{
    protected $table = 'billing_invoices';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'checkout_session_id',
        'number',
        'amount_cents',
        'tax_cents',
        'discount_cents',
        'currency',
        'status',
        'description',
        'issued_at',
        'paid_at',
        'provider_invoice_id',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'tax_cents' => 'integer',
        'discount_cents' => 'integer',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /** @return BelongsTo<CheckoutSession, $this> */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }
}
