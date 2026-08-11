<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property list<string> $email_domains
 * @property int|null $plan_id
 * @property Carbon|null $valid_until
 * @property bool $is_active
 */
class Institution extends Model
{
    protected $table = 'billing_institutions';

    protected $fillable = [
        'name',
        'email_domains',
        'plan_id',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'email_domains' => 'array',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return HasMany<InstitutionMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(InstitutionMember::class, 'institution_id');
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->valid_until === null || $this->valid_until->isFuture();
    }

    public function matchesEmail(string $email): bool
    {
        $domain = strtolower((string) strrchr($email, '@'));
        $domain = ltrim($domain, '@');
        if ($domain === '') {
            return false;
        }

        foreach ($this->email_domains ?? [] as $allowed) {
            if (strtolower((string) $allowed) === $domain) {
                return true;
            }
        }

        return false;
    }
}
