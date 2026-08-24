<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Billing\Actions\InvalidateEntitlementCacheAction;

/**
 * @property int $id
 * @property int $user_id
 * @property int $institution_id
 * @property string $email
 * @property string $status
 * @property Carbon|null $verified_at
 */
class InstitutionMember extends Model
{
    protected $table = 'billing_institution_members';

    protected $fillable = [
        'user_id',
        'institution_id',
        'email',
        'status',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $invalidate = static function (self $member): void {
            InvalidateEntitlementCacheAction::run((int) $member->user_id);
        };

        static::saved($invalidate);
        static::deleted($invalidate);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' && $this->verified_at !== null;
    }
}
