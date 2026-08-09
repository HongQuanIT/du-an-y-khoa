<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TOTP secret + hashed recovery codes for a user (srs Auth §6).
 *
 * @property int $id
 * @property int $user_id
 * @property string $secret
 * @property list<string>|null $recovery_codes
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 */
class TwoFactorSecret extends Model
{
    protected $fillable = [
        'user_id',
        'secret',
        'recovery_codes',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            // Hashed recovery codes as JSON array. Do not use encrypted:array —
            // that stores a ciphertext string MySQL rejects in JSON columns.
            'recovery_codes' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
