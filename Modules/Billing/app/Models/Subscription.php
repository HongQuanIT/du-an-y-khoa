<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property int|null $plan_price_id
 * @property string $status
 * @property string $source
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 */
class Subscription extends Model
{
    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_price_id',
        'status',
        'source',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return BelongsTo<PlanPrice, $this> */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'subscription_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(function (Builder $builder): void {
                $builder->whereNull('ends_at')
                    ->orWhere('ends_at', '>', Carbon::now());
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder->where('status', '!=', 'active')
                ->orWhere(function (Builder $inner): void {
                    $inner->where('status', 'active')
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<=', Carbon::now());
                });
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForStudents(Builder $query): Builder
    {
        return $query->whereHas('user', fn (Builder $builder): Builder => $builder->role(Role::Student->value));
    }
}
