<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $slug
 * @property string $label
 * @property int $price_cents
 * @property int|null $compare_at_price_cents
 * @property string $currency
 * @property int|null $duration_days
 * @property string $billing_type
 * @property string|null $badge_label
 * @property int|null $savings_percent
 * @property string|null $cta_label
 * @property bool $is_featured
 * @property bool $is_public
 * @property int $sort_order
 */
class PlanPrice extends Model
{
    protected $table = 'billing_plan_prices';

    protected $fillable = [
        'plan_id',
        'slug',
        'label',
        'price_cents',
        'currency',
        'duration_days',
        'billing_type',
        'badge_label',
        'savings_percent',
        'cta_label',
        'is_featured',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'compare_at_price_cents' => 'integer',
        'duration_days' => 'integer',
        'savings_percent' => 'integer',
        'is_featured' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (PlanPrice $price): void {
            $price->syncDerivedPricing();
        });
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_price_id');
    }

    /** @param Builder<self> $query */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** @param Builder<self> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function perMonthCents(): ?int
    {
        if ($this->duration_days === null || $this->duration_days <= 0) {
            return null;
        }

        return (int) round($this->price_cents / ($this->duration_days / 30));
    }

    /** Giá gạch ngang hiển thị (tự tính, không nhập tay). */
    public function listPriceCents(): ?int
    {
        return $this->compare_at_price_cents ?? $this->deriveCompareAtPriceCents();
    }

    /** % tiết kiệm hiển thị (ưu tiên giá trị admin; không có thì suy từ giá tháng × thời hạn). */
    public function displaySavingsPercent(): ?int
    {
        if ($this->savings_percent !== null) {
            return $this->savings_percent;
        }

        $list = $this->referenceListPriceCents();
        if ($list === null || $list <= 0 || $this->price_cents <= 0) {
            return null;
        }

        return (int) max(0, min(100, round((1 - $this->price_cents / $list) * 100)));
    }

    /**
     * Giá lẻ tham chiếu = SKU tháng (recurring 30 ngày) × số tháng.
     */
    public function referenceListPriceCents(): ?int
    {
        if ($this->billing_type !== 'prepaid' || $this->duration_days === null || $this->duration_days <= 0) {
            return null;
        }

        $monthly = static::query()
            ->where('plan_id', $this->plan_id)
            ->where('billing_type', 'recurring')
            ->where('duration_days', 30)
            ->value('price_cents');

        if ($monthly === null) {
            return null;
        }

        return (int) ($monthly * (int) round($this->duration_days / 30));
    }

    public function syncDerivedPricing(): void
    {
        if ($this->billing_type !== 'prepaid') {
            $this->compare_at_price_cents = null;

            return;
        }

        if ($this->savings_percent !== null && $this->savings_percent > 0 && $this->savings_percent < 100) {
            $this->compare_at_price_cents = (int) round(
                $this->price_cents / (1 - $this->savings_percent / 100),
            );

            return;
        }

        $list = $this->referenceListPriceCents();
        $this->compare_at_price_cents = $list;

        if ($list !== null && $list > 0 && $this->price_cents > 0) {
            $this->savings_percent = (int) max(0, min(100, round((1 - $this->price_cents / $list) * 100)));
        }
    }

    private function deriveCompareAtPriceCents(): ?int
    {
        if ($this->billing_type !== 'prepaid') {
            return null;
        }

        if ($this->savings_percent !== null && $this->savings_percent > 0 && $this->savings_percent < 100) {
            return (int) round($this->price_cents / (1 - $this->savings_percent / 100));
        }

        return $this->referenceListPriceCents();
    }
}
