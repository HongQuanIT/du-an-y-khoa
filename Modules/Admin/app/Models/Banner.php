<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Admin\Support\Enums\BannerAudience;
use Modules\Admin\Support\Enums\BannerPlacement;
use Modules\Admin\Support\Enums\BannerVariant;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'body',
        'cta_label',
        'cta_url',
        'variant',
        'placement',
        'audience',
        'is_enabled',
        'is_dismissible',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant' => BannerVariant::class,
            'placement' => BannerPlacement::class,
            'audience' => BannerAudience::class,
            'is_enabled' => 'boolean',
            'is_dismissible' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isCurrentlyScheduled(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $at->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPlacement(Builder $query, BannerPlacement $placement): Builder
    {
        return $query->where(function (Builder $builder) use ($placement): void {
            $builder->where('placement', BannerPlacement::Both->value)
                ->orWhere('placement', $placement->value);
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveSchedule(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public static function nextSortOrder(): int
    {
        return ((int) self::query()->max('sort_order')) + 10;
    }
}
