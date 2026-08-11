<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $price_cents
 * @property string $currency
 * @property list<string>|null $entitlements
 * @property bool $is_active
 */
class Plan extends Model
{
    protected $table = 'billing_plans';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_cents',
        'currency',
        'entitlements',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'entitlements' => 'array',
        'is_active' => 'boolean',
        'price_cents' => 'integer',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
