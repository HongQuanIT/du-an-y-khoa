<?php

declare(strict_types=1);

namespace Modules\Partner\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Partner\Support\PartnerSettings;

/**
 * @property int $id
 * @property int $partner_id
 * @property string $code
 * @property string|null $label
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property int|null $max_uses
 * @property int $use_count
 * @property int|null $commission_rate_bps
 * @property bool $is_active
 */
class PartnerInviteCode extends Model
{
    protected $table = 'partner_invite_codes';

    protected $fillable = [
        'partner_id',
        'code',
        'label',
        'starts_at',
        'expires_at',
        'max_uses',
        'use_count',
        'commission_rate_bps',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'use_count' => 'integer',
        'commission_rate_bps' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $code): void {
            $code->code = Str::upper(trim($code->code));
        });
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /** @return HasMany<PartnerAttribution, $this> */
    public function attributions(): HasMany
    {
        return $this->hasMany(PartnerAttribution::class, 'invite_code_id');
    }

    public function isCurrentlyValid(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at !== null && $at->gt($this->expires_at)) {
            return false;
        }

        if ($this->max_uses !== null && $this->use_count >= $this->max_uses) {
            return false;
        }

        $partner = $this->relationLoaded('partner') ? $this->partner : $this->partner()->first();

        if ($partner === null) {
            return false;
        }

        if (PartnerSettings::requireActivePartner()) {
            return $partner->isActive();
        }

        return true;
    }

    public function effectiveRateBps(): int
    {
        if ($this->commission_rate_bps !== null) {
            return $this->commission_rate_bps;
        }

        $partner = $this->relationLoaded('partner') ? $this->partner : $this->partner()->first();

        return (int) ($partner?->default_commission_rate_bps ?? 0);
    }

    public function registerUrl(): string
    {
        return route('register', ['ref' => $this->code]);
    }

    /** @param  Builder<self>  $query */
    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->where('code', Str::upper(trim($code)));
    }
}
