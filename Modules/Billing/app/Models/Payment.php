<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property int|null $checkout_session_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $method
 * @property string $status
 * @property string $provider
 * @property string|null $provider_payment_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    protected $table = 'billing_payments';

    protected $fillable = [
        'invoice_id',
        'checkout_session_id',
        'amount_cents',
        'currency',
        'method',
        'status',
        'provider',
        'provider_payment_id',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /** @return BelongsTo<CheckoutSession, $this> */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }
}
