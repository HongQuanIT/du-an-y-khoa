<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property list<string>|null $features
 * @property bool $is_active
 * @property int $sort_order
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
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'entitlements' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'price_cents' => 'integer',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /** @return HasMany<PlanPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class, 'plan_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isFree(): bool
    {
        return $this->slug === 'free';
    }
}
